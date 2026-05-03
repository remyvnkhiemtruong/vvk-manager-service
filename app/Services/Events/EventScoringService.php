<?php

namespace App\Services\Events;

use App\Models\EventCategory;
use App\Models\EventCategoryCriterion;
use App\Models\EventJudge;
use App\Models\EventJudgeScore;
use App\Models\EventRegistration;
use App\Models\EventResult;
use App\Models\EventTeam;
use App\Models\SchoolEvent;
use App\Support\Audit\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventScoringService
{
    public function __construct(private readonly EventAccess $access)
    {
    }

    public function results(Request $request, SchoolEvent $event, array $filters = []): array
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return EventResult::query()
            ->with(['event:id,title', 'category.criteria', 'registration.schoolClass:id,name', 'team.members.student:id,student_code,full_name', 'student:id,student_code,full_name', 'judgeScores.criterion', 'judgeScores.judge.teacher:id,full_name,teacher_code'])
            ->where('event_id', $event->id)
            ->when(! empty($filters['event_category_id']), fn (Builder $query): Builder => $query->where('event_category_id', (int) $filters['event_category_id']))
            ->orderBy('event_category_id')
            ->orderByRaw('rank is null, rank asc')
            ->latest()
            ->get()
            ->map(fn (EventResult $result): array => $this->resultPayload($result))
            ->values()
            ->all();
    }

    public function saveJudgeScores(Request $request, SchoolEvent $event, array $data): EventResult
    {
        abort_unless($request->user()?->hasPermission('activities.event_results.update'), 403);
        $this->access->assertCanManage($request->user());
        $category = EventCategory::where('event_id', $event->id)->findOrFail((int) $data['event_category_id']);

        return DB::transaction(function () use ($request, $event, $category, $data): EventResult {
            $result = $this->resultForPayload($event, $category, $data);
            $before = $result->exists ? $result->getAttributes() : null;
            $result->fill([
                'event_id' => $event->id,
                'event_category_id' => $category->id,
                'award_title' => $data['award_title'] ?? $result->award_title,
                'conduct_points' => array_key_exists('conduct_points', $data) ? (int) $data['conduct_points'] : $result->conduct_points,
                'class_points' => array_key_exists('class_points', $data) ? (float) $data['class_points'] : $result->class_points,
                'remarks' => $data['remarks'] ?? $result->remarks,
                'status' => $data['status'] ?? 'published',
                'entered_by' => $request->user()?->id,
                'published_by' => in_array($data['status'] ?? 'published', ['published', 'final'], true) ? $request->user()?->id : null,
                'published_at' => in_array($data['status'] ?? 'published', ['published', 'final'], true) ? now() : null,
            ])->save();

            $judgeId = $this->judgeId($request, $event, $data);
            $this->syncJudgeScores($request, $result, $category, $judgeId, $data['scores'] ?? []);
            $finalScore = $this->calculateFinalScore($result->fresh(['judgeScores.criterion']), $category);
            $result->forceFill(['score' => $finalScore])->save();
            $this->recalculateRanks($event, $category);

            Auditor::record($before ? 'event_judge_scores.updated' : 'event_judge_scores.created', $result, $before, $result->fresh()->getAttributes(), $request);

            return $result->fresh(['category.criteria', 'registration.schoolClass', 'team.members.student', 'student', 'judgeScores.criterion', 'judgeScores.judge.teacher']);
        });
    }

    public function saveManualResult(Request $request, SchoolEvent $event, array $data): EventResult
    {
        abort_unless($request->user()?->hasPermission('activities.event_results.update'), 403);
        $this->access->assertCanManage($request->user());
        $category = EventCategory::where('event_id', $event->id)->findOrFail((int) $data['event_category_id']);

        return DB::transaction(function () use ($request, $event, $category, $data): EventResult {
            $result = $this->resultForPayload($event, $category, $data);
            $before = $result->exists ? $result->getAttributes() : null;
            $result->fill([
                'event_id' => $event->id,
                'event_category_id' => $category->id,
                'rank' => $data['rank'] ?? $result->rank,
                'score' => (float) ($data['score'] ?? $result->score ?? 0),
                'award_title' => $data['award_title'] ?? $result->award_title,
                'conduct_points' => array_key_exists('conduct_points', $data) ? (int) $data['conduct_points'] : $result->conduct_points,
                'class_points' => array_key_exists('class_points', $data) ? (float) $data['class_points'] : $result->class_points,
                'remarks' => $data['remarks'] ?? null,
                'status' => $data['status'] ?? 'published',
                'entered_by' => $request->user()?->id,
                'published_by' => in_array($data['status'] ?? 'published', ['published', 'final'], true) ? $request->user()?->id : null,
                'published_at' => in_array($data['status'] ?? 'published', ['published', 'final'], true) ? now() : null,
            ])->save();

            if (empty($data['rank'])) {
                $this->recalculateRanks($event, $category);
            }

            Auditor::record($before ? 'event_results.updated' : 'event_results.created', $result, $before, $result->fresh()->getAttributes(), $request);

            return $result->fresh(['category.criteria', 'registration.schoolClass', 'team.members.student', 'student', 'judgeScores.criterion', 'judgeScores.judge.teacher']);
        });
    }

    public function recalculateRanks(SchoolEvent $event, EventCategory $category): void
    {
        $results = EventResult::query()
            ->where('event_id', $event->id)
            ->where('event_category_id', $category->id)
            ->whereIn('status', ['published', 'final'])
            ->orderByDesc('score')
            ->orderBy('id')
            ->get();

        $rank = 1;
        $previousScore = null;
        foreach ($results as $index => $result) {
            if ($previousScore !== null && (float) $result->score < (float) $previousScore) {
                $rank = $index + 1;
            }

            $result->forceFill(['rank' => $rank])->save();
            $previousScore = $result->score;
        }
    }

    public function resultPayload(EventResult $result): array
    {
        $team = $result->team;
        $registration = $result->registration;

        return [
            'id' => $result->id,
            'event_id' => $result->event_id,
            'event_category_id' => $result->event_category_id,
            'category_name' => $result->category?->name,
            'event_registration_id' => $result->event_registration_id,
            'event_team_id' => $result->event_team_id,
            'team_name' => $team?->name,
            'student_id' => $result->student_id,
            'student_code' => $result->student?->student_code,
            'student_name' => $result->student?->full_name,
            'participant_name' => $team?->name ?? $result->student?->full_name ?? $registration?->participant_name,
            'class_id' => $registration?->class_id ?? $team?->class_id,
            'class_name' => $registration?->schoolClass?->name ?? $team?->schoolClass?->name,
            'members' => $team?->members->map(fn ($member): array => [
                'id' => $member->student_id,
                'student_code' => $member->student?->student_code,
                'full_name' => $member->student?->full_name,
                'role' => $member->role,
            ])->values() ?? collect(),
            'score' => $result->score,
            'rank' => $result->rank,
            'award_title' => $result->award_title,
            'conduct_points' => $result->conduct_points,
            'class_points' => $result->class_points,
            'remarks' => $result->remarks,
            'status' => $result->status,
            'judge_scores' => $result->judgeScores->map(fn (EventJudgeScore $score): array => [
                'id' => $score->id,
                'event_category_criterion_id' => $score->event_category_criterion_id,
                'criterion_name' => $score->criterion?->name,
                'event_judge_id' => $score->event_judge_id,
                'judge_name' => $score->judge?->teacher?->full_name ?? $score->judge?->judge_name,
                'score' => $score->score,
                'comment' => $score->comment,
            ])->values(),
        ];
    }

    private function resultForPayload(SchoolEvent $event, EventCategory $category, array $data): EventResult
    {
        if (! empty($data['event_registration_id'])) {
            $registration = EventRegistration::where('event_id', $event->id)->findOrFail((int) $data['event_registration_id']);

            return EventResult::query()->firstOrNew([
                'event_id' => $event->id,
                'event_category_id' => $category->id,
                'event_registration_id' => $registration->id,
            ], [
                'event_team_id' => $registration->event_team_id,
                'student_id' => $registration->student_id,
            ]);
        }

        if (! empty($data['event_team_id'])) {
            $team = EventTeam::where('event_id', $event->id)->findOrFail((int) $data['event_team_id']);

            return EventResult::query()->firstOrNew([
                'event_id' => $event->id,
                'event_category_id' => $category->id,
                'event_team_id' => $team->id,
            ]);
        }

        if (! empty($data['student_id'])) {
            return EventResult::query()->firstOrNew([
                'event_id' => $event->id,
                'event_category_id' => $category->id,
                'student_id' => (int) $data['student_id'],
            ]);
        }

        throw ValidationException::withMessages(['participant' => 'Cần chọn đăng ký, đội hoặc học sinh để nhập kết quả.']);
    }

    private function judgeId(Request $request, SchoolEvent $event, array $data): int
    {
        if (! empty($data['event_judge_id'])) {
            return (int) $data['event_judge_id'];
        }

        $judge = EventJudge::query()->firstOrCreate(
            ['event_id' => $event->id, 'teacher_id' => $request->user()?->staff?->id],
            ['judge_name' => $request->user()?->name, 'role' => 'judge']
        );

        return $judge->id;
    }

    private function syncJudgeScores(Request $request, EventResult $result, EventCategory $category, int $judgeId, array $scores): void
    {
        $criteria = $category->criteria()->where('status', 'active')->get()->keyBy('id');

        foreach ($scores as $row) {
            $criterionId = (int) ($row['event_category_criterion_id'] ?? $row['criterion_id'] ?? 0);
            $criterion = $criteria->get($criterionId);

            if (! $criterion) {
                continue;
            }

            $score = (float) ($row['score'] ?? 0);
            if ($score < 0 || $score > (float) $criterion->max_score) {
                throw ValidationException::withMessages(['scores' => 'Điểm tiêu chí phải nằm trong thang điểm đã cấu hình.']);
            }

            EventJudgeScore::withTrashed()
                ->updateOrCreate(
                    [
                        'event_result_id' => $result->id,
                        'event_category_criterion_id' => $criterion->id,
                        'event_judge_id' => $judgeId,
                    ],
                    [
                        'scored_by' => $request->user()?->id,
                        'score' => $score,
                        'comment' => $row['comment'] ?? null,
                        'deleted_at' => null,
                    ]
                );
        }
    }

    private function calculateFinalScore(EventResult $result, EventCategory $category): float
    {
        $judgeTotals = $result->judgeScores
            ->groupBy('event_judge_id')
            ->map(function ($rows): float {
                return round($rows->sum(fn (EventJudgeScore $score): float => (float) $score->score * (float) ($score->criterion?->weight ?? 1)), 2);
            })
            ->values()
            ->sort()
            ->values();

        if ($judgeTotals->isEmpty()) {
            return 0;
        }

        if ($category->drop_extreme_scores && $judgeTotals->count() >= 3) {
            $judgeTotals = $judgeTotals->slice(1, $judgeTotals->count() - 2)->values();
        }

        return round((float) $judgeTotals->avg(), 2);
    }
}
