<?php

namespace App\Services\Conduct;

use App\Models\ClassEnrollment;
use App\Models\ConductRatingRule;
use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductRevision;
use App\Models\ConductScore;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Auth\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConductScoreService
{
    public function __construct(private readonly ResourceScope $scope)
    {
    }

    public function lookups(Request $request): array
    {
        return [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->get(['id', 'name', 'is_active']),
            'semesters' => Semester::query()->orderByDesc('school_year_id')->orderBy('term_number')->get(['id', 'school_year_id', 'name', 'term_number', 'is_active']),
            'classes' => $this->classQueryFor($request->user())->orderBy('name')->get(['id', 'school_year_id', 'grade_id', 'name', 'code']),
            'students' => $this->studentQueryFor($request->user())->orderBy('full_name')->get(['id', 'student_code', 'full_name']),
            'rules' => ConductRule::query()->where('status', 'active')->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name', 'rule_type', 'points', 'severity', 'requires_approval']),
            'ratingRules' => ConductRatingRule::query()->where('status', 'active')->orderByDesc('min_score')->get(['id', 'rating', 'min_score', 'max_score']),
        ];
    }

    public function defaultFilters(Request $request, array $filters = []): array
    {
        $lookups = $this->lookups($request);
        $activeYear = $lookups['schoolYears']->firstWhere('is_active', true) ?? $lookups['schoolYears']->first();
        $activeSemester = $lookups['semesters']->firstWhere('is_active', true) ?? $lookups['semesters']->first();

        return [
            'school_year_id' => (int) ($filters['school_year_id'] ?? $activeYear?->id ?? 0),
            'semester_id' => (int) ($filters['semester_id'] ?? $activeSemester?->id ?? 0),
            'class_id' => (int) ($filters['class_id'] ?? $lookups['classes']->first()?->id ?? 0),
            'student_id' => (int) ($filters['student_id'] ?? 0),
        ];
    }

    public function summaries(Request $request, array $filters): array
    {
        $this->assertCanViewSummaries($request);
        $filters = $this->defaultFilters($request, $filters);

        $query = $this->scope->scope($request, 'conduct_scores', ConductScore::query())
            ->with(['student:id,student_code,full_name', 'schoolClass:id,name', 'semester:id,name'])
            ->where('school_year_id', $filters['school_year_id'])
            ->where('semester_id', $filters['semester_id']);

        if ($filters['class_id']) {
            $query->where('class_id', $filters['class_id']);
        }

        if ($filters['student_id']) {
            $query->where('student_id', $filters['student_id']);
        }

        return [
            'filters' => $filters,
            'rows' => $query->orderBy('class_id')->orderBy('student_id')->get()->map(fn (ConductScore $summary): array => $this->summaryPayload($summary))->values(),
            'can' => $this->abilities($request, $filters),
        ];
    }

    public function recomputeForFilters(Request $request, array $filters): array
    {
        $this->assertCanManageClass($request, (int) ($filters['class_id'] ?? 0), 'conduct.recompute');

        $students = $this->studentsForClass((int) $filters['class_id'], (int) $filters['semester_id']);
        $updated = 0;

        DB::transaction(function () use ($students, $filters, &$updated): void {
            foreach ($students as $student) {
                $summary = $this->ensureSummary((int) $filters['school_year_id'], (int) $filters['semester_id'], (int) $filters['class_id'], $student->id);
                $this->recalculate($summary);
                $updated++;
            }
        });

        return ['updated' => $updated];
    }

    public function ensureSummary(int $schoolYearId, int $semesterId, int $classId, int $studentId): ConductScore
    {
        return ConductScore::query()->firstOrCreate(
            ['student_id' => $studentId, 'semester_id' => $semesterId],
            [
                'school_year_id' => $schoolYearId,
                'class_id' => $classId,
                'base_score' => (int) config('school.conduct.base_score', 100),
                'bonus_points' => 0,
                'minus_points' => 0,
                'adjustment_points' => 0,
                'score' => (int) config('school.conduct.base_score', 100),
                'rating' => $this->ratingFor((int) config('school.conduct.base_score', 100)),
                'status' => 'draft',
                'lock_status' => 'open',
            ]
        );
    }

    public function recalculate(ConductScore $summary): ConductScore
    {
        $records = ConductRecord::query()
            ->with('rule:id,rule_type')
            ->where('student_id', $summary->student_id)
            ->where('semester_id', $summary->semester_id)
            ->where('status', 'approved')
            ->get();

        $bonus = 0;
        $minus = 0;

        foreach ($records as $record) {
            $points = abs((int) $record->points);
            $isBonus = ($record->rule?->rule_type ?? null) === 'bonus' || (int) $record->points > 0;

            if ($isBonus) {
                $bonus += $points;
            } else {
                $minus += $points;
            }
        }

        $base = (int) ($summary->base_score ?: config('school.conduct.base_score', 100));
        $adjustment = (int) $summary->adjustment_points;
        $score = $this->clamp($base + $bonus - $minus + $adjustment);

        $summary->forceFill([
            'bonus_points' => $bonus,
            'minus_points' => $minus,
            'score' => $score,
            'rating' => $this->ratingFor($score),
            'last_recalculated_at' => now(),
        ])->save();

        return $summary->fresh(['student', 'schoolClass', 'semester']);
    }

    public function adjust(Request $request, ConductScore $summary, int $pointsDelta, string $reason): ConductScore
    {
        $this->assertCanAdjust($request, $summary);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Bắt buộc nhập lý do điều chỉnh điểm rèn luyện.']);
        }

        return DB::transaction(function () use ($request, $summary, $pointsDelta, $reason): ConductScore {
            $before = $this->summarySnapshot($summary);

            $summary->forceFill([
                'adjustment_points' => (int) $summary->adjustment_points + $pointsDelta,
            ])->save();
            $summary = $this->recalculate($summary);

            $after = $this->summarySnapshot($summary);
            ConductRevision::create([
                'conduct_score_summary_id' => $summary->id,
                'before_values' => $before,
                'after_values' => $after,
                'points_delta' => $pointsDelta,
                'action' => 'manual_adjustment',
                'changed_by' => $request->user()?->id,
                'reason' => $reason,
            ]);

            Auditor::record('conduct_scores.adjusted', $summary, $before, $after, $request, ['reason' => $reason]);

            return $summary;
        });
    }

    public function comment(Request $request, ConductScore $summary, string $comment): ConductScore
    {
        $this->assertCanManageClass($request, (int) $summary->class_id, 'conduct.comment');

        return DB::transaction(function () use ($request, $summary, $comment): ConductScore {
            $before = $this->summarySnapshot($summary);
            $summary->forceFill([
                'homeroom_comment' => $comment,
                'commented_by' => $request->user()?->id,
                'commented_at' => now(),
            ])->save();
            Auditor::record('conduct_scores.commented', $summary, $before, $this->summarySnapshot($summary->fresh()), $request);

            return $summary->fresh(['student', 'schoolClass', 'semester']);
        });
    }

    public function lock(Request $request, ConductScore $summary): ConductScore
    {
        $this->assertCanManageClass($request, (int) $summary->class_id, 'conduct.lock');

        return DB::transaction(function () use ($request, $summary): ConductScore {
            $before = $this->summarySnapshot($summary);
            $summary->forceFill([
                'lock_status' => 'locked',
                'locked_by' => $request->user()?->id,
                'locked_at' => now(),
            ])->save();
            Auditor::record('conduct_scores.locked', $summary, $before, $this->summarySnapshot($summary->fresh()), $request);

            return $summary->fresh(['student', 'schoolClass', 'semester']);
        });
    }

    public function unlock(Request $request, ConductScore $summary): ConductScore
    {
        $user = $request->user();
        abort_unless($user?->hasRole('admin') || $user?->hasRole('bgh'), 403, 'Chỉ Admin/BGH được mở khóa điểm rèn luyện.');

        return DB::transaction(function () use ($request, $summary): ConductScore {
            $before = $this->summarySnapshot($summary);
            $summary->forceFill([
                'lock_status' => 'open',
                'unlocked_by' => $request->user()?->id,
                'unlocked_at' => now(),
            ])->save();
            Auditor::record('conduct_scores.unlocked', $summary, $before, $this->summarySnapshot($summary->fresh()), $request);

            return $summary->fresh(['student', 'schoolClass', 'semester']);
        });
    }

    public function timeline(Request $request, Student $student, array $filters = []): array
    {
        $this->assertCanViewStudent($request, $student);

        $records = $this->scope->scope($request, 'conduct_records', ConductRecord::query())
            ->with(['rule:id,code,name,rule_type,severity', 'recordedBy:id,name', 'approvedBy:id,name', 'evidences'])
            ->where('student_id', $student->id)
            ->when(! empty($filters['semester_id']), fn (Builder $query): Builder => $query->where('semester_id', (int) $filters['semester_id']))
            ->latest('recorded_date')
            ->latest('id')
            ->get()
            ->map(fn (ConductRecord $record): array => $this->recordTimelinePayload($record));

        $summaries = $this->scope->scope($request, 'conduct_scores', ConductScore::query())
            ->where('student_id', $student->id)
            ->with(['semester:id,name', 'schoolClass:id,name'])
            ->orderByDesc('semester_id')
            ->get()
            ->map(fn (ConductScore $summary): array => $this->summaryPayload($summary));

        return [
            'student' => Arr::only($student->toArray(), ['id', 'student_code', 'full_name']),
            'summaries' => $summaries,
            'records' => $records,
        ];
    }

    public function classQueryFor(?User $user): Builder
    {
        $query = SchoolClass::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalScope($user) || $user->hasRole('giam_thi') || $user->hasRole('doan_truong')) {
            return $query;
        }

        if ($user->hasRole('gvcn') && $user->staff) {
            return $query->where('homeroom_teacher_id', $user->staff->id);
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff) {
            return $query->whereIn('id', TeachingAssignment::query()
                ->where('teacher_id', $user->staff->id)
                ->where('status', 'active')
                ->select('class_id'));
        }

        return $query->whereRaw('1 = 0');
    }

    public function studentQueryFor(?User $user): Builder
    {
        $query = Student::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalScope($user) || $user->hasRole('giam_thi') || $user->hasRole('doan_truong')) {
            return $query;
        }

        if ($user->hasRole('gvcn') && $user->staff) {
            return $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->join('classes', 'classes.id', '=', 'student_class_enrollments.class_id')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.status', 'active')
                    ->where('classes.homeroom_teacher_id', $user->staff->id);
            });
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff) {
            return $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->join('teaching_assignments', 'teaching_assignments.class_id', '=', 'student_class_enrollments.class_id')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->whereColumn('teaching_assignments.semester_id', 'student_class_enrollments.semester_id')
                    ->where('student_class_enrollments.status', 'active')
                    ->where('teaching_assignments.status', 'active')
                    ->where('teaching_assignments.teacher_id', $user->staff->id);
            });
        }

        if ($user->hasRole('phu_huynh') && $user->guardian) {
            return $query->whereIn('id', $user->guardian->students()->pluck('students.id'));
        }

        if ($user->hasRole('hoc_sinh') && $user->student) {
            return $query->whereKey($user->student->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function assertCanViewStudent(Request $request, Student $student): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($this->hasGlobalScope($user)) {
            return;
        }

        if ($user->student?->id === $student->id) {
            return;
        }

        if ($user->guardian && $user->guardian->students()->where('students.id', $student->id)->exists()) {
            return;
        }

        if ($this->studentQueryFor($user)->whereKey($student->id)->exists()) {
            return;
        }

        abort(403);
    }

    public function studentsForClass(int $classId, int $semesterId): Collection
    {
        return Student::query()
            ->whereExists(function ($query) use ($classId, $semesterId): void {
                $query->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.class_id', $classId)
                    ->where('student_class_enrollments.semester_id', $semesterId)
                    ->where('student_class_enrollments.status', 'active');
            })
            ->orderBy('full_name')
            ->get(['id', 'student_code', 'full_name']);
    }

    private function assertCanViewSummaries(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_scores.view'), 403);
    }

    private function assertCanAdjust(Request $request, ConductScore $summary): void
    {
        $this->assertUnlockedForWrite($request, $summary);

        $user = $request->user();
        abort_unless($user?->hasRole('admin') || $user?->hasRole('bgh') || ($user?->hasRole('gvcn') && $this->isHomeroomClass($user, (int) $summary->class_id)), 403);
    }

    public function assertUnlockedForWrite(Request $request, ConductScore $summary): void
    {
        $user = $request->user();

        if ($summary->lock_status !== 'locked') {
            return;
        }

        abort_unless($user?->hasRole('admin') || $user?->hasRole('bgh'), 403, 'Điểm rèn luyện đã khóa.');
    }

    private function assertCanManageClass(Request $request, int $classId, string $action): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->hasRole('admin') || $user->hasRole('bgh')) {
            return;
        }

        if ($user->hasRole('gvcn') && $this->isHomeroomClass($user, $classId)) {
            return;
        }

        abort(403, 'Không có quyền thao tác với lớp này.');
    }

    private function isHomeroomClass(User $user, int $classId): bool
    {
        return $user->staff
            && SchoolClass::query()->whereKey($classId)->where('homeroom_teacher_id', $user->staff->id)->exists();
    }

    private function clamp(int $score): int
    {
        return max((int) config('school.conduct.min_score', 0), min((int) config('school.conduct.max_score', 100), $score));
    }

    private function ratingFor(int $score): ?string
    {
        $rule = ConductRatingRule::query()
            ->where('status', 'active')
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->orderByDesc('min_score')
            ->first();

        if ($rule) {
            return $rule->rating;
        }

        foreach (config('school.conduct.ratings', []) as $rating => $range) {
            if ($score >= (int) $range['min'] && $score <= (int) $range['max']) {
                return $rating;
            }
        }

        return null;
    }

    private function summarySnapshot(ConductScore $summary): array
    {
        return Arr::only($summary->fresh()?->getAttributes() ?? $summary->getAttributes(), [
            'id',
            'school_year_id',
            'semester_id',
            'class_id',
            'student_id',
            'base_score',
            'bonus_points',
            'minus_points',
            'adjustment_points',
            'score',
            'rating',
            'status',
            'lock_status',
            'homeroom_comment',
        ]);
    }

    private function summaryPayload(ConductScore $summary): array
    {
        return [
            'id' => $summary->id,
            'school_year_id' => $summary->school_year_id,
            'semester_id' => $summary->semester_id,
            'semester_name' => $summary->semester?->name,
            'class_id' => $summary->class_id,
            'class_name' => $summary->schoolClass?->name,
            'student_id' => $summary->student_id,
            'student_code' => $summary->student?->student_code,
            'student_name' => $summary->student?->full_name,
            'base_score' => $summary->base_score,
            'bonus_points' => $summary->bonus_points,
            'minus_points' => $summary->minus_points,
            'adjustment_points' => $summary->adjustment_points,
            'score' => $summary->score,
            'rating' => $summary->rating,
            'status' => $summary->status,
            'lock_status' => $summary->lock_status,
            'homeroom_comment' => $summary->homeroom_comment,
            'last_recalculated_at' => $summary->last_recalculated_at?->toDateTimeString(),
        ];
    }

    private function recordTimelinePayload(ConductRecord $record): array
    {
        return [
            'id' => $record->id,
            'recorded_date' => $record->recorded_date?->toDateString(),
            'rule_code' => $record->rule?->code,
            'rule_name' => $record->rule?->name,
            'rule_type' => $record->rule?->rule_type,
            'severity' => $record->rule?->severity,
            'points' => $record->points,
            'description' => $record->description ?: $record->note,
            'status' => $record->status,
            'recorded_by' => $record->recordedBy?->name,
            'approved_by' => $record->approvedBy?->name,
            'evidence_count' => $record->evidences->count(),
        ];
    }

    private function abilities(Request $request, array $filters): array
    {
        $user = $request->user();
        $classId = (int) ($filters['class_id'] ?? 0);

        return [
            'record' => (bool) $user?->hasPermission('conduct.conduct_records.create'),
            'approve' => (bool) ($user?->hasRole('admin') || $user?->hasRole('bgh') || ($user?->hasRole('gvcn') && $this->isHomeroomClass($user, $classId))),
            'lock' => (bool) ($user?->hasRole('admin') || $user?->hasRole('bgh') || ($user?->hasRole('gvcn') && $this->isHomeroomClass($user, $classId))),
            'unlock' => (bool) ($user?->hasRole('admin') || $user?->hasRole('bgh')),
            'adjust' => (bool) ($user?->hasRole('admin') || $user?->hasRole('bgh') || ($user?->hasRole('gvcn') && $this->isHomeroomClass($user, $classId))),
        ];
    }

    private function hasGlobalScope(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('bgh') || $user->hasRole('giao_vu');
    }
}
