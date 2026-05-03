<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventAccess
{
    public function isManager(?User $user): bool
    {
        return (bool) ($user?->hasRole('admin') || $user?->hasRole('bgh') || $user?->hasRole('doan_truong'));
    }

    public function scopeEvents(?User $user, Builder $query): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isManager($user) || $user->hasRole('gvcn')) {
            return $query;
        }

        if ($user->hasRole('hoc_sinh') || $user->hasRole('phu_huynh')) {
            $studentIds = $this->studentIdsForUser($user);

            return $query
                ->whereIn('status', ['registration_open', 'in_progress', 'ended', 'summarized'])
                ->where(function (Builder $builder) use ($studentIds): void {
                    $builder->whereDoesntHave('registrations')
                        ->orWhereHas('registrations', fn (Builder $registration): Builder => $registration->whereIn('student_id', $studentIds))
                        ->orWhereHas('teams.members', fn (Builder $member): Builder => $member->whereIn('student_id', $studentIds));
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeRegistrations(?User $user, Builder $query): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isManager($user)) {
            return $query;
        }

        if ($user->hasRole('gvcn')) {
            $classIds = $this->homeroomClassIds($user);
            $studentIds = $this->studentIdsForClasses($classIds);

            return $query->where(function (Builder $builder) use ($classIds, $studentIds): void {
                $builder->whereIn('class_id', $classIds)
                    ->orWhereIn('student_id', $studentIds)
                    ->orWhereHas('team.members', fn (Builder $member): Builder => $member->whereIn('student_id', $studentIds));
            });
        }

        if ($user->hasRole('hoc_sinh') || $user->hasRole('phu_huynh')) {
            $studentIds = $this->studentIdsForUser($user);

            return $query->where(function (Builder $builder) use ($studentIds): void {
                $builder->whereIn('student_id', $studentIds)
                    ->orWhereHas('team.members', fn (Builder $member): Builder => $member->whereIn('student_id', $studentIds));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function classQueryFor(?User $user): Builder
    {
        $query = SchoolClass::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isManager($user)) {
            return $query;
        }

        if ($user->hasRole('gvcn')) {
            return $query->whereIn('id', $this->homeroomClassIds($user));
        }

        return $query->whereRaw('1 = 0');
    }

    public function studentQueryFor(?User $user): Builder
    {
        $query = Student::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isManager($user)) {
            return $query;
        }

        if ($user->hasRole('gvcn')) {
            return $query->whereIn('id', $this->studentIdsForClasses($this->homeroomClassIds($user)));
        }

        if ($user->hasRole('hoc_sinh') || $user->hasRole('phu_huynh')) {
            return $query->whereIn('id', $this->studentIdsForUser($user));
        }

        return $query->whereRaw('1 = 0');
    }

    public function assertCanManage(?User $user): void
    {
        abort_unless($this->isManager($user), 403, 'Không có quyền quản lý hội thi/hội thao.');
    }

    public function assertCanViewEvent(?User $user, SchoolEvent $event): void
    {
        abort_unless($user?->hasPermission('activities.events.view'), 403);
        abort_unless($this->scopeEvents($user, SchoolEvent::query())->whereKey($event->id)->exists(), 403);
    }

    public function assertCanRegister(?User $user, SchoolEvent $event, array $data): void
    {
        abort_unless($user?->hasPermission('activities.event_registrations.create'), 403);
        abort_unless($event->status === 'registration_open', 422, 'Sự kiện chưa mở đăng ký.');

        if ($this->isManager($user)) {
            return;
        }

        $type = $data['registration_type'] ?? 'individual';

        if ($user?->hasRole('gvcn')) {
            $classIds = $this->homeroomClassIds($user);

            if ($type === 'class') {
                abort_unless($classIds->contains((int) ($data['class_id'] ?? 0)), 403, 'GVCN chỉ đăng ký lớp chủ nhiệm.');

                return;
            }

            $targetStudentIds = collect($data['member_ids'] ?? [])->push((int) ($data['student_id'] ?? 0))->filter()->values();
            abort_unless($targetStudentIds->isNotEmpty() && $targetStudentIds->diff($this->studentIdsForClasses($classIds))->isEmpty(), 403, 'GVCN chỉ đăng ký học sinh lớp chủ nhiệm.');

            return;
        }

        if ($user?->hasRole('hoc_sinh')) {
            abort_unless($type !== 'class', 403, 'Học sinh không được đăng ký tập thể lớp.');
            $targetStudentIds = collect($data['member_ids'] ?? [])->push((int) ($data['student_id'] ?? 0))->filter()->values();
            abort_unless($targetStudentIds->isNotEmpty() && $targetStudentIds->diff($this->studentIdsForUser($user))->isEmpty(), 403, 'Học sinh chỉ được đăng ký cho chính mình.');

            return;
        }

        abort(403);
    }

    public function assertCanReview(?User $user, EventRegistration $registration): void
    {
        abort_unless($user?->hasPermission('activities.event_registrations.update'), 403);

        if ($this->isManager($user)) {
            return;
        }

        if ($user?->hasRole('gvcn')) {
            $classIds = $this->homeroomClassIds($user);
            $studentIds = $this->studentIdsForClasses($classIds);
            $allowed = $classIds->contains((int) $registration->class_id)
                || $studentIds->contains((int) $registration->student_id)
                || $registration->team?->members()->whereIn('student_id', $studentIds)->exists();

            abort_unless($allowed, 403, 'GVCN chỉ duyệt đăng ký thuộc lớp chủ nhiệm.');

            return;
        }

        abort(403);
    }

    public function studentIdsForUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($user->student) {
            return collect([$user->student->id]);
        }

        if ($user->guardian) {
            return $user->guardian->students()->pluck('students.id')->values();
        }

        return collect();
    }

    public function homeroomClassIds(User $user): Collection
    {
        if (! $user->staff) {
            return collect();
        }

        return SchoolClass::query()->where('homeroom_teacher_id', $user->staff->id)->pluck('id')->values();
    }

    public function studentIdsForClasses(Collection $classIds): Collection
    {
        if ($classIds->isEmpty()) {
            return collect();
        }

        return DB::table('student_class_enrollments')
            ->whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    public function classIdForStudent(int $studentId, int $semesterId): ?int
    {
        $query = DB::table('student_class_enrollments')
            ->where('student_id', $studentId)
            ->where('status', 'active');

        if ($semesterId > 0) {
            $query->where('semester_id', $semesterId);
        }

        return ($classId = $query->latest('semester_id')->value('class_id')) ? (int) $classId : null;
    }
}
