<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignFile;
use App\Models\CampaignParticipant;
use App\Models\CampaignResult;
use App\Services\Campaigns\CampaignExportService;
use App\Services\Campaigns\CampaignRegistrationService;
use App\Services\Campaigns\CampaignRankingService;
use App\Services\Campaigns\CampaignScoringService;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignPageController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignRegistrationService $registrations,
        private readonly CampaignScoringService $scoring,
        private readonly CampaignRankingService $rankings,
        private readonly CampaignExportService $exports
    ) {
    }

    public function dashboard(Request $request): Response
    {
        return Inertia::render('Campaigns/Dashboard', [
            'dashboard' => $this->campaigns->dashboard($request),
        ]);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Campaigns/Index', [
            'lookups' => $this->campaigns->lookups($request),
            'campaigns' => $this->campaigns->campaigns($request, $request->all()),
            'filters' => $request->only(['school_year_id', 'semester_id', 'campaign_type', 'status', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.create'), 403);

        return Inertia::render('Campaigns/Form', [
            'lookups' => $this->campaigns->lookups($request),
            'campaign' => null,
        ]);
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.update'), 403);
        $campaign = $this->campaigns->findForView($request, $campaign);

        return Inertia::render('Campaigns/Form', [
            'lookups' => $this->campaigns->lookups($request),
            'campaign' => $this->campaigns->campaignPayload($campaign),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($request, $request->validate($this->campaignRules()), $request->file('plan_file'));

        return redirect()->route('campaigns.edit', $campaign)->with('success', 'Đã tạo phong trào.');
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->update($request, $campaign, $request->validate($this->campaignRules()), $request->file('plan_file'));

        return back()->with('success', 'Đã cập nhật phong trào.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->delete($request, $campaign);

        return redirect()->route('campaigns.index')->with('success', 'Đã xóa phong trào.');
    }

    public function register(Request $request, Campaign $campaign): Response
    {
        $campaign = $this->campaigns->findForView($request, $campaign);

        return Inertia::render('Campaigns/Register', [
            'lookups' => $this->campaigns->lookups($request),
            'campaign' => $this->campaigns->campaignPayload($campaign),
            'registrations' => $this->registrations->registrations($request, $campaign, $request->all()),
        ]);
    }

    public function storeRegistration(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->registrations->create($request, $campaign, $request->validate($this->registrationRules()));

        return back()->with('success', 'Đã gửi đăng ký tham gia.');
    }

    public function approvals(Request $request): Response
    {
        return Inertia::render('Campaigns/Approvals', [
            'lookups' => [
                ...$this->campaigns->lookups($request),
                'campaigns' => $this->campaigns->campaigns($request, ['per_page' => 100])->items(),
            ],
            'registrations' => $this->registrations->registrations($request, null, $request->all()),
            'filters' => $request->only(['campaign_id', 'class_id', 'participant_type', 'status']),
        ]);
    }

    public function approve(Request $request, CampaignParticipant $participant): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->registrations->approve($request, $participant, $data['note'] ?? null);

        return back()->with('success', 'Đã duyệt đăng ký.');
    }

    public function reject(Request $request, CampaignParticipant $participant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->registrations->reject($request, $participant, $data['reason']);

        return back()->with('success', 'Đã từ chối đăng ký.');
    }

    public function cancel(Request $request, CampaignParticipant $participant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->registrations->cancel($request, $participant, $data['reason']);

        return back()->with('success', 'Đã hủy đăng ký.');
    }

    public function results(Request $request, Campaign $campaign): Response
    {
        $campaign = $this->campaigns->findForView($request, $campaign);

        return Inertia::render('Campaigns/Results', [
            'campaign' => $this->campaigns->campaignPayload($campaign),
            'criteria' => $this->scoring->criteria($request, $campaign),
            'participants' => $this->registrations->rows($request, $campaign),
            'results' => $this->scoring->results($request, $campaign),
        ]);
    }

    public function saveCriteria(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->scoring->saveCriteria($request, $campaign, $request->validate($this->criteriaRules())['criteria']);

        return back()->with('success', 'Đã lưu tiêu chí chấm điểm.');
    }

    public function storeResult(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->scoring->saveResult($request, $campaign, $request->validate($this->resultRules()), $request->file('evidences', []));

        return back()->with('success', 'Đã lưu kết quả phong trào.');
    }

    public function rankings(Request $request, Campaign $campaign): Response
    {
        $campaign = $this->campaigns->findForView($request, $campaign);

        return Inertia::render('Campaigns/Rankings', [
            'campaign' => $this->campaigns->campaignPayload($campaign),
            'ranking' => $this->rankings->rankings($request, $campaign),
        ]);
    }

    public function summary(Request $request, Campaign $campaign): Response
    {
        $campaign = $this->campaigns->findForView($request, $campaign);

        return Inertia::render('Campaigns/Summary', [
            'campaign' => $this->campaigns->campaignPayload($campaign),
            'ranking' => $this->rankings->rankings($request, $campaign),
            'registrations' => $this->registrations->rows($request, $campaign),
        ]);
    }

    public function summarize(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate(['summary_report' => ['nullable', 'string', 'max:10000']]);
        $result = $this->scoring->summarize($request, $campaign, $data['summary_report'] ?? null);

        return back()->with('success', "Đã tổng kết phong trào, cộng {$result['conduct']} điểm rèn luyện và {$result['class']} điểm thi đua lớp.");
    }

    public function uploadFile(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'file_type' => ['required', 'string', 'in:plan'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);
        $this->campaigns->storeFile($request, $campaign, $data['file'], $data['file_type']);

        return back()->with('success', 'Đã tải file kế hoạch.');
    }

    public function file(Request $request, Campaign $campaign, CampaignFile $file): StreamedResponse
    {
        return $this->campaigns->downloadFile($request, $campaign, $file);
    }

    public function uploadEvidence(Request $request, CampaignResult $result): RedirectResponse
    {
        $data = $request->validate([
            'evidences' => ['required', 'array'],
            'evidences.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);
        $this->scoring->uploadEvidence($request, $result, $data['evidences']);

        return back()->with('success', 'Đã tải minh chứng.');
    }

    public function evidence(Request $request, CampaignResult $result, CampaignFile $file): StreamedResponse
    {
        return $this->scoring->downloadEvidence($request, $result, $file);
    }

    public function export(Request $request, Campaign $campaign, string $kind): BinaryFileResponse
    {
        abort_unless(in_array($kind, ['participants', 'results', 'rankings'], true), 404);
        $format = $request->query('format', 'xlsx');
        $rows = $kind === 'participants'
            ? $this->registrations->rows($request, $campaign)
            : $this->rankings->rankingRows($campaign);
        $path = $this->exports->export($campaign, $kind, $rows, (string) $format);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    private function campaignRules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'title' => ['required', 'string', 'max:255'],
            'campaign_type' => ['required', 'string', 'in:'.implode(',', array_keys(config('school.campaigns.types', [])))],
            'organizer_unit' => ['nullable', 'string', 'max:255'],
            'target_audience' => ['required', 'string', 'in:'.implode(',', array_keys(config('school.campaigns.target_audiences', [])))],
            'registration_modes' => ['array'],
            'registration_modes.*' => ['string', 'in:individual,team,class'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'summary_report' => ['nullable', 'string', 'max:10000'],
            'conduct_points_per_student' => ['nullable', 'integer', 'min:0', 'max:100'],
            'class_competition_points' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(config('school.campaigns.statuses', [])))],
            'plan_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function campaignRulesForApi(): array
    {
        return $this->campaignRules();
    }

    private function registrationRules(): array
    {
        return [
            'participant_type' => ['required', 'string', 'in:individual,team,class'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'exists:students,id'],
            'participant_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function registrationRulesForApi(): array
    {
        return $this->registrationRules();
    }

    private function criteriaRules(): array
    {
        return [
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.id' => ['nullable', 'integer', 'exists:campaign_criteria,id'],
            'criteria.*.code' => ['nullable', 'string', 'max:64'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string', 'max:1000'],
            'criteria.*.max_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'criteria.*.order_index' => ['nullable', 'integer', 'min:1'],
            'criteria.*.status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    public function criteriaRulesForApi(): array
    {
        return $this->criteriaRules();
    }

    private function resultRules(): array
    {
        return [
            'campaign_participant_id' => ['required', 'integer', 'exists:campaign_participants,id'],
            'scores' => ['array'],
            'scores.*.campaign_criterion_id' => ['required', 'integer', 'exists:campaign_criteria,id'],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
            'scores.*.note' => ['nullable', 'string', 'max:1000'],
            'award_title' => ['nullable', 'string', 'max:255'],
            'conduct_points' => ['nullable', 'integer', 'min:0', 'max:100'],
            'class_points' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:draft,published,final'],
            'evidences' => ['array'],
            'evidences.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function resultRulesForApi(): array
    {
        return $this->resultRules();
    }
}
