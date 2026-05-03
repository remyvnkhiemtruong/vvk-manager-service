<?php

namespace App\Services\Conduct;

use App\Models\ConductRecord;
use App\Models\ConductScore;
use App\Models\User;
use App\Support\Auth\ResourceScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConductReportService
{
    public function __construct(private readonly ResourceScope $scope)
    {
    }

    public function overview(Request $request, array $filters): array
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_scores.view'), 403);

        return [
            'classTable' => $this->classTable($request, $filters),
            'topDeducted' => $this->topPoints($request, $filters, 'deduction'),
            'topAwarded' => $this->topPoints($request, $filters, 'bonus'),
            'commonViolations' => $this->commonViolations($request, $filters),
            'ratingDistribution' => $this->ratingDistribution($request, $filters),
            'lowRiskStudents' => $this->lowRiskStudents($request, $filters),
        ];
    }

    public function classTable(Request $request, array $filters)
    {
        return $this->summaryBase($request, $filters)
            ->with(['student:id,student_code,full_name', 'schoolClass:id,name'])
            ->orderBy('class_id')
            ->orderBy('score')
            ->limit(100)
            ->get()
            ->map(fn (ConductScore $summary): array => [
                'student_id' => $summary->student_id,
                'student_code' => $summary->student?->student_code,
                'student_name' => $summary->student?->full_name,
                'class_name' => $summary->schoolClass?->name,
                'score' => $summary->score,
                'rating' => $summary->rating,
                'bonus_points' => $summary->bonus_points,
                'minus_points' => $summary->minus_points,
                'adjustment_points' => $summary->adjustment_points,
            ])
            ->values();
    }

    public function topPoints(Request $request, array $filters, string $type): LengthAwarePaginator
    {
        $query = $this->recordBase($request, $filters)
            ->join('students', 'students.id', '=', 'conduct_records.student_id')
            ->leftJoin('classes', 'classes.id', '=', 'conduct_records.class_id')
            ->join('conduct_rules', 'conduct_rules.id', '=', 'conduct_records.conduct_rule_id')
            ->where('conduct_records.status', 'approved')
            ->where('conduct_rules.rule_type', $type)
            ->select(
                'students.id as student_id',
                'students.student_code',
                'students.full_name as student_name',
                'classes.name as class_name',
                DB::raw('SUM(ABS(conduct_records.points)) as total_points')
            )
            ->groupBy('students.id', 'students.student_code', 'students.full_name', 'classes.name')
            ->orderByDesc('total_points');

        return DB::query()
            ->fromSub($query->toBase(), 'conduct_point_report')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    public function commonViolations(Request $request, array $filters)
    {
        return $this->recordBase($request, $filters)
            ->join('conduct_rules', 'conduct_rules.id', '=', 'conduct_records.conduct_rule_id')
            ->where('conduct_records.status', 'approved')
            ->where('conduct_rules.rule_type', 'deduction')
            ->select('conduct_rules.code', 'conduct_rules.name', DB::raw('COUNT(*) as record_count'), DB::raw('SUM(ABS(conduct_records.points)) as total_minus'))
            ->groupBy('conduct_rules.code', 'conduct_rules.name')
            ->orderByDesc('record_count')
            ->limit(10)
            ->get();
    }

    public function ratingDistribution(Request $request, array $filters)
    {
        return $this->summaryBase($request, $filters)
            ->leftJoin('classes', 'classes.id', '=', 'conduct_score_summaries.class_id')
            ->leftJoin('grades', 'grades.id', '=', 'classes.grade_id')
            ->select('grades.level as grade_level', 'classes.name as class_name', 'conduct_score_summaries.rating', DB::raw('COUNT(*) as total'))
            ->groupBy('grades.level', 'classes.name', 'conduct_score_summaries.rating')
            ->orderBy('grades.level')
            ->orderBy('classes.name')
            ->get();
    }

    public function lowRiskStudents(Request $request, array $filters): LengthAwarePaginator
    {
        return $this->summaryBase($request, $filters)
            ->with(['student:id,student_code,full_name', 'schoolClass:id,name'])
            ->where('score', '<=', (int) ($filters['threshold'] ?? 60))
            ->orderBy('score')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (ConductScore $summary): array => [
                'student_id' => $summary->student_id,
                'student_code' => $summary->student?->student_code,
                'student_name' => $summary->student?->full_name,
                'class_name' => $summary->schoolClass?->name,
                'score' => $summary->score,
                'rating' => $summary->rating,
                'minus_points' => $summary->minus_points,
            ]);
    }

    private function summaryBase(Request $request, array $filters): Builder
    {
        $query = $this->scope->scope($request, 'conduct_scores', ConductScore::query())
            ->whereNull('conduct_score_summaries.deleted_at');

        if (! empty($filters['school_year_id'])) $query->where('conduct_score_summaries.school_year_id', (int) $filters['school_year_id']);
        if (! empty($filters['semester_id'])) $query->where('conduct_score_summaries.semester_id', (int) $filters['semester_id']);
        if (! empty($filters['class_id'])) $query->where('conduct_score_summaries.class_id', (int) $filters['class_id']);

        return $query;
    }

    private function recordBase(Request $request, array $filters): Builder
    {
        $query = $this->scope->scope($request, 'conduct_records', ConductRecord::query())
            ->whereNull('conduct_records.deleted_at');

        if (! empty($filters['school_year_id'])) $query->where('conduct_records.school_year_id', (int) $filters['school_year_id']);
        if (! empty($filters['semester_id'])) $query->where('conduct_records.semester_id', (int) $filters['semester_id']);
        if (! empty($filters['class_id'])) $query->where('conduct_records.class_id', (int) $filters['class_id']);

        return $query;
    }
}
