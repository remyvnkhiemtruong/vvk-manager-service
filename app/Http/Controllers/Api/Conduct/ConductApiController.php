<?php

namespace App\Http\Controllers\Api\Conduct;

use App\Http\Controllers\Controller;
use App\Models\ConductEvidence;
use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductScore;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Conduct\ConductRecordService;
use App\Services\Conduct\ConductReportService;
use App\Services\Conduct\ConductScoreService;
use App\Support\Audit\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConductApiController extends Controller
{
    public function __construct(
        private readonly ConductScoreService $scores,
        private readonly ConductRecordService $records,
        private readonly ConductReportService $reports
    ) {
    }

    public function rules(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.view'), 403);

        return $this->ok(ConductRule::query()->orderBy('sort_order')->orderBy('code')->paginate((int) ($request->integer('per_page') ?: 20)));
    }

    public function storeRule(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.create'), 403);
        $data = $request->validate($this->ruleRules());
        $data['sort_order'] = $data['sort_order'] ?? 1;
        $rule = ConductRule::create($data);
        Auditor::record('conduct_rules.created', $rule, null, $rule->fresh()->getAttributes(), $request);

        return $this->created($rule->fresh());
    }

    public function updateRule(Request $request, ConductRule $rule): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.update'), 403);
        $before = $rule->getAttributes();
        $data = $request->validate($this->ruleRules());
        $data['sort_order'] = $data['sort_order'] ?? 1;
        $rule->fill($data)->save();
        Auditor::record('conduct_rules.updated', $rule, $before, $rule->fresh()->getAttributes(), $request);

        return $this->ok($rule->fresh(), 'Đã cập nhật tiêu chí.');
    }

    public function deleteRule(Request $request, ConductRule $rule): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_rules.delete'), 403);
        $before = $rule->getAttributes();
        $rule->delete();
        Auditor::record('conduct_rules.deleted', $rule, $before, null, $request);

        return $this->ok(null, 'Đã xóa tiêu chí.');
    }

    public function records(Request $request): JsonResponse
    {
        return $this->ok($this->records->records($request, $request->all()));
    }

    public function storeRecord(Request $request): JsonResponse
    {
        $record = $this->records->create($request, $request->validate($this->recordRules()), $request->file('evidences', []));

        return $this->created($this->records->recordPayload($record));
    }

    public function updateRecord(Request $request, ConductRecord $record): JsonResponse
    {
        return $this->ok($this->records->recordPayload($this->records->update($request, $record, $request->validate([
            'points' => ['required', 'integer'],
            'recorded_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]))), 'Đã cập nhật sự kiện.');
    }

    public function approve(Request $request, ConductRecord $record): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->ok($this->records->recordPayload($this->records->approve($request, $record, $data['note'] ?? null)), 'Đã duyệt sự kiện.');
    }

    public function reject(Request $request, ConductRecord $record): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->records->recordPayload($this->records->reject($request, $record, $data['reason'])), 'Đã từ chối sự kiện.');
    }

    public function cancel(Request $request, ConductRecord $record): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->records->recordPayload($this->records->cancel($request, $record, $data['reason'])), 'Đã hủy sự kiện.');
    }

    public function summaries(Request $request): JsonResponse
    {
        return $this->ok($this->scores->summaries($request, $request->all()));
    }

    public function classSummaries(Request $request, SchoolClass $class): JsonResponse
    {
        return $this->ok($this->scores->summaries($request, [
            ...$request->all(),
            'school_year_id' => $request->integer('school_year_id') ?: $class->school_year_id,
            'class_id' => $class->id,
        ]));
    }

    public function recompute(Request $request): JsonResponse
    {
        return $this->ok($this->scores->recomputeForFilters($request, $request->validate($this->filterRules())), 'Đã tính lại điểm rèn luyện.');
    }

    public function adjust(Request $request, ConductScore $summary): JsonResponse
    {
        $data = $request->validate(['points_delta' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->scores->adjust($request, $summary, (int) $data['points_delta'], $data['reason']), 'Đã điều chỉnh điểm.');
    }

    public function comment(Request $request, ConductScore $summary): JsonResponse
    {
        $data = $request->validate(['homeroom_comment' => ['required', 'string', 'max:2000']]);

        return $this->ok($this->scores->comment($request, $summary, $data['homeroom_comment']), 'Đã lưu nhận xét.');
    }

    public function lock(Request $request, ConductScore $summary): JsonResponse
    {
        return $this->ok($this->scores->lock($request, $summary), 'Đã khóa điểm rèn luyện.');
    }

    public function unlock(Request $request, ConductScore $summary): JsonResponse
    {
        return $this->ok($this->scores->unlock($request, $summary), 'Đã mở khóa điểm rèn luyện.');
    }

    public function timeline(Request $request, Student $student): JsonResponse
    {
        return $this->ok($this->scores->timeline($request, $student, $request->all()));
    }

    public function evidence(Request $request, ConductRecord $record, ConductEvidence $evidence): StreamedResponse
    {
        return $this->records->downloadEvidence($request, $record, $evidence);
    }

    public function reports(Request $request): JsonResponse
    {
        return $this->ok($this->reports->overview($request, $request->all()));
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $data]);
    }

    private function created(mixed $data): JsonResponse
    {
        return response()->json(['message' => 'Đã tạo mới.', 'data' => $data], 201);
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
