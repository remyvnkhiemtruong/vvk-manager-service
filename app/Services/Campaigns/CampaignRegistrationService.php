<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\CampaignParticipantMember;
use App\Models\Student;
use App\Support\Audit\Auditor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignRegistrationService
{
    public function __construct(private readonly CampaignAccess $access)
    {
    }

    public function registrations(Request $request, ?Campaign $campaign = null, array $filters = []): LengthAwarePaginator
    {
        abort_unless($request->user()?->hasPermission('activities.campaign_participants.view'), 403);

        return $this->access->scopeParticipants($request->user(), CampaignParticipant::query())
            ->with([
                'campaign:id,title,status,campaign_type,semester_id',
                'schoolClass:id,name',
                'student:id,student_code,full_name',
                'members.student:id,student_code,full_name',
                'registeredBy:id,name',
                'approvedBy:id,name',
            ])
            ->when($campaign, fn (Builder $query): Builder => $query->where('campaign_id', $campaign->id))
            ->when(! empty($filters['campaign_id']), fn (Builder $query): Builder => $query->where('campaign_id', (int) $filters['campaign_id']))
            ->when(! empty($filters['class_id']), fn (Builder $query): Builder => $query->where('class_id', (int) $filters['class_id']))
            ->when(! empty($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(! empty($filters['participant_type']), fn (Builder $query): Builder => $query->where('participant_type', $filters['participant_type']))
            ->latest()
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100))
            ->withQueryString()
            ->through(fn (CampaignParticipant $participant): array => $this->participantPayload($participant));
    }

    public function rows(Request $request, Campaign $campaign): array
    {
        abort_unless($request->user()?->hasPermission('activities.campaign_participants.view'), 403);

        return $this->access->scopeParticipants($request->user(), CampaignParticipant::query())
            ->with(['campaign:id,title', 'schoolClass:id,name', 'student:id,student_code,full_name', 'members.student:id,student_code,full_name', 'registeredBy:id,name', 'approvedBy:id,name'])
            ->where('campaign_id', $campaign->id)
            ->orderBy('participant_type')
            ->orderBy('participant_name')
            ->get()
            ->map(fn (CampaignParticipant $participant): array => $this->participantPayload($participant))
            ->values()
            ->all();
    }

    public function create(Request $request, Campaign $campaign, array $data): CampaignParticipant
    {
        $this->access->assertCanRegister($request->user(), $campaign, $data);
        $this->assertModeAllowed($campaign, $data['participant_type'] ?? 'individual');

        return DB::transaction(function () use ($request, $campaign, $data): CampaignParticipant {
            $data = $this->normalizeData($request, $campaign, $data);
            $status = $this->access->isManager($request->user()) ? 'approved' : 'pending';
            $participant = CampaignParticipant::create([
                ...Arr::only($data, ['participant_type', 'class_id', 'student_id', 'participant_name', 'note']),
                'campaign_id' => $campaign->id,
                'status' => $status,
                'registered_by' => $request->user()?->id,
                'approved_by' => $status === 'approved' ? $request->user()?->id : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'metadata' => ['source' => 'campaign_registration'],
            ]);

            $this->syncMembers($participant, $data['member_ids'] ?? []);
            Auditor::record('campaign_participants.created', $participant, null, $this->snapshot($participant->fresh()), $request);

            return $participant->fresh(['campaign', 'schoolClass', 'student', 'members.student', 'registeredBy', 'approvedBy']);
        });
    }

    public function approve(Request $request, CampaignParticipant $participant, ?string $note = null): CampaignParticipant
    {
        $this->access->assertCanReview($request->user(), $participant);

        return DB::transaction(function () use ($request, $participant, $note): CampaignParticipant {
            $before = $this->snapshot($participant);
            $participant->forceFill([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'note' => $note ?: $participant->note,
            ])->save();
            Auditor::record('campaign_participants.approved', $participant, $before, $this->snapshot($participant->fresh()), $request, ['note' => $note]);

            return $participant->fresh(['campaign', 'schoolClass', 'student', 'members.student', 'registeredBy', 'approvedBy']);
        });
    }

    public function reject(Request $request, CampaignParticipant $participant, string $reason): CampaignParticipant
    {
        $this->access->assertCanReview($request->user(), $participant);

        return DB::transaction(function () use ($request, $participant, $reason): CampaignParticipant {
            $before = $this->snapshot($participant);
            $participant->forceFill([
                'status' => 'rejected',
                'rejected_by' => $request->user()?->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();
            Auditor::record('campaign_participants.rejected', $participant, $before, $this->snapshot($participant->fresh()), $request, ['reason' => $reason]);

            return $participant->fresh(['campaign', 'schoolClass', 'student', 'members.student', 'registeredBy', 'approvedBy']);
        });
    }

    public function cancel(Request $request, CampaignParticipant $participant, string $reason): CampaignParticipant
    {
        $user = $request->user();
        abort_unless($user && ($this->access->isManager($user) || (int) $participant->registered_by === (int) $user->id), 403);

        return DB::transaction(function () use ($request, $participant, $reason): CampaignParticipant {
            $before = $this->snapshot($participant);
            $participant->forceFill([
                'status' => 'cancelled',
                'rejection_reason' => $reason,
            ])->save();
            Auditor::record('campaign_participants.cancelled', $participant, $before, $this->snapshot($participant->fresh()), $request, ['reason' => $reason]);

            return $participant->fresh(['campaign', 'schoolClass', 'student', 'members.student', 'registeredBy', 'approvedBy']);
        });
    }

    public function participantPayload(CampaignParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'campaign_id' => $participant->campaign_id,
            'campaign_title' => $participant->campaign?->title,
            'participant_type' => $participant->participant_type,
            'participant_type_label' => config('school.campaigns.registration_modes.'.$participant->participant_type, $participant->participant_type),
            'class_id' => $participant->class_id,
            'class_name' => $participant->schoolClass?->name,
            'student_id' => $participant->student_id,
            'student_code' => $participant->student?->student_code,
            'student_name' => $participant->student?->full_name,
            'participant_name' => $participant->participant_name ?: $this->displayName($participant),
            'members' => $participant->members->map(fn (CampaignParticipantMember $member): array => [
                'id' => $member->student_id,
                'student_code' => $member->student?->student_code,
                'full_name' => $member->student?->full_name,
                'role' => $member->role,
            ])->values(),
            'status' => $participant->status,
            'status_label' => config('school.campaigns.registration_statuses.'.$participant->status, $participant->status),
            'registered_by' => $participant->registeredBy?->name,
            'approved_by' => $participant->approvedBy?->name,
            'approved_at' => $participant->approved_at?->toDateTimeString(),
            'rejection_reason' => $participant->rejection_reason,
            'note' => $participant->note,
        ];
    }

    private function normalizeData(Request $request, Campaign $campaign, array $data): array
    {
        $type = $data['participant_type'] ?? 'individual';
        $data['participant_type'] = $type;

        if ($request->user()?->hasRole('hoc_sinh') && $request->user()?->student) {
            $data['student_id'] = $request->user()->student->id;
        }

        if ($type === 'individual') {
            $studentId = (int) ($data['student_id'] ?? 0);
            $student = Student::findOrFail($studentId);
            $classId = (int) ($data['class_id'] ?? 0) ?: $this->access->classIdForStudent($studentId, (int) $campaign->semester_id);

            if (! $classId) {
                throw ValidationException::withMessages(['class_id' => 'Không tìm thấy lớp hiện tại của học sinh.']);
            }

            $data['class_id'] = $classId;
            $data['participant_name'] = $data['participant_name'] ?? $student->full_name;
            $data['member_ids'] = [$studentId];

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
                throw ValidationException::withMessages(['member_ids' => 'Đội/nhóm cần ít nhất một học sinh.']);
            }

            $data['student_id'] = $data['student_id'] ?? $memberIds->first();
            $data['class_id'] = (int) ($data['class_id'] ?? 0) ?: $this->access->classIdForStudent((int) $memberIds->first(), (int) $campaign->semester_id);
            $data['participant_name'] = $data['participant_name'] ?: 'Đội/nhóm phong trào';
            $data['member_ids'] = $memberIds->all();

            return $data;
        }

        throw ValidationException::withMessages(['participant_type' => 'Loại đăng ký không hợp lệ.']);
    }

    private function syncMembers(CampaignParticipant $participant, array $memberIds): void
    {
        CampaignParticipantMember::query()
            ->where('campaign_participant_id', $participant->id)
            ->delete();

        foreach (array_values(array_unique(array_map('intval', $memberIds))) as $studentId) {
            if ($studentId <= 0) {
                continue;
            }

            CampaignParticipantMember::create([
                'campaign_participant_id' => $participant->id,
                'student_id' => $studentId,
                'status' => 'active',
            ]);
        }
    }

    private function assertModeAllowed(Campaign $campaign, string $type): void
    {
        $modes = $campaign->registration_modes ?: ['individual', 'team', 'class'];

        abort_unless(in_array($type, $modes, true), 422, 'Hoạt động không cho phép loại đăng ký này.');
    }

    private function displayName(CampaignParticipant $participant): string
    {
        return match ($participant->participant_type) {
            'individual' => $participant->student?->full_name ?? 'Cá nhân',
            'class' => $participant->schoolClass?->name ? 'Tập thể '.$participant->schoolClass->name : 'Tập thể lớp',
            default => $participant->participant_name ?? 'Đội/nhóm',
        };
    }

    private function snapshot(CampaignParticipant $participant): array
    {
        return Arr::only($participant->fresh()?->getAttributes() ?? $participant->getAttributes(), [
            'id',
            'campaign_id',
            'participant_type',
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
