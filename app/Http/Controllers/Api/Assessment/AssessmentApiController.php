<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\ScoreColumn;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Assessment\ScorebookService;
use App\Services\Assessment\ScoreExcelService;
use App\Services\Assessment\ScoreReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentApiController extends Controller
{
    public function __construct(
        private readonly ScorebookService $scorebooks,
        private readonly ScoreExcelService $excel,
        private readonly ScoreReportService $reports
    ) {
    }

    public function scorebooks(Request $request): JsonResponse
    {
        return $this->ok($this->scorebooks->scorebook($request, $request->all()));
    }

    public function scorebook(Request $request, SchoolClass $class, int $subject, int $semester): JsonResponse
    {
        return $this->ok($this->scorebooks->scorebook($request, [
            'school_year_id' => $class->school_year_id,
            'semester_id' => $semester,
            'class_id' => $class->id,
            'subject_id' => $subject,
        ]));
    }

    public function storeColumn(Request $request): JsonResponse
    {
        return $this->created($this->scorebooks->createColumn($request, $request->validate($this->columnRules())));
    }

    public function updateColumn(Request $request, ScoreColumn $column): JsonResponse
    {
        return $this->ok($this->scorebooks->updateColumn($request, $column, $request->validate($this->columnRules())), 'Da cap nhat cot diem.');
    }

    public function deleteColumn(Request $request, ScoreColumn $column): JsonResponse
    {
        $this->scorebooks->deleteColumn($request, $column);

        return $this->ok(null, 'Da xoa cot diem.');
    }

    public function saveScores(Request $request): JsonResponse
    {
        return $this->ok($this->scorebooks->bulkUpsert($request, $request->validate($this->bulkRules())), 'Da luu bang diem.');
    }

    public function lockColumn(Request $request, ScoreColumn $column): JsonResponse
    {
        return $this->ok($this->scorebooks->lockColumn($request, $column), 'Da khoa cot diem.');
    }

    public function requestUnlock(Request $request, ScoreColumn $column): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->scorebooks->requestUnlock($request, $column, $data['reason']), 'Da gui yeu cau mo khoa.');
    }

    public function approveUnlock(Request $request, ScoreColumn $column): JsonResponse
    {
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:1000']]);

        return $this->ok($this->scorebooks->approveUnlock($request, $column, $data['resolution_note'] ?? null), 'Da mo khoa cot diem.');
    }

    public function rejectUnlock(Request $request, ScoreColumn $column): JsonResponse
    {
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:1000']]);

        return $this->ok($this->scorebooks->rejectUnlock($request, $column, $data['resolution_note'] ?? null), 'Da tu choi mo khoa.');
    }

    public function importScores(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->filterRules(),
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'revision_reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->ok($this->excel->import($request, $data['file'], $this->onlyFilters($data), $data['revision_reason']), 'Import diem hoan tat.');
    }

    public function exportScores(Request $request, SchoolClass $class): BinaryFileResponse
    {
        $data = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $path = $this->excel->export($request, [
            'school_year_id' => (int) ($data['school_year_id'] ?? $class->school_year_id),
            'semester_id' => (int) $data['semester_id'],
            'class_id' => $class->id,
            'subject_id' => (int) $data['subject_id'],
        ]);

        return response()->download($path, 'bang-diem-lop.xlsx')->deleteFileAfterSend(true);
    }

    public function studentScores(Request $request, Student $student): JsonResponse
    {
        return $this->ok($this->scorebooks->studentScores($request, $student, $request->all()));
    }

    public function revisions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('assessment.student_scores.view'), 403);

        return $this->ok($this->scorebooks->revisions($request, $request->all()));
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('assessment.student_scores.view'), 403);

        $filters = $request->only(['school_year_id', 'semester_id', 'class_id', 'subject_id', 'threshold', 'from_semester_id', 'to_semester_id']);

        return $this->ok([
            'overview' => $this->reports->buildOverview($request->user(), $filters),
            'low_score_students' => $this->reports->lowScoreStudents($request->user(), $filters),
            'improved_students' => $this->reports->improvedStudents($request->user(), $filters),
        ]);
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $data]);
    }

    private function created(mixed $data): JsonResponse
    {
        return response()->json(['message' => 'Da tao moi.', 'data' => $data], 201);
    }

    private function filterRules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ];
    }

    private function bulkRules(): array
    {
        return [
            ...$this->filterRules(),
            'revision_reason' => ['nullable', 'string', 'max:1000'],
            'scores' => ['array'],
            'scores.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'scores.*.score_column_id' => ['required', 'integer', 'exists:score_columns,id'],
            'scores.*.score' => ['nullable'],
            'scores.*.comment' => ['nullable', 'string'],
            'scores.*.status' => ['nullable', 'string'],
            'scores.*.note' => ['nullable', 'string'],
        ];
    }

    private function columnRules(): array
    {
        return [
            ...$this->filterRules(),
            'score_type_id' => ['required', 'integer', 'exists:score_types,id'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'order_index' => ['required', 'integer', 'min:1'],
            'max_score' => ['required', 'numeric', 'min:0'],
            'lock_status' => ['nullable', 'string', 'in:open,locked,unlock_requested'],
            'status' => ['nullable', 'string'],
        ];
    }

    private function onlyFilters(array $data): array
    {
        return [
            'school_year_id' => (int) $data['school_year_id'],
            'semester_id' => (int) $data['semester_id'],
            'class_id' => (int) $data['class_id'],
            'subject_id' => (int) $data['subject_id'],
        ];
    }
}
