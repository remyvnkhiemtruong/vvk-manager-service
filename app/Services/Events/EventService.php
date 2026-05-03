<?php

namespace App\Services\Events;

use App\Models\EventCategory;
use App\Models\EventCategoryCriterion;
use App\Models\EventFile;
use App\Models\EventJudge;
use App\Models\EventOrganizer;
use App\Models\EventRegistration;
use App\Models\EventResult;
use App\Models\SchoolEvent;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Staff;
use App\Support\Audit\Auditor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventService
{
    public function __construct(private readonly EventAccess $access)
    {
    }

    public function events(Request $request, array $filters = []): LengthAwarePaginator
    {
        abort_unless($request->user()?->hasPermission('activities.events.view'), 403);

        return $this->access->scopeEvents($request->user(), SchoolEvent::query())
            ->with(['schoolYear:id,name', 'semester:id,name', 'creator:id,name'])
            ->withCount(['categories', 'registrations', 'teams', 'matches', 'results', 'awards'])
            ->when(! empty($filters['school_year_id']), fn (Builder $query): Builder => $query->where('school_year_id', (int) $filters['school_year_id']))
            ->when(! empty($filters['semester_id']), fn (Builder $query): Builder => $query->where('semester_id', (int) $filters['semester_id']))
            ->when(! empty($filters['event_type']), fn (Builder $query): Builder => $query->where('event_type', $filters['event_type']))
            ->when(! empty($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): Builder {
                $search = '%'.trim((string) $filters['search']).'%';

                return $query->where(fn (Builder $builder): Builder => $builder
                    ->where('title', 'like', $search)
                    ->orWhere('organizer_unit', 'like', $search)
                    ->orWhere('location', 'like', $search)
                    ->orWhere('description', 'like', $search));
            })
            ->latest('starts_at')
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100))
            ->withQueryString()
            ->through(fn (SchoolEvent $event): array => $this->eventPayload($event));
    }

    public function dashboard(Request $request): array
    {
        abort_unless($request->user()?->hasPermission('activities.events.view'), 403);

        $eventQuery = $this->access->scopeEvents($request->user(), SchoolEvent::query());

        return [
            'stats' => [
                'total' => (clone $eventQuery)->count(),
                'open' => (clone $eventQuery)->where('status', 'registration_open')->count(),
                'running' => (clone $eventQuery)->where('status', 'in_progress')->count(),
                'summarized' => (clone $eventQuery)->where('status', 'summarized')->count(),
                'pending_registrations' => $this->access->scopeRegistrations($request->user(), EventRegistration::query())->where('status', 'pending')->count(),
            ],
            'upcoming' => (clone $eventQuery)
                ->whereIn('status', ['registration_open', 'in_progress'])
                ->orderBy('starts_at')
                ->limit(8)
                ->get()
                ->map(fn (SchoolEvent $event): array => $this->eventPayload($event))
                ->values(),
            'recentResults' => EventResult::query()
                ->with(['event:id,title', 'category:id,name', 'team:id,name', 'student:id,student_code,full_name'])
                ->whereHas('event', fn (Builder $query): Builder => $this->access->scopeEvents($request->user(), $query))
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (EventResult $result): array => $this->resultSummaryPayload($result))
                ->values(),
        ];
    }

    public function findForView(Request $request, SchoolEvent $event): SchoolEvent
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return $event->load([
            'schoolYear:id,name',
            'semester:id,name',
            'categories' => fn ($query) => $query->orderBy('order_index')->orderBy('id'),
            'organizers.teacher:id,full_name,teacher_code',
            'judges.teacher:id,full_name,teacher_code',
            'files' => fn ($query) => $query->where('file_type', 'plan')->latest(),
        ]);
    }

    public function create(Request $request, array $data, ?UploadedFile $planFile = null): SchoolEvent
    {
        abort_unless($request->user()?->hasPermission('activities.events.create'), 403);
        $this->access->assertCanManage($request->user());

        return DB::transaction(function () use ($request, $data, $planFile): SchoolEvent {
            $event = SchoolEvent::create([
                ...$this->eventData($data),
                'created_by' => $request->user()?->id,
            ]);

            $this->syncOrganizers($request, $event, $data['organizers'] ?? []);
            $this->syncJudges($request, $event, $data['judges'] ?? []);

            if ($planFile) {
                $this->storeFile($request, $event, $planFile, 'plan');
            }

            Auditor::record('events.created', $event, null, $this->snapshot($event->fresh()), $request);

            return $event->fresh(['categories', 'organizers', 'judges', 'files']);
        });
    }

    public function update(Request $request, SchoolEvent $event, array $data, ?UploadedFile $planFile = null): SchoolEvent
    {
        abort_unless($request->user()?->hasPermission('activities.events.update'), 403);
        $this->access->assertCanManage($request->user());

        return DB::transaction(function () use ($request, $event, $data, $planFile): SchoolEvent {
            $before = $this->snapshot($event);
            $event->fill($this->eventData($data))->save();

            $this->syncOrganizers($request, $event, $data['organizers'] ?? []);
            $this->syncJudges($request, $event, $data['judges'] ?? []);

            if ($planFile) {
                $this->storeFile($request, $event, $planFile, 'plan');
            }

            Auditor::record('events.updated', $event, $before, $this->snapshot($event->fresh()), $request);

            return $event->fresh(['categories', 'organizers', 'judges', 'files']);
        });
    }

    public function delete(Request $request, SchoolEvent $event): void
    {
        abort_unless($request->user()?->hasPermission('activities.events.delete'), 403);
        $this->access->assertCanManage($request->user());

        DB::transaction(function () use ($request, $event): void {
            $before = $this->snapshot($event);
            $event->delete();
            Auditor::record('events.deleted', $event, $before, null, $request);
        });
    }

    public function saveCategory(Request $request, SchoolEvent $event, array $data): EventCategory
    {
        abort_unless($request->user()?->hasPermission('activities.event_categories.update'), 403);
        $this->access->assertCanManage($request->user());
        $this->access->assertCanViewEvent($request->user(), $event);

        return DB::transaction(function () use ($request, $event, $data): EventCategory {
            $category = empty($data['id'])
                ? new EventCategory(['event_id' => $event->id])
                : EventCategory::where('event_id', $event->id)->findOrFail((int) $data['id']);

            $before = $category->exists ? $category->getAttributes() : null;
            $category->fill([
                'event_id' => $event->id,
                'name' => $data['name'],
                'category_type' => $data['category_type'] ?? $data['sport_rule'] ?? null,
                'participation_type' => $data['participation_type'] ?? 'team',
                'max_participants' => $data['max_participants'] ?? null,
                'gender_rule' => $data['gender_rule'] ?? null,
                'allowed_grade_ids' => array_values(array_filter((array) ($data['allowed_grade_ids'] ?? []))),
                'allowed_class_ids' => array_values(array_filter((array) ($data['allowed_class_ids'] ?? []))),
                'rules_text' => $data['rules_text'] ?? null,
                'scoring_mode' => $data['scoring_mode'] ?? 'sport',
                'sport_rule' => $data['sport_rule'] ?? $data['category_type'] ?? null,
                'judge_score_mode' => $data['judge_score_mode'] ?? 'average',
                'drop_extreme_scores' => (bool) ($data['drop_extreme_scores'] ?? false),
                'max_score' => (float) ($data['max_score'] ?? 100),
                'order_index' => (int) ($data['order_index'] ?? 1),
                'status' => $data['status'] ?? 'active',
            ])->save();

            if (($category->scoring_mode === 'judged') && ! $category->criteria()->exists()) {
                $this->ensureDefaultCriteria($category);
            }

            Auditor::record($before ? 'event_categories.updated' : 'event_categories.created', $category, $before, $category->fresh()->getAttributes(), $request);

            return $category->fresh(['criteria']);
        });
    }

    public function saveCriteria(Request $request, EventCategory $category, array $criteria): array
    {
        abort_unless($request->user()?->hasPermission('activities.event_categories.update'), 403);
        $this->access->assertCanManage($request->user());

        DB::transaction(function () use ($request, $category, $criteria): void {
            $kept = [];

            foreach (array_values($criteria) as $index => $row) {
                $payload = [
                    'event_category_id' => $category->id,
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'max_score' => (float) ($row['max_score'] ?? 10),
                    'weight' => (float) ($row['weight'] ?? 1),
                    'order_index' => (int) ($row['order_index'] ?? ($index + 1)),
                    'status' => $row['status'] ?? 'active',
                ];

                $criterion = empty($row['id'])
                    ? EventCategoryCriterion::create($payload)
                    : tap(EventCategoryCriterion::where('event_category_id', $category->id)->findOrFail((int) $row['id']))->update($payload);

                $kept[] = $criterion->id;
            }

            EventCategoryCriterion::query()->where('event_category_id', $category->id)->whereNotIn('id', $kept)->delete();
            Auditor::record('event_category_criteria.updated', $category, null, ['criterion_ids' => $kept], $request);
        });

        return $this->criteriaPayload($category->fresh('criteria'));
    }

    public function lookups(Request $request): array
    {
        return [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->get(['id', 'name', 'is_active']),
            'semesters' => Semester::query()->orderByDesc('school_year_id')->orderBy('term_number')->get(['id', 'school_year_id', 'name', 'term_number', 'is_active']),
            'classes' => $this->access->classQueryFor($request->user())->orderBy('name')->get(['id', 'school_year_id', 'grade_id', 'name']),
            'students' => $this->access->studentQueryFor($request->user())->orderBy('full_name')->get(['id', 'student_code', 'full_name']),
            'teachers' => Staff::query()->orderBy('full_name')->get(['id', 'teacher_code', 'full_name']),
            'types' => config('school.events.types', []),
            'typeGroups' => config('school.events.type_groups', []),
            'statuses' => config('school.events.statuses', []),
            'targetAudiences' => config('school.events.target_audiences', []),
            'registrationModes' => config('school.events.registration_modes', []),
            'registrationStatuses' => config('school.events.registration_statuses', []),
            'participationTypes' => config('school.events.participation_types', []),
            'scoringModes' => config('school.events.scoring_modes', []),
            'sportRules' => config('school.events.sport_rules', []),
            'organizerRoles' => config('school.events.organizer_roles', []),
            'awardTypes' => config('school.events.award_types', []),
        ];
    }

    public function storeFile(Request $request, SchoolEvent $event, UploadedFile $file, string $type): EventFile
    {
        $this->access->assertCanManage($request->user());

        $path = $file->store('events/'.$event->id.'/'.$type, 'local');
        $record = EventFile::create([
            'event_id' => $event->id,
            'file_type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()?->id,
        ]);

        Auditor::record('event_files.created', $record, null, $record->fresh()->getAttributes(), $request);

        return $record;
    }

    public function downloadFile(Request $request, SchoolEvent $event, EventFile $file): StreamedResponse
    {
        $this->access->assertCanViewEvent($request->user(), $event);
        abort_unless((int) $file->event_id === (int) $event->id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function eventPayload(SchoolEvent $event): array
    {
        return [
            'id' => $event->id,
            'school_year_id' => $event->school_year_id,
            'school_year_name' => $event->schoolYear?->name,
            'semester_id' => $event->semester_id,
            'semester_name' => $event->semester?->name,
            'title' => $event->title,
            'event_type' => $event->event_type,
            'type_label' => config('school.events.types.'.$event->event_type, $event->event_type),
            'organizer_unit' => $event->organizer_unit,
            'location' => $event->location,
            'target_audience' => $event->target_audience,
            'registration_modes' => $event->registration_modes ?? ['individual', 'team', 'class'],
            'starts_at' => $event->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
            'description' => $event->description,
            'summary_report' => $event->summary_report,
            'conduct_points_per_student' => $event->conduct_points_per_student,
            'class_competition_points' => $event->class_competition_points,
            'status' => $event->status,
            'status_label' => config('school.events.statuses.'.$event->status, $event->status),
            'categories_count' => $event->categories_count ?? null,
            'registrations_count' => $event->registrations_count ?? null,
            'teams_count' => $event->teams_count ?? null,
            'matches_count' => $event->matches_count ?? null,
            'results_count' => $event->results_count ?? null,
            'awards_count' => $event->awards_count ?? null,
            'created_by' => $event->creator?->name,
            'summarized_at' => $event->summarized_at?->toDateTimeString(),
        ];
    }

    public function categoryPayload(EventCategory $category): array
    {
        return [
            'id' => $category->id,
            'event_id' => $category->event_id,
            'name' => $category->name,
            'category_type' => $category->category_type,
            'participation_type' => $category->participation_type,
            'max_participants' => $category->max_participants,
            'gender_rule' => $category->gender_rule,
            'allowed_grade_ids' => $category->allowed_grade_ids ?? [],
            'allowed_class_ids' => $category->allowed_class_ids ?? [],
            'rules_text' => $category->rules_text,
            'scoring_mode' => $category->scoring_mode,
            'sport_rule' => $category->sport_rule,
            'judge_score_mode' => $category->judge_score_mode,
            'drop_extreme_scores' => (bool) $category->drop_extreme_scores,
            'max_score' => $category->max_score,
            'order_index' => $category->order_index,
            'status' => $category->status,
            'criteria' => $this->criteriaPayload($category),
        ];
    }

    public function categoriesPayload(SchoolEvent $event): array
    {
        return $event->categories()
            ->with('criteria')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->map(fn (EventCategory $category): array => $this->categoryPayload($category))
            ->values()
            ->all();
    }

    public function criteriaPayload(EventCategory $category): array
    {
        return $category->criteria
            ->sortBy([['order_index', 'asc'], ['id', 'asc']])
            ->map(fn (EventCategoryCriterion $criterion): array => [
                'id' => $criterion->id,
                'event_category_id' => $criterion->event_category_id,
                'code' => $criterion->code,
                'name' => $criterion->name,
                'description' => $criterion->description,
                'max_score' => $criterion->max_score,
                'weight' => $criterion->weight,
                'order_index' => $criterion->order_index,
                'status' => $criterion->status,
            ])
            ->values()
            ->all();
    }

    public function resultSummaryPayload(EventResult $result): array
    {
        return [
            'id' => $result->id,
            'event_title' => $result->event?->title,
            'category_name' => $result->category?->name,
            'participant_name' => $result->team?->name ?? $result->student?->full_name ?? $result->registration?->participant_name,
            'score' => $result->score,
            'rank' => $result->rank,
            'award_title' => $result->award_title,
        ];
    }

    private function eventData(array $data): array
    {
        $modes = array_values(array_filter((array) ($data['registration_modes'] ?? ['individual', 'team', 'class'])));

        return [
            ...Arr::only($data, [
                'school_year_id',
                'semester_id',
                'title',
                'event_type',
                'organizer_unit',
                'location',
                'target_audience',
                'starts_at',
                'ends_at',
                'description',
                'summary_report',
                'status',
            ]),
            'registration_modes' => $modes ?: ['individual', 'team', 'class'],
            'conduct_points_per_student' => (int) ($data['conduct_points_per_student'] ?? 0),
            'class_competition_points' => (float) ($data['class_competition_points'] ?? 0),
        ];
    }

    private function syncOrganizers(Request $request, SchoolEvent $event, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $kept = [];
        foreach ($rows as $row) {
            if (empty($row['teacher_id']) && empty($row['organizer_name'])) {
                continue;
            }

            $organizer = empty($row['id'])
                ? new EventOrganizer(['event_id' => $event->id])
                : EventOrganizer::where('event_id', $event->id)->findOrFail((int) $row['id']);

            $organizer->fill([
                'teacher_id' => $row['teacher_id'] ?? null,
                'user_id' => $row['user_id'] ?? null,
                'organizer_name' => $row['organizer_name'] ?? null,
                'role' => $row['role'] ?? 'member',
                'note' => $row['note'] ?? null,
            ])->save();
            $kept[] = $organizer->id;
        }

        if ($kept !== []) {
            EventOrganizer::query()->where('event_id', $event->id)->whereNotIn('id', $kept)->delete();
            Auditor::record('event_organizers.synced', $event, null, ['organizer_ids' => $kept], $request);
        }
    }

    private function syncJudges(Request $request, SchoolEvent $event, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $kept = [];
        foreach ($rows as $row) {
            if (empty($row['teacher_id']) && empty($row['judge_name'])) {
                continue;
            }

            $judge = empty($row['id'])
                ? new EventJudge(['event_id' => $event->id])
                : EventJudge::where('event_id', $event->id)->findOrFail((int) $row['id']);

            $judge->fill([
                'teacher_id' => $row['teacher_id'] ?? null,
                'judge_name' => $row['judge_name'] ?? null,
                'role' => $row['role'] ?? 'judge',
            ])->save();
            $kept[] = $judge->id;
        }

        if ($kept !== []) {
            EventJudge::query()->where('event_id', $event->id)->whereNotIn('id', $kept)->delete();
            Auditor::record('event_judges.synced', $event, null, ['judge_ids' => $kept], $request);
        }
    }

    private function ensureDefaultCriteria(EventCategory $category): void
    {
        foreach (config('school.events.default_criteria', []) as $index => $criterion) {
            EventCategoryCriterion::create([
                'event_category_id' => $category->id,
                'code' => $criterion['code'],
                'name' => $criterion['name'],
                'description' => $criterion['description'] ?? null,
                'max_score' => $criterion['max_score'] ?? 10,
                'weight' => $criterion['weight'] ?? 1,
                'order_index' => $index + 1,
                'status' => 'active',
            ]);
        }
    }

    private function snapshot(SchoolEvent $event): array
    {
        return Arr::only($event->fresh()?->getAttributes() ?? $event->getAttributes(), [
            'id',
            'school_year_id',
            'semester_id',
            'title',
            'event_type',
            'organizer_unit',
            'location',
            'target_audience',
            'registration_modes',
            'starts_at',
            'ends_at',
            'status',
            'conduct_points_per_student',
            'class_competition_points',
            'summary_report',
            'summarized_by',
            'summarized_at',
        ]);
    }
}
