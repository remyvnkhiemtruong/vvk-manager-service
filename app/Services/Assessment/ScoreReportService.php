<?php

namespace App\Services\Assessment;

use App\Models\Semester;
use App\Models\ScoreEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ScoreReportService
{
    public function __construct(private readonly ScoreFormulaService $formula)
    {
    }

    public function buildOverview(User $user, array $filters): array
    {
        $base = $this->baseScoreQuery($user, $filters);
        $averageSql = $this->formula->weightedAverageSql();

        return [
            'averageBySubject' => (clone $base)
                ->select('subjects.id as subject_id', 'subjects.name as subject_name', DB::raw($averageSql.' as average_score'))
                ->groupBy('subjects.id', 'subjects.name')
                ->orderBy('subjects.name')
                ->get(),
            'averageByClass' => (clone $base)
                ->select('score_classes.id as class_id', 'score_classes.name as class_name', DB::raw($averageSql.' as average_score'))
                ->leftJoin('classes as score_classes', 'score_classes.id', '=', 'student_scores.class_id')
                ->groupBy('score_classes.id', 'score_classes.name')
                ->orderBy('score_classes.name')
                ->get(),
        ];
    }

    public function lowScoreStudents(User $user, array $filters): LengthAwarePaginator
    {
        return $this->studentAverageQuery($user, $filters)
            ->where('average_score', '<', (float) ($filters['threshold'] ?? 5))
            ->orderBy('average_score')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function improvedStudents(User $user, array $filters): LengthAwarePaginator
    {
        [$fromSemester, $toSemester] = $this->comparisonSemesters($filters);

        return $this->studentAverageQuery($user, $filters, $toSemester)
            ->leftJoinSub($this->studentAverageQuery($user, $filters, $fromSemester), 'from_avg', 'from_avg.student_id', '=', 'student_avg.student_id')
            ->selectRaw('student_avg.*, COALESCE(ROUND(student_avg.average_score - from_avg.average_score, 2), 0) as delta_score')
            ->whereRaw('COALESCE(student_avg.average_score - from_avg.average_score, 0) > 0')
            ->orderByDesc('delta_score')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    private function studentAverageQuery(User $user, array $filters, ?int $forceSemesterId = null)
    {
        $averageSql = $this->formula->weightedAverageSql();
        $query = $this->baseScoreQuery($user, $filters, $forceSemesterId)
            ->join('students', 'students.id', '=', 'student_scores.student_id')
            ->leftJoin('classes as score_classes', 'score_classes.id', '=', 'student_scores.class_id')
            ->select(
                'students.id as student_id',
                'students.student_code',
                'students.full_name as student_name',
                'score_classes.name as class_name',
                DB::raw($averageSql.' as average_score')
            )
            ->groupBy('students.id', 'students.student_code', 'students.full_name', 'score_classes.name');

        return DB::query()->fromSub($query, 'student_avg');
    }

    private function baseScoreQuery(User $user, array $filters, ?int $forceSemesterId = null)
    {
        $query = ScoreEntry::query()
            ->join('score_types', 'score_types.id', '=', 'student_scores.score_type_id')
            ->join('subjects', 'subjects.id', '=', 'student_scores.subject_id')
            ->whereNull('student_scores.deleted_at')
            ->whereNotNull('student_scores.score')
            ->where('score_types.counts_toward_average', true)
            ->where('score_types.input_type', 'numeric')
            ->where('subjects.assessment_mode', config('school.assessment.average.numeric_subject_mode', 'numeric'));

        if (! empty($filters['school_year_id'])) $query->where('student_scores.school_year_id', (int) $filters['school_year_id']);
        $semesterId = $forceSemesterId ?: ($filters['semester_id'] ?? null);
        if (! empty($semesterId)) $query->where('student_scores.semester_id', (int) $semesterId);
        if (! empty($filters['class_id'])) $query->where('student_scores.class_id', (int) $filters['class_id']);
        if (! empty($filters['subject_id'])) $query->where('student_scores.subject_id', (int) $filters['subject_id']);

        $this->applyRoleScope($query, $user);

        return $query->toBase();
    }

    private function applyRoleScope($query, User $user): void
    {
        if ($user->hasRole('admin') || $user->hasRole('bgh') || $user->hasRole('giao_vu')) return;
        if ($user->hasRole('hoc_sinh') && $user->student) {
            $query->where('student_scores.student_id', $user->student->id);
            return;
        }
        if ($user->hasRole('phu_huynh') && $user->guardian) {
            $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('student_guardians')
                    ->whereColumn('student_guardians.student_id', 'student_scores.student_id')
                    ->where('student_guardians.guardian_id', $user->guardian->id);
            });
            return;
        }
        if (! $user->staff) return;
        if ($user->hasRole('giao_vien_bo_mon')) {
            $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('teaching_assignments')
                    ->whereColumn('teaching_assignments.class_id', 'student_scores.class_id')
                    ->whereColumn('teaching_assignments.subject_id', 'student_scores.subject_id')
                    ->whereColumn('teaching_assignments.semester_id', 'student_scores.semester_id')
                    ->where('teaching_assignments.status', 'active')
                    ->where('teaching_assignments.teacher_id', $user->staff->id);
            });
            return;
        }
        if ($user->hasRole('gvcn')) {
            $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('classes')
                    ->whereColumn('classes.id', 'student_scores.class_id')
                    ->where('classes.homeroom_teacher_id', $user->staff->id);
            });
        }
    }

    private function comparisonSemesters(array $filters): array
    {
        $toSemester = (int) ($filters['to_semester_id'] ?? $filters['semester_id'] ?? 0);

        if (! $toSemester) {
            $toSemester = (int) (Semester::query()->where('is_active', true)->value('id') ?? Semester::query()->latest('id')->value('id'));
        }

        $fromSemester = (int) ($filters['from_semester_id'] ?? 0);

        if (! $fromSemester && $toSemester) {
            $to = Semester::find($toSemester);
            $fromSemester = (int) (Semester::query()
                ->where('school_year_id', $to?->school_year_id)
                ->where('term_number', '<', $to?->term_number ?? 0)
                ->orderByDesc('term_number')
                ->value('id') ?? 0);
        }

        return [$fromSemester, $toSemester];
    }
}
