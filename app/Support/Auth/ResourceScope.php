<?php

namespace App\Support\Auth;

use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResourceScope
{
    public function scope(Request $request, string $resource, Builder $query): Builder
    {
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalScope($user)) {
            return $query;
        }

        if ($user->hasRole('giao_vien_bo_mon')) {
            $this->scopeSubjectTeacher($user, $resource, $query);
        }

        if ($user->hasRole('gvcn')) {
            $this->scopeHomeroomTeacher($user, $resource, $query);
        }

        if ($user->hasRole('phu_huynh')) {
            $this->scopeStudentContext($this->guardianStudentIds($user), $resource, $query, ['all', 'guardians']);
        }

        if ($user->hasRole('hoc_sinh')) {
            $this->scopeStudentContext($this->ownStudentIds($user), $resource, $query, ['all', 'students']);
        }

        return $query;
    }

    public function assertCanWrite(Request $request, string $resource, array $data): void
    {
        $user = $request->user();

        if (! $user || $this->hasGlobalScope($user)) {
            return;
        }

        if ($user->hasRole('giao_vien_bo_mon') && $resource === 'student_scores') {
            $allowed = TeachingAssignment::query()
                ->where('teacher_id', $user->staff?->id)
                ->where('status', 'active')
                ->where('subject_id', $data['subject_id'] ?? null)
                ->whereExists(function ($query) use ($data): void {
                    $query->selectRaw('1')
                        ->from('student_class_enrollments')
                        ->whereColumn('student_class_enrollments.class_id', 'teaching_assignments.class_id')
                        ->where('student_class_enrollments.student_id', $data['student_id'] ?? null)
                        ->where('student_class_enrollments.status', 'active');
                })
                ->exists();

            abort_unless($allowed, 403, 'Giáo viên bộ môn chỉ được nhập điểm lớp-môn được phân công.');
        }

        if ($user->hasRole('gvcn') && in_array($resource, ['conduct_scores', 'student_scores', 'student_class_enrollments'], true)) {
            $studentIds = $this->homeroomStudentIds($user);
            $classIds = $this->homeroomClassIds($user);
            $studentId = (int) ($data['student_id'] ?? 0);
            $classId = (int) ($data['class_id'] ?? 0);

            $allowed = ($studentId > 0 && $studentIds->contains($studentId))
                || ($classId > 0 && $classIds->contains($classId));

            abort_unless($allowed, 403, 'GVCN chỉ được quản lý dữ liệu lớp chủ nhiệm.');
        }
    }

    private function hasGlobalScope(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('bgh') || $user->hasRole('giao_vu');
    }

    private function scopeSubjectTeacher(User $user, string $resource, Builder $query): void
    {
        $assignments = $this->teachingAssignments($user);
        $classIds = $assignments->pluck('class_id')->unique()->values();
        $subjectIds = $assignments->pluck('subject_id')->unique()->values();

        match ($resource) {
            'classes' => $query->whereIn('id', $classIds),
            'subjects' => $query->whereIn('id', $subjectIds),
            'teaching_assignments' => $query->where('teacher_id', $user->staff?->id),
            'student_scores' => $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('teaching_assignments')
                    ->join('student_class_enrollments', 'student_class_enrollments.class_id', '=', 'teaching_assignments.class_id')
                    ->whereColumn('student_class_enrollments.student_id', 'student_scores.student_id')
                    ->whereColumn('teaching_assignments.subject_id', 'student_scores.subject_id')
                    ->where('student_class_enrollments.status', 'active')
                    ->where('teaching_assignments.status', 'active')
                    ->where('teaching_assignments.teacher_id', $user->staff?->id);
            }),
            default => null,
        };
    }

    private function scopeHomeroomTeacher(User $user, string $resource, Builder $query): void
    {
        $classIds = $this->homeroomClassIds($user);
        $studentIds = $this->studentIdsForClasses($classIds);

        match ($resource) {
            'classes' => $query->whereIn('id', $classIds),
            'students' => $query->whereIn('id', $studentIds),
            'student_scores', 'conduct_scores', 'student_fees' => $query->whereIn('student_id', $studentIds),
            'student_class_enrollments', 'teaching_assignments' => $query->whereIn('class_id', $classIds),
            'guardians' => $query->whereExists(function ($subQuery) use ($studentIds): void {
                $subQuery->selectRaw('1')
                    ->from('student_guardians')
                    ->whereColumn('student_guardians.guardian_id', 'guardians.id')
                    ->whereIn('student_guardians.student_id', $studentIds);
            }),
            'payments' => $query->whereExists(function ($subQuery) use ($studentIds): void {
                $subQuery->selectRaw('1')
                    ->from('student_fees')
                    ->whereColumn('student_fees.id', 'payments.student_fee_id')
                    ->whereIn('student_fees.student_id', $studentIds);
            }),
            default => null,
        };
    }

    private function scopeStudentContext(Collection $studentIds, string $resource, Builder $query, array $audiences): void
    {
        match ($resource) {
            'students' => $query->whereIn('id', $studentIds),
            'student_scores', 'conduct_scores', 'student_fees' => $query->whereIn('student_id', $studentIds),
            'student_class_enrollments' => $query->whereIn('student_id', $studentIds),
            'payments' => $query->whereExists(function ($subQuery) use ($studentIds): void {
                $subQuery->selectRaw('1')
                    ->from('student_fees')
                    ->whereColumn('student_fees.id', 'payments.student_fee_id')
                    ->whereIn('student_fees.student_id', $studentIds);
            }),
            'announcements' => $query->where('status', 'published')->whereIn('audience', $audiences),
            default => null,
        };
    }

    private function teachingAssignments(User $user): Collection
    {
        if (! $user->staff) {
            return collect();
        }

        return TeachingAssignment::query()
            ->where('teacher_id', $user->staff->id)
            ->where('status', 'active')
            ->get(['class_id', 'subject_id']);
    }

    private function homeroomClassIds(User $user): Collection
    {
        if (! $user->staff) {
            return collect();
        }

        return SchoolClass::query()
            ->where('homeroom_teacher_id', $user->staff->id)
            ->pluck('id')
            ->values();
    }

    private function homeroomStudentIds(User $user): Collection
    {
        return $this->studentIdsForClasses($this->homeroomClassIds($user));
    }

    private function guardianStudentIds(User $user): Collection
    {
        return $user->guardian
            ? $user->guardian->students()->pluck('students.id')->values()
            : collect();
    }

    private function ownStudentIds(User $user): Collection
    {
        return $user->student ? collect([$user->student->id]) : collect();
    }

    private function studentIdsForClasses(Collection $classIds): Collection
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
}
