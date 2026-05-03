<?php

namespace App\Http\Controllers\Conduct;

use App\Http\Controllers\Controller;
use App\Models\ConductEvidence;
use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductScore;
use App\Models\Student;
use App\Services\Conduct\ConductRecordService;
use App\Services\Conduct\ConductReportService;
use App\Services\Conduct\ConductScoreService;
use App\Support\Audit\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConductPageController extends Controller
{
    public function __construct(
        private readonly ConductScoreService $scores,
        private readonly ConductRecordService $records,
        private readonly ConductReportService $reports
    ) {
    }

    public function rules(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.view'), 403);

        return Inertia::render('Conduct/Rules', [
            'rules' => ConductRule::query()->orderBy('sort_order')->orderBy('code')->paginate(20)->withQueryString(),
            'ratingRules' => $this->scores->lookups($request)['ratingRules'],
            'conductConfig' => config('school.conduct'),
        ]);
    }

    public function records(Request $request): Response
    {
        return Inertia::render('Conduct/Records', [
            'lookups' => $this->scores->lookups($request),
            'records' => $this->records->records($request, $request->all()),
            'filters' => $request->only(['school_year_id', 'semester_id', 'class_id', 'student_id', 'status', 'rule_type']),
        ]);
    }

    public function approvals(Request $request): Response
    {
        return Inertia::render('Conduct/Approvals', [
            'lookups' => $this->scores->lookups($request),
            'records' => $this->records->pendingApprovals($request, $request->all()),
            'filters' => $request->only(['school_year_id', 'semester_id', 'class_id', 'student_id']),
        ]);
    }

    public function classes(Request $request): Response
    {
        $filters = $this->scores->defaultFilters($request, $request->only(['school_year_id', 'semester_id', 'class_id', 'student_id']));

        return Inertia::render('Conduct/ClassScores', [
            'lookups' => $this->scores->lookups($request),
            'summary' => $this->scores->summaries($request, $filters),
        ]);
    }

    public function student(Request $request, Student $student): Response
    {
        return Inertia::render('Conduct/StudentTimeline', [
            'lookups' => $this->scores->lookups($request),
            'detail' => $this->scores->timeline($request, $student, $request->only(['semester_id'])),
            'filters' => $request->only(['semester_id']),
        ]);
    }

    public function comments(Request $request): Response
    {
        $filters = $this->scores->defaultFilters($request, $request->only(['school_year_id', 'semester_id', 'class_id', 'student_id']));

        return Inertia::render('Conduct/Comments', [
            'lookups' => $this->scores->lookups($request),
            'summary' => $this->scores->summaries($request, $filters),
        ]);
    }

    public function locks(Request $request): Response
    {
        $filters = $this->scores->defaultFilters($request, $request->only(['school_year_id', 'semester_id', 'class_id', 'student_id']));

        return Inertia::render('Conduct/Locks', [
            'lookups' => $this->scores->lookups($request),
            'summary' => $this->scores->summaries($request, $filters),
        ]);
    }

    public function reports(Request $request): Response
    {
        return Inertia::render('Conduct/Reports', [
            'lookups' => $this->scores->lookups($request),
            'filters' => $request->only(['school_year_id', 'semester_id', 'class_id', 'threshold']),
            'overview' => $this->reports->overview($request, $request->all()),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.create'), 403);
        $data = $request->validate($this->ruleRules());
        $data['sort_order'] = $data['sort_order'] ?? 1;
        $rule = ConductRule::create($data);
        Auditor::record('conduct_rules.created', $rule, null, $rule->fresh()->getAttributes(), $request);

        return back()->with('success', 'Đã tạo tiêu chí rèn luyện.');
    }

    public function updateRule(Request $request, ConductRule $rule): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.update'), 403);
        $before = $rule->getAttributes();
        $data = $request->validate($this->ruleRules());
        $data['sort_order'] = $data['sort_order'] ?? 1;
        $rule->fill($data)->save();
        Auditor::record('conduct_rules.updated', $rule, $before, $rule->fresh()->getAttributes(), $request);

        return back()->with('success', 'Đã cập nhật tiêu chí.');
    }

    public function deleteRule(Request $request, ConductRule $rule): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.delete'), 403);
        $before = $rule->getAttributes();
        $rule->delete();
        Auditor::record('conduct_rules.deleted', $rule, $before, null, $request);

        return back()->with('success', 'Đã ngưng dùng tiêu chí.');
    }

    public function storeRecord(Request $request): RedirectResponse
    {
        $data = $request->validate($this->recordRules());
        $this->records->create($request, $data, $request->file('evidences', []));

        return back()->with('success', 'Đã ghi nhận sự kiện rèn luyện.');
    }

    public function updateRecord(Request $request, ConductRecord $record): RedirectResponse
    {
        $this->records->update($request, $record, $request->validate([
            'points' => ['required', 'integer'],
            'recorded_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Đã cập nhật sự kiện.');
    }

    public function approveRecord(Request $request, ConductRecord $record): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->records->approve($request, $record, $data['note'] ?? null);

        return back()->with('success', 'Đã duyệt sự kiện rèn luyện.');
    }

    public function rejectRecord(Request $request, ConductRecord $record): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->records->reject($request, $record, $data['reason']);

        return back()->with('success', 'Đã từ chối sự kiện.');
    }

    public function cancelRecord(Request $request, ConductRecord $record): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->records->cancel($request, $record, $data['reason']);

        return back()->with('success', 'Đã hủy sự kiện.');
    }

    public function recompute(Request $request): RedirectResponse
    {
        $result = $this->scores->recomputeForFilters($request, $request->validate($this->filterRules()));

        return back()->with('success', "Đã tính lại {$result['updated']} học sinh.");
    }

    public function adjust(Request $request, ConductScore $summary): RedirectResponse
    {
        $data = $request->validate([
            'points_delta' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->scores->adjust($request, $summary, (int) $data['points_delta'], $data['reason']);

        return back()->with('success', 'Đã điều chỉnh điểm rèn luyện.');
    }

    public function comment(Request $request, ConductScore $summary): RedirectResponse
    {
        $data = $request->validate(['homeroom_comment' => ['required', 'string', 'max:2000']]);
        $this->scores->comment($request, $summary, $data['homeroom_comment']);

        return back()->with('success', 'Đã lưu nhận xét cuối kỳ.');
    }

    public function lock(Request $request, ConductScore $summary): RedirectResponse
    {
        $this->scores->lock($request, $summary);

        return back()->with('success', 'Đã khóa điểm rèn luyện.');
    }

    public function unlock(Request $request, ConductScore $summary): RedirectResponse
    {
        $this->scores->unlock($request, $summary);

        return back()->with('success', 'Đã mở khóa điểm rèn luyện.');
    }

    public function evidence(Request $request, ConductRecord $record, ConductEvidence $evidence): StreamedResponse
    {
        return $this->records->downloadEvidence($request, $record, $evidence);
    }

    private function filterRules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
        ];
    }

    private function recordRules(): array
    {
        return [
            ...$this->filterRules(),
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'conduct_rule_id' => ['required', 'integer', 'exists:conduct_rules,id'],
            'points' => ['nullable', 'integer'],
            'recorded_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'evidences' => ['array'],
            'evidences.*' => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
        ];
    }

    private function ruleRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'rule_type' => ['required', 'string', 'in:bonus,deduction'],
            'points' => ['required', 'integer'],
            'severity' => ['required', 'string', 'in:minor,normal,major,serious'],
            'requires_approval' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
