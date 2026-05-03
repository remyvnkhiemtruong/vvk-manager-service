<?php

namespace App\Http\Controllers\Assessment;

use App\Http\Controllers\Controller;
use App\Models\ScoreColumn;
use App\Models\Student;
use App\Services\Assessment\ScorebookService;
use App\Services\Assessment\ScoreExcelService;
use App\Services\Assessment\ScoreReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentPageController extends Controller
{
    public function __construct(
        private readonly ScorebookService $scorebooks,
        private readonly ScoreExcelService $excel,
        private readonly ScoreReportService $reports
    ) {
    }

    public function entry(Request $request): Response
    {
        $this->assertCanViewScores($request);
        $filters = $this->filters($request);

        return Inertia::render('Assessment/Entry', [
            'lookups' => $this->scorebooks->lookups($request),
            'scorebook' => $this->scorebooks->scorebook($request, $filters),
        ]);
    }

    public function classScores(Request $request): Response
    {
        $this->assertCanViewScores($request);
        $filters = $this->filters($request);

        return Inertia::render('Assessment/ClassScores', [
            'lookups' => $this->scorebooks->lookups($request),
            'scorebook' => $this->scorebooks->scorebook($request, $filters),
        ]);
    }

    public function student(Request $request, Student $student): Response
    {
        return Inertia::render('Assessment/StudentShow', [
            'lookups' => $this->scorebooks->lookups($request),
            'detail' => $this->scorebooks->studentScores($request, $student, $request->only(['school_year_id', 'semester_id', 'subject_id'])),
            'filters' => $request->only(['school_year_id', 'semester_id', 'subject_id']),
        ]);
    }

    public function revisions(Request $request): Response
    {
        $this->assertCanViewScores($request);

        return Inertia::render('Assessment/Revisions', [
            'lookups' => $this->scorebooks->lookups($request),
            'revisions' => $this->scorebooks->revisions($request, $request->all()),
            'filters' => $request->only(['student_id', 'class_id', 'subject_id', 'semester_id']),
        ]);
    }

    public function scoreColumns(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('assessment.score_columns.view'), 403);

        return Inertia::render('Assessment/ScoreColumns', [
            'lookups' => $this->scorebooks->lookups($request),
            'scorebook' => $this->scorebooks->scorebook($request, $this->filters($request)),
            'filters' => $this->filters($request),
        ]);
    }

    public function reports(Request $request): Response
    {
        $this->assertCanViewScores($request);
        $filters = $request->only(['school_year_id', 'semester_id', 'class_id', 'subject_id', 'threshold', 'from_semester_id', 'to_semester_id']);

        return Inertia::render('Assessment/Reports', [
            'lookups' => $this->scorebooks->lookups($request),
            'filters' => $filters,
            'overview' => $this->reports->buildOverview($request->user(), $filters),
            'lowScoreStudents' => $this->reports->lowScoreStudents($request->user(), $filters),
            'improvedStudents' => $this->reports->improvedStudents($request->user(), $filters),
        ]);
    }

    public function saveScores(Request $request): RedirectResponse
    {
        $data = $request->validate($this->bulkRules());
        $result = $this->scorebooks->bulkUpsert($request, $data);

        return back()->with('success', "Da luu bang diem: {$result['created']} tao moi, {$result['updated']} cap nhat.");
    }

    public function storeColumn(Request $request): RedirectResponse
    {
        $this->scorebooks->createColumn($request, $request->validate($this->columnRules()));

        return back()->with('success', 'Da tao cot diem.');
    }

    public function updateColumn(Request $request, ScoreColumn $column): RedirectResponse
    {
        $this->scorebooks->updateColumn($request, $column, $request->validate($this->columnRules()));

        return back()->with('success', 'Da cap nhat cot diem.');
    }

    public function deleteColumn(Request $request, ScoreColumn $column): RedirectResponse
    {
        $this->scorebooks->deleteColumn($request, $column);

        return back()->with('success', 'Da xoa cot diem.');
    }

    public function lockColumn(Request $request, ScoreColumn $column): RedirectResponse
    {
        $this->scorebooks->lockColumn($request, $column);

        return back()->with('success', 'Da khoa cot diem.');
    }

    public function requestUnlock(Request $request, ScoreColumn $column): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->scorebooks->requestUnlock($request, $column, $data['reason']);

        return back()->with('success', 'Da gui yeu cau mo khoa.');
    }

    public function approveUnlock(Request $request, ScoreColumn $column): RedirectResponse
    {
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:1000']]);
        $this->scorebooks->approveUnlock($request, $column, $data['resolution_note'] ?? null);

        return back()->with('success', 'Da mo khoa cot diem.');
    }

    public function rejectUnlock(Request $request, ScoreColumn $column): RedirectResponse
    {
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:1000']]);
        $this->scorebooks->rejectUnlock($request, $column, $data['resolution_note'] ?? null);

        return back()->with('success', 'Da tu choi mo khoa.');
    }

    public function importScores(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->filterRules(),
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'revision_reason' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->excel->import($request, $data['file'], $this->onlyFilters($data), $data['revision_reason']);

        return back()->with('success', "Import diem hoan tat: {$result['created']} tao moi, {$result['updated']} cap nhat, ".count($result['skipped']).' bo qua.');
    }

    public function exportScores(Request $request): BinaryFileResponse
    {
        $data = $request->validate($this->filterRules());
        $path = $this->excel->export($request, $this->onlyFilters($data));

        return response()->download($path, 'bang-diem-lop.xlsx')->deleteFileAfterSend(true);
    }

    private function filters(Request $request): array
    {
        return $this->scorebooks->defaultFilters($request, $request->only(['school_year_id', 'semester_id', 'class_id', 'subject_id']));
    }

    private function assertCanViewScores(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('assessment.student_scores.view'), 403);
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
