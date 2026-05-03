<?php

namespace App\Http\Controllers\Api\Campaigns;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Campaigns\CampaignPageController;
use App\Models\Campaign;
use App\Models\CampaignFile;
use App\Models\CampaignParticipant;
use App\Models\CampaignResult;
use App\Services\Campaigns\CampaignExportService;
use App\Services\Campaigns\CampaignRegistrationService;
use App\Services\Campaigns\CampaignRankingService;
use App\Services\Campaigns\CampaignScoringService;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignApiController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignRegistrationService $registrations,
        private readonly CampaignScoringService $scoring,
        private readonly CampaignRankingService $rankings,
        private readonly CampaignExportService $exports
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->campaigns->campaigns($request, $request->all()));
    }

    public function store(Request $request): JsonResponse
    {
        $campaign = $this->campaigns->create($request, $request->validate($this->campaignRules()), $request->file('plan_file'));

        return $this->created($this->campaigns->campaignPayload($campaign));
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        return $this->ok($this->campaigns->campaignPayload($this->campaigns->findForView($request, $campaign)));
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->campaigns->update($request, $campaign, $request->validate($this->campaignRules()), $request->file('plan_file'));

        return $this->ok($this->campaigns->campaignPayload($campaign), 'Đã cập nhật phong trào.');
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->campaigns->delete($request, $campaign);

        return $this->ok(null, 'Đã xóa phong trào.');
    }

    public function uploadFile(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'file_type' => ['required', 'string', 'in:plan'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        return $this->created($this->campaigns->storeFile($request, $campaign, $data['file'], $data['file_type']));
    }

    public function file(Request $request, Campaign $campaign, CampaignFile $file): StreamedResponse
    {
        return $this->campaigns->downloadFile($request, $campaign, $file);
    }

    public function registrations(Request $request, Campaign $campaign): JsonResponse
    {
        return $this->ok($this->registrations->registrations($request, $campaign, $request->all()));
    }

    public function storeRegistration(Request $request, Campaign $campaign): JsonResponse
    {
        $participant = $this->registrations->create($request, $campaign, $request->validate($this->registrationRules()));

        return $this->created($this->registrations->participantPayload($participant));
    }

    public function approve(Request $request, CampaignParticipant $participant): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->ok($this->registrations->participantPayload($this->registrations->approve($request, $participant, $data['note'] ?? null)), 'Đã duyệt đăng ký.');
    }

    public function reject(Request $request, CampaignParticipant $participant): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->registrations->participantPayload($this->registrations->reject($request, $participant, $data['reason'])), 'Đã từ chối đăng ký.');
    }

    public function cancel(Request $request, CampaignParticipant $participant): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->registrations->participantPayload($this->registrations->cancel($request, $participant, $data['reason'])), 'Đã hủy đăng ký.');
    }

    public function criteria(Request $request, Campaign $campaign): JsonResponse
    {
        return $this->ok($this->scoring->criteria($request, $campaign));
    }

    public function saveCriteria(Request $request, Campaign $campaign): JsonResponse
    {
        return $this->ok($this->scoring->saveCriteria($request, $campaign, $request->validate($this->criteriaRules())['criteria']), 'Đã lưu tiêu chí.');
    }

    public function storeResult(Request $request, Campaign $campaign): JsonResponse
    {
        return $this->created($this->scoring->resultPayload($this->scoring->saveResult($request, $campaign, $request->validate($this->resultRules()), $request->file('evidences', []))));
    }

    public function uploadEvidence(Request $request, CampaignResult $result): JsonResponse
    {
        $data = $request->validate([
            'evidences' => ['required', 'array'],
            'evidences.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        return $this->ok($this->scoring->resultPayload($this->scoring->uploadEvidence($request, $result, $data['evidences'])), 'Đã tải minh chứng.');
    }

    public function rankings(Request $request, Campaign $campaign): JsonResponse
    {
        return $this->ok($this->rankings->rankings($request, $campaign));
    }

    public function summarize(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $request->validate(['summary_report' => ['nullable', 'string', 'max:10000']]);

        return $this->ok($this->scoring->summarize($request, $campaign, $data['summary_report'] ?? null), 'Đã tổng kết phong trào.');
    }

    public function export(Request $request, Campaign $campaign, string $kind): BinaryFileResponse
    {
        abort_unless(in_array($kind, ['participants', 'results', 'rankings'], true), 404);
        $rows = $kind === 'participants'
            ? $this->registrations->rows($request, $campaign)
            : $this->rankings->rankingRows($campaign);
        $path = $this->exports->export($campaign, $kind, $rows, (string) $request->query('format', 'xlsx'));

        return response()->download($path)->deleteFileAfterSend(true);
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $data]);
    }

    private function created(mixed $data): JsonResponse
    {
        return response()->json(['message' => 'Đã tạo mới.', 'data' => $data], 201);
    }

    private function campaignRules(): array
    {
        return app(CampaignPageController::class)->campaignRulesForApi();
    }

    private function registrationRules(): array
    {
        return app(CampaignPageController::class)->registrationRulesForApi();
    }

    private function criteriaRules(): array
    {
        return app(CampaignPageController::class)->criteriaRulesForApi();
    }

    private function resultRules(): array
    {
        return app(CampaignPageController::class)->resultRulesForApi();
    }
}
