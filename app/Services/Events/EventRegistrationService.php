<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Support\Audit\Auditor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventRegistrationService
{
    public function __construct(private readonly EventAccess $access)
    {
    }

    public function registrations(Request $request, ?SchoolEvent $event = null, array $filters = []): LengthAwarePaginator
    {
        abort_unless($request->user()?->hasPermission('activities.event_registrations.view'), 403);

        return $this->access->scopeRegistrations($request->user(), EventRegistration::query())
            ->with(['event:id,title,status,event_type,semester_id', 'category:id,name,participation_type,sport_rule,scoring_mode', 'team.members.student:id,student_code,full_name', 'schoolClass:id,name', 'student:id,student_code,full_name', 'registeredBy:id,name', 'approvedBy:id,name'])
            ->when($event, fn (Builder $query): Builder => $query->where('event_id', $event->id))
            ->when(! empty($filters['event_id']), fn (Builder $query): Builder => $query->where('event_id', (int) $filters['event_id']))
            ->when(! empty($filters['event_category_id']), fn (Builder $query): Builder => $query->where('event_category_id', (int) $filters['event_category_id']))
            ->when(! empty($filters['class_id']), fn (Builder $query): Builder => $query->where('class_id', (int) $filters['class_id']))
            ->when(! empty($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(! empty($filters['registration_type']), fn (Builder $query): Builder => $query->where('registration_type', $filters['registration_type']))
            ->latest()
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100))
            ->withQueryString()
            ->through(fn (EventRegistration $registration): array => $this->registrationPayload($registration));
    }

    public function rows(Request $request, SchoolEvent $event): array
    {
        abort_unless($request->user()?->hasPermission('activities.event_registrations.view'), 403);

        return $this->access->scopeRegistrations($request->user(), EventRegistration::query())
            ->with(['event:id,title', 'category:id,name', 'team.members.student:id,student_code,full_name', 'schoolClass:id,name', 'student:id,student_code,full_name', 'registeredBy:id,name', 'approvedBy:id,name'])
            ->where('event_id', $event->id)
            ->orderBy('event_category_id')
            ->orderBy('registration_type')
            ->orderBy('participant_name')
            ->get()
            ->map(fn (EventRegistration $registration): array => $this->registrationPayload($registration))
            ->values()
            ->all();
    }

    public function create(Request $request, SchoolEvent $event, array $data): EventRegistration
    {
        $this->access->assertCanRegister($request->user(), $event, $data);
        $this->assertModeAllowed($event, $data['registration_type'] ?? 'individual');

        return DB::transaction(function () use ($request, $event, $data): EventRegistration {
            $data = $this->normalizeData($request, $event, $data);
            $status = $this->access->isManager($request->user()) ? 'approved' : 'pending';
            $team = null;

            if ($data['registration_type'] === 'team') {
                $team = $this->createOrUpdateTeam($request, $event, $data, $status);
            }

            $registration = EventRegistration::create([
                ...Arr::only($data, ['event_category_id', 'student_id', 'class_id', 'registration_type', 'participant_name', 'note']),
                'event_id' => $event->id,
                'event_team_id' => $team?->id,
                'status' => $status,
                'registered_by' => $request->user()?->id,
                'approved_by' => $status === 'approved' ? $request->user()?->id : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'metadata' => ['source' => 'event_registration'],
            ]);

            if ($team) {
                $team->forceFill(['metadata' => ['event_registration_id' => $registration->id]])->save();
            }

            Auditor::record('event_registrations.created', $registration, null, $this->snapshot($registration->fresh()), $request);

            return $registration->fresh(['event', 'category', 'team.members.student', 'schoolClass', 'student', 'registeredBy', 'approvedBy']);
        });
    }

    public function approve(Request $request, EventRegistration $registration, ?string $note = null): EventRegistration
    {
        $this->access->assertCanReview($request->user(), $registration);

        return DB::transaction(function () use ($request, $registration, $note): EventRegistration {
            $before = $this->snapshot($registration);
            $registration->forceFill([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'note' => $note ?: $registration->note,
            ])->save();

            if ($registration->team) {
                $registration->team->forceFill([
                    'status' => 'approved',
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ])->save();
            }

            Auditor::record('event_registrations.approved', $registration, $before, $this->snapshot($registration->fresh()), $request, ['note' => $note]);

            return $registration->fresh(['event', 'category', 'team.members.student', 'schoolClass', 'student', 'registeredBy', 'approvedBy']);
        });
    }

    public function reject(Request $request, EventRegistration $registration, string $reason): EventRegistration
    {
        $this->access->assertCanReview($request->user(), $registration);

        return DB::transaction(function () use ($request, $registration, $reason): EventRegistration {
            $before = $this->snapshot($registration);
            $registration->forceFill([
                'status' => 'rejected',
                'rejected_by' => $request->user()?->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            if ($registration->team) {
                $registration->team->forceFill(['status' => 'rejected'])->save();
            }

            Auditor::record('event_registrations.rejected', $registration, $before, $this->snapshot($registration->fresh()), $request, ['reason' => $reason]);

            return $registration->fresh(['event', 'category', 'team.members.student', 'schoolClass', 'student', 'registeredBy', 'approvedBy']);
        });
    }

    public function cancel(Request $request, EventRegistration $registration, string $reason): EventRegistration
    {
        $user = $request->user();
        abort_unless($user && ($this->access->isManager($user) || (int) $registration->registered_by === (int) $user->id), 403);

        return DB::transaction(function () use ($request, $registration, $reason): EventRegistration {
            $before = $this->snapshot($registration);
            $registration->forceFill([
                'status' => 'cancelled',
                'rejection_reason' => $reason,
            ])->save();

            if ($registration->team) {
                $registration->team->forceFill(['status' => 'cancelled'])->save();
            }

            Auditor::record('event_registrations.cancelled', $registration, $before, $this->snapshot($registration->fresh()), $request, ['reason' => $reason]);

            return $registration->fresh(['event', 'category', 'team.members.student', 'schoolClass', 'student', 'registeredBy', 'approvedBy']);
        });
    }

    public function registrationPayload(EventRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'event_id' => $registration->event_id,
            'event_title' => $registration->event?->title,
            'event_category_id' => $registration->event_category_id,
            'category_name' => $registration->category?->name,
            'registration_type' => $registration->registration_type,
            'registration_type_label' => config('school.events.registration_modes.'.$registration->registration_type, $registration->registration_type),
            'event_team_id' => $registration->event_team_id,
            'team_name' => $registration->team?->name,
            'class_id' => $registration->class_id,
            'class_name' => $registration->schoolClass?->name,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student?->student_code,
            'student_name' => $registration->student?->full_name,
            'participant_name' => $registration->participant_name ?: $this->displayName($registration),
            'members' => $registration->team?->members->map(fn (EventTeamMember $member): array => [
                'id' => $member->student_id,
                'student_code' => $member->student?->student_code,
                'full_name' => $member->student?->full_name,
                'role' => $member->role,
            ])->values() ?? collect(),
            'status' => $registration->status,
            'status_label' => config('school.events.registration_statuses.'.$registration->status, $registration->status),
            'registered_by' => $registration->registeredBy?->name,
            'approved_by' => $registration->approvedBy?->name,
            'approved_at' => $registration->approved_at?->toDateTimeString(),
            'rejection_reason' => $registration->rejection_reason,
            'note' => $registration->note,
        ];
    }

    private function normalizeData(Request $request, SchoolEvent $event, array $data): array
    {
        $type = $data['registration_type'] ?? 'individual';
        $data['registration_type'] = $type;

        if ($request->user()?->hasRole('hoc_sinh') && $request->user()?->student) {
            $data['student_id'] = $request->user()->student->id;
        }

        if ($type === 'individual') {
            $student = Student::findOrFail((int) ($data['student_id'] ?? 0));
            $classId = (int) ($data['class_id'] ?? 0) ?: $this->access->classIdForStudent($student->id, (int) $event->semester_id);

            if (! $classId) {
                throw ValidationException::withMessages(['class_id' => 'Không tìm thấy lớp hiện tại của học sinh.']);
            }

            $data['class_id'] = $classId;
            $data['participant_name'] = $data['participant_name'] ?? $student->full_name;
            $data['member_ids'] = [$student->id];

            return $data;
        }

        if ($type === 'class') {
            if (empty($data['class_id'])) {
                throw ValidationException::withMessages(['class_id' => 'Cần chọn lớp đăng ký.']);
            }

            $data['student_id'] = null;
            $data['participant_name'] = $data['participant_name'] ?: 'Tập thể lớp';
            $data['member_ids'] = [];

            return $data;
        }

        if ($type === 'team') {
            $memberIds = collect($data['member_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->values();

            if ($memberIds->isEmpty()) {
                throw ValidationException::withMessages(['member_ids' => 'Đội cần ít nhất một học sinh.']);
            }

            $data['student_id'] = $data['student_id'] ?? $memberIds->first();
            $data['class_id'] = (int) ($data['class_id'] ?? 0) ?: $this->access->classIdForStudent((int) $memberIds->first(), (int) $event->semester_id);
            $data['participant_name'] = $data['participant_name'] ?: 'Đội thi';
            $data['member_ids'] = $memberIds->all();

            return $data;
        }

        throw ValidationException::withMessages(['registration_type' => 'Loại đăng ký không hợp lệ.']);
    }

    private function createOrUpdateTeam(Request $request, SchoolEvent $event, array $data, string $status): EventTeam
    {
        $team = EventTeam::create([
            'event_id' => $event->id,
            'event_category_id' => $data['event_category_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'captain_student_id' => $data['student_id'] ?? null,
            'name' => $data['participant_name'] ?: 'Đội thi',
            'status' => $status,
            'registered_by' => $request->user()?->id,
            'approved_by' => $status === 'approved' ? $request->user()?->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        foreach (array_values(array_unique(array_map('intval', $data['member_ids'] ?? []))) as $studentId) {
            if ($studentId <= 0) {
                continue;
            }

            EventTeamMember::create([
                'event_team_id' => $team->id,
                'student_id' => $studentId,
                'role' => $studentId === (int) ($data['student_id'] ?? 0) ? 'captain' : 'member',
            ]);
        }

        return $team;
    }

    private function assertModeAllowed(SchoolEvent $event, string $type): void
    {
        $modes = $event->registration_modes ?: ['individual', 'team', 'class'];

        abort_unless(in_array($type, $modes, true), 422, 'Sự kiện không cho phép loại đăng ký này.');
    }

    private function displayName(EventRegistration $registration): string
    {
        return match ($registration->registration_type) {
            'individual' => $registration->student?->full_name ?? 'Cá nhân',
            'class' => $registration->schoolClass?->name ? 'Tập thể '.$registration->schoolClass->name : 'Tập thể lớp',
            default => $registration->team?->name ?? $registration->participant_name ?? 'Đội thi',
        };
    }

    private function snapshot(EventRegistration $registration): array
    {
        return Arr::only($registration->fresh()?->getAttributes() ?? $registration->getAttributes(), [
            'id',
            'event_id',
            'event_category_id',
            'event_team_id',
            'registration_type',
            'class_id',
            'student_id',
            'participant_name',
            'status',
            'registered_by',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
            'note',
        ]);
    }
}
