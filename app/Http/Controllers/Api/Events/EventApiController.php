<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Events\EventPageController;
use App\Models\EventCategory;
use App\Models\EventFile;
use App\Models\EventMatch;
use App\Models\EventRegistration;
use App\Models\SchoolEvent;
use App\Services\Events\EventAwardService;
use App\Services\Events\EventExportService;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventScoringService;
use App\Services\Events\EventService;
use App\Services\Events\EventTournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventApiController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventRegistrationService $registrations,
        private readonly EventTournamentService $tournament,
        private readonly EventScoringService $scoring,
        private readonly EventAwardService $awards,
        private readonly EventExportService $exports
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->events->events($request, $request->all()));
    }

    public function store(Request $request): JsonResponse
    {
        $event = $this->events->create($request, $request->validate($this->rules()->eventRulesForApi()), $request->file('plan_file'));

        return $this->created($this->events->eventPayload($event));
    }

    public function show(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->events->eventPayload($this->events->findForView($request, $event)));
    }

    public function update(Request $request, SchoolEvent $event): JsonResponse
    {
        $event = $this->events->update($request, $event, $request->validate($this->rules()->eventRulesForApi()), $request->file('plan_file'));

        return $this->ok($this->events->eventPayload($event), 'Đã cập nhật sự kiện.');
    }

    public function destroy(Request $request, SchoolEvent $event): JsonResponse
    {
        $this->events->delete($request, $event);

        return $this->ok(null, 'Đã xóa sự kiện.');
    }

    public function uploadFile(Request $request, SchoolEvent $event): JsonResponse
    {
        $data = $request->validate([
            'file_type' => ['required', 'string', 'in:plan,rules,evidence,summary'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        return $this->created($this->events->storeFile($request, $event, $data['file'], $data['file_type']));
    }

    public function file(Request $request, SchoolEvent $event, EventFile $file): StreamedResponse
    {
        return $this->events->downloadFile($request, $event, $file);
    }

    public function categories(Request $request, SchoolEvent $event): JsonResponse
    {
        $this->events->findForView($request, $event);

        return $this->ok($this->events->categoriesPayload($event));
    }

    public function saveCategory(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->created($this->events->categoryPayload($this->events->saveCategory($request, $event, $request->validate($this->rules()->categoryRulesForApi()))));
    }

    public function saveCriteria(Request $request, EventCategory $category): JsonResponse
    {
        return $this->ok($this->events->saveCriteria($request, $category, $request->validate($this->rules()->criteriaRulesForApi())['criteria']), 'Đã lưu tiêu chí.');
    }

    public function registrations(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->registrations->registrations($request, $event, $request->all()));
    }

    public function storeRegistration(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->created($this->registrations->registrationPayload($this->registrations->create($request, $event, $request->validate($this->rules()->registrationRulesForApi()))));
    }

    public function approve(Request $request, EventRegistration $registration): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->ok($this->registrations->registrationPayload($this->registrations->approve($request, $registration, $data['note'] ?? null)), 'Đã duyệt đăng ký.');
    }

    public function reject(Request $request, EventRegistration $registration): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->registrations->registrationPayload($this->registrations->reject($request, $registration, $data['reason'])), 'Đã từ chối đăng ký.');
    }

    public function cancel(Request $request, EventRegistration $registration): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->registrations->registrationPayload($this->registrations->cancel($request, $registration, $data['reason'])), 'Đã hủy đăng ký.');
    }

    public function teams(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->tournament->teams($request, $event, $request->all()));
    }

    public function drawGroups(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->tournament->drawGroups($request, $event, $request->validate($this->rules()->drawRulesForApi())), 'Đã chia bảng.');
    }

    public function schedules(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->tournament->schedules($request, $event));
    }

    public function saveSchedule(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->created($this->tournament->saveSchedule($request, $event, $request->validate($this->rules()->scheduleRulesForApi())));
    }

    public function matches(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->tournament->matches($request, $event, $request->all()));
    }

    public function scoreMatch(Request $request, EventMatch $match): JsonResponse
    {
        return $this->ok($this->tournament->matchPayload($this->tournament->saveMatchScore($request, $match, $request->validate($this->rules()->matchScoreRulesForApi()))), 'Đã nhập tỷ số.');
    }

    public function scoring(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->scoring->results($request, $event, $request->all()));
    }

    public function saveJudgeScores(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->created($this->scoring->resultPayload($this->scoring->saveJudgeScores($request, $event, $request->validate($this->rules()->judgeScoreRulesForApi()))));
    }

    public function saveResult(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->created($this->scoring->resultPayload($this->scoring->saveManualResult($request, $event, $request->validate($this->rules()->manualResultRulesForApi()))));
    }

    public function awards(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->ok($this->awards->awards($request, $event));
    }

    public function saveAward(Request $request, SchoolEvent $event): JsonResponse
    {
        return $this->created($this->awards->awardPayload($this->awards->saveAward($request, $event, $request->validate($this->rules()->awardRulesForApi()))));
    }

    public function summarize(Request $request, SchoolEvent $event): JsonResponse
    {
        $data = $request->validate(['summary_report' => ['nullable', 'string', 'max:10000']]);

        return $this->ok($this->awards->summarize($request, $event, $data['summary_report'] ?? null), 'Đã tổng kết sự kiện.');
    }

    public function export(Request $request, SchoolEvent $event, string $kind): BinaryFileResponse
    {
        abort_unless(in_array($kind, ['registrations', 'schedule', 'results', 'rankings', 'awards'], true), 404);
        $rows = match ($kind) {
            'registrations' => $this->registrations->rows($request, $event),
            'schedule' => $this->tournament->matches($request, $event),
            'awards' => $this->awards->awards($request, $event),
            default => $this->scoring->results($request, $event),
        };
        $path = $this->exports->export($event, $kind, $rows, (string) $request->query('format', 'xlsx'));

        return response()->download($path)->deleteFileAfterSend(true);
    }

    private function rules(): EventPageController
    {
        return app(EventPageController::class);
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $data]);
    }

    private function created(mixed $data): JsonResponse
    {
        return response()->json(['message' => 'Đã tạo mới.', 'data' => $data], 201);
    }
}
