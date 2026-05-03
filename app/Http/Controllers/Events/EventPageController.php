<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventPageController extends Controller
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

    public function dashboard(Request $request): Response
    {
        return Inertia::render('Events/Dashboard', [
            'dashboard' => $this->events->dashboard($request),
        ]);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'lookups' => $this->events->lookups($request),
            'events' => $this->events->events($request, $request->all()),
            'filters' => $request->only(['search', 'event_type', 'status', 'school_year_id', 'semester_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('activities.events.create'), 403);

        return Inertia::render('Events/Form', [
            'lookups' => $this->events->lookups($request),
            'event' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $event = $this->events->create($request, $request->validate($this->eventRules()), $request->file('plan_file'));

        return redirect()->route('events.show', $event)->with('success', 'Đã tạo sự kiện.');
    }

    public function show(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Detail', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'teams' => $this->tournament->teams($request, $event),
            'matches' => $this->tournament->matches($request, $event),
            'results' => $this->scoring->results($request, $event),
            'awards' => $this->awards->awards($request, $event),
        ]);
    }

    public function edit(Request $request, SchoolEvent $event): Response
    {
        abort_unless($request->user()?->hasPermission('activities.events.update'), 403);
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Form', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
        ]);
    }

    public function update(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->events->update($request, $event, $request->validate($this->eventRules()), $request->file('plan_file'));

        return back()->with('success', 'Đã cập nhật sự kiện.');
    }

    public function destroy(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->events->delete($request, $event);

        return redirect()->route('events.index')->with('success', 'Đã xóa sự kiện.');
    }

    public function categories(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Categories', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
        ]);
    }

    public function saveCategory(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->events->saveCategory($request, $event, $request->validate($this->categoryRules()));

        return back()->with('success', 'Đã lưu nội dung thi.');
    }

    public function saveCriteria(Request $request, EventCategory $category): RedirectResponse
    {
        $this->events->saveCriteria($request, $category, $request->validate($this->criteriaRules())['criteria']);

        return back()->with('success', 'Đã lưu tiêu chí.');
    }

    public function register(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Register', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'registrations' => $this->registrations->registrations($request, $event, $request->all()),
        ]);
    }

    public function storeRegistration(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->registrations->create($request, $event, $request->validate($this->registrationRules()));

        return back()->with('success', 'Đã gửi đăng ký.');
    }

    public function approvals(Request $request): Response
    {
        return Inertia::render('Events/Approvals', [
            'lookups' => [
                ...$this->events->lookups($request),
                'events' => $this->events->events($request, ['per_page' => 100])->items(),
            ],
            'registrations' => $this->registrations->registrations($request, null, $request->all()),
            'filters' => $request->only(['event_id', 'event_category_id', 'class_id', 'status', 'registration_type']),
        ]);
    }

    public function approve(Request $request, EventRegistration $registration): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->registrations->approve($request, $registration, $data['note'] ?? null);

        return back()->with('success', 'Đã duyệt đăng ký.');
    }

    public function reject(Request $request, EventRegistration $registration): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->registrations->reject($request, $registration, $data['reason']);

        return back()->with('success', 'Đã từ chối đăng ký.');
    }

    public function cancel(Request $request, EventRegistration $registration): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->registrations->cancel($request, $registration, $data['reason']);

        return back()->with('success', 'Đã hủy đăng ký.');
    }

    public function groups(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Groups', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'teams' => $this->tournament->teams($request, $event, $request->all()),
            'matches' => $this->tournament->matches($request, $event, $request->all()),
            'standings' => $this->tournament->standings($request, $event),
        ]);
    }

    public function drawGroups(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->tournament->drawGroups($request, $event, $request->validate($this->drawRules()));

        return back()->with('success', 'Đã chia bảng và sinh lịch đấu.');
    }

    public function schedules(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Schedule', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'schedules' => $this->tournament->schedules($request, $event),
            'matches' => $this->tournament->matches($request, $event, $request->all()),
        ]);
    }

    public function saveSchedule(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->tournament->saveSchedule($request, $event, $request->validate($this->scheduleRules()));

        return back()->with('success', 'Đã lưu lịch thi đấu.');
    }

    public function results(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Results', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'matches' => $this->tournament->matches($request, $event, $request->all()),
            'standings' => $this->tournament->standings($request, $event),
            'results' => $this->scoring->results($request, $event, $request->all()),
        ]);
    }

    public function saveMatchScore(Request $request, EventMatch $match): RedirectResponse
    {
        $this->tournament->saveMatchScore($request, $match, $request->validate($this->matchScoreRules()));

        return back()->with('success', 'Đã nhập tỷ số.');
    }

    public function saveManualResult(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->scoring->saveManualResult($request, $event, $request->validate($this->manualResultRules()));

        return back()->with('success', 'Đã lưu kết quả.');
    }

    public function scoring(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Scoring', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'registrations' => $this->registrations->rows($request, $event),
            'teams' => $this->tournament->teams($request, $event),
            'results' => $this->scoring->results($request, $event, $request->all()),
        ]);
    }

    public function saveJudgeScores(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->scoring->saveJudgeScores($request, $event, $request->validate($this->judgeScoreRules()));

        return back()->with('success', 'Đã lưu điểm giám khảo.');
    }

    public function awards(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Awards', [
            'lookups' => $this->events->lookups($request),
            'event' => $this->events->eventPayload($event),
            'results' => $this->scoring->results($request, $event),
            'awards' => $this->awards->awards($request, $event),
        ]);
    }

    public function saveAward(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->awards->saveAward($request, $event, $request->validate($this->awardRules()));

        return back()->with('success', 'Đã lưu giải thưởng.');
    }

    public function summary(Request $request, SchoolEvent $event): Response
    {
        $event = $this->events->findForView($request, $event);

        return Inertia::render('Events/Summary', [
            'event' => $this->events->eventPayload($event),
            'categories' => $this->events->categoriesPayload($event),
            'registrations' => $this->registrations->rows($request, $event),
            'matches' => $this->tournament->matches($request, $event),
            'results' => $this->scoring->results($request, $event),
            'awards' => $this->awards->awards($request, $event),
        ]);
    }

    public function summarize(Request $request, SchoolEvent $event): RedirectResponse
    {
        $data = $request->validate(['summary_report' => ['nullable', 'string', 'max:10000']]);
        $applied = $this->awards->summarize($request, $event, $data['summary_report'] ?? null);

        return back()->with('success', 'Đã tổng kết sự kiện: cộng '.$applied['conduct'].' điểm rèn luyện, '.$applied['class'].' điểm lớp, '.$applied['rewards'].' khen thưởng.');
    }

    public function uploadFile(Request $request, SchoolEvent $event): RedirectResponse
    {
        $data = $request->validate([
            'file_type' => ['required', 'string', 'in:plan,rules,evidence,summary'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        $this->events->storeFile($request, $event, $data['file'], $data['file_type']);

        return back()->with('success', 'Đã tải file.');
    }

    public function file(Request $request, SchoolEvent $event, EventFile $file): StreamedResponse
    {
        return $this->events->downloadFile($request, $event, $file);
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

    public function eventRulesForApi(): array
    {
        return $this->eventRules();
    }

    public function categoryRulesForApi(): array
    {
        return $this->categoryRules();
    }

    public function registrationRulesForApi(): array
    {
        return $this->registrationRules();
    }

    public function criteriaRulesForApi(): array
    {
        return $this->criteriaRules();
    }

    public function drawRulesForApi(): array
    {
        return $this->drawRules();
    }

    public function scheduleRulesForApi(): array
    {
        return $this->scheduleRules();
    }

    public function matchScoreRulesForApi(): array
    {
        return $this->matchScoreRules();
    }

    public function judgeScoreRulesForApi(): array
    {
        return $this->judgeScoreRules();
    }

    public function manualResultRulesForApi(): array
    {
        return $this->manualResultRules();
    }

    public function awardRulesForApi(): array
    {
        return $this->awardRules();
    }

    private function eventRules(): array
    {
        return [
            'school_year_id' => ['required', 'exists:school_years,id'],
            'semester_id' => ['nullable', 'exists:semesters,id'],
            'title' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'in:'.implode(',', array_keys(config('school.events.types', [])))],
            'organizer_unit' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'target_audience' => ['required', 'string', 'in:'.implode(',', array_keys(config('school.events.target_audiences', [])))],
            'registration_modes' => ['nullable', 'array'],
            'registration_modes.*' => ['string', 'in:'.implode(',', array_keys(config('school.events.registration_modes', [])))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['nullable', 'string', 'max:10000'],
            'summary_report' => ['nullable', 'string', 'max:10000'],
            'conduct_points_per_student' => ['nullable', 'integer', 'min:0'],
            'class_competition_points' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(config('school.events.statuses', [])))],
            'plan_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'organizers' => ['nullable', 'array'],
            'organizers.*.teacher_id' => ['nullable', 'exists:teachers,id'],
            'organizers.*.organizer_name' => ['nullable', 'string', 'max:255'],
            'organizers.*.role' => ['nullable', 'string', 'max:100'],
            'judges' => ['nullable', 'array'],
            'judges.*.teacher_id' => ['nullable', 'exists:teachers,id'],
            'judges.*.judge_name' => ['nullable', 'string', 'max:255'],
            'judges.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function categoryRules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:event_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'category_type' => ['nullable', 'string', 'max:100'],
            'participation_type' => ['required', 'string', 'in:individual,team,class'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'gender_rule' => ['nullable', 'string', 'max:50'],
            'allowed_grade_ids' => ['nullable', 'array'],
            'allowed_class_ids' => ['nullable', 'array'],
            'rules_text' => ['nullable', 'string', 'max:10000'],
            'scoring_mode' => ['required', 'string', 'in:sport,judged,time,manual'],
            'sport_rule' => ['nullable', 'string', 'max:100'],
            'judge_score_mode' => ['nullable', 'string', 'in:average,manual'],
            'drop_extreme_scores' => ['nullable', 'boolean'],
            'max_score' => ['nullable', 'numeric', 'min:0'],
            'order_index' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function criteriaRules(): array
    {
        return [
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.id' => ['nullable', 'integer', 'exists:event_category_criteria,id'],
            'criteria.*.code' => ['nullable', 'string', 'max:100'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string', 'max:1000'],
            'criteria.*.max_score' => ['required', 'numeric', 'min:0'],
            'criteria.*.weight' => ['required', 'numeric', 'min:0'],
            'criteria.*.order_index' => ['nullable', 'integer', 'min:1'],
            'criteria.*.status' => ['required', 'string', 'max:50'],
        ];
    }

    private function registrationRules(): array
    {
        return [
            'event_category_id' => ['required', 'exists:event_categories,id'],
            'registration_type' => ['required', 'string', 'in:individual,team,class'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'participant_name' => ['nullable', 'string', 'max:255'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:students,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function drawRules(): array
    {
        return [
            'event_category_id' => ['required', 'exists:event_categories,id'],
            'group_count' => ['nullable', 'integer', 'min:1', 'max:16'],
            'starts_at' => ['nullable', 'date'],
            'minutes_per_match' => ['nullable', 'integer', 'min:10', 'max:240'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function scheduleRules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:event_schedules,id'],
            'event_category_id' => ['nullable', 'exists:event_categories,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function matchScoreRules(): array
    {
        return [
            'home_score' => ['nullable', 'numeric', 'min:0'],
            'away_score' => ['nullable', 'numeric', 'min:0'],
            'played_at' => ['nullable', 'date'],
            'result_note' => ['nullable', 'string', 'max:1000'],
            'sets' => ['nullable', 'array'],
            'sets.*.set_number' => ['nullable', 'integer', 'min:1'],
            'sets.*.home_score' => ['required_with:sets', 'numeric', 'min:0'],
            'sets.*.away_score' => ['required_with:sets', 'numeric', 'min:0'],
        ];
    }

    private function manualResultRules(): array
    {
        return [
            'event_category_id' => ['required', 'exists:event_categories,id'],
            'event_registration_id' => ['nullable', 'exists:event_registrations,id'],
            'event_team_id' => ['nullable', 'exists:event_teams,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'score' => ['nullable', 'numeric', 'min:0'],
            'rank' => ['nullable', 'integer', 'min:1'],
            'award_title' => ['nullable', 'string', 'max:255'],
            'conduct_points' => ['nullable', 'integer', 'min:0'],
            'class_points' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:draft,published,final'],
        ];
    }

    private function judgeScoreRules(): array
    {
        return [
            'event_category_id' => ['required', 'exists:event_categories,id'],
            'event_registration_id' => ['nullable', 'exists:event_registrations,id'],
            'event_team_id' => ['nullable', 'exists:event_teams,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'event_judge_id' => ['nullable', 'exists:event_judges,id'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.event_category_criterion_id' => ['required', 'exists:event_category_criteria,id'],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
            'scores.*.comment' => ['nullable', 'string', 'max:1000'],
            'award_title' => ['nullable', 'string', 'max:255'],
            'conduct_points' => ['nullable', 'integer', 'min:0'],
            'class_points' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:draft,published,final'],
        ];
    }

    private function awardRules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:event_awards,id'],
            'event_result_id' => ['required', 'exists:event_results,id'],
            'award_type' => ['nullable', 'string', 'max:100'],
            'rank' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'awarded_date' => ['nullable', 'date'],
        ];
    }
}
