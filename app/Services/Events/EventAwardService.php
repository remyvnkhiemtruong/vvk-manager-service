<?php

namespace App\Services\Events;

use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\EventAward;
use App\Models\EventAwardRecipient;
use App\Models\EventClassScore;
use App\Models\EventPointApplication;
use App\Models\EventResult;
use App\Models\SchoolEvent;
use App\Services\Conduct\ConductScoreService;
use App\Support\Audit\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventAwardService
{
    public function __construct(
        private readonly EventAccess $access,
        private readonly ConductScoreService $conductScores
    ) {
    }

    public function awards(Request $request, SchoolEvent $event): array
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return EventAward::query()
            ->with(['result.category:id,name', 'result.team:id,name', 'recipients'])
            ->where('event_id', $event->id)
            ->orderBy('event_category_id')
            ->orderBy('rank')
            ->latest()
            ->get()
            ->map(fn (EventAward $award): array => $this->awardPayload($award))
            ->values()
            ->all();
    }

    public function saveAward(Request $request, SchoolEvent $event, array $data): EventAward
    {
        abort_unless($request->user()?->hasPermission('activities.event_awards.update'), 403);
        $this->access->assertCanManage($request->user());

        return DB::transaction(function () use ($request, $event, $data): EventAward {
            $result = EventResult::where('event_id', $event->id)->findOrFail((int) $data['event_result_id']);
            $award = empty($data['id'])
                ? new EventAward(['event_id' => $event->id])
                : EventAward::where('event_id', $event->id)->findOrFail((int) $data['id']);
            $before = $award->exists ? $award->getAttributes() : null;

            $award->fill([
                'event_id' => $event->id,
                'event_result_id' => $result->id,
                'event_category_id' => $result->event_category_id,
                'event_team_id' => $result->event_team_id,
                'student_id' => $result->student_id,
                'class_id' => $this->classIdForResult($result),
                'award_type' => $data['award_type'] ?? 'ranked',
                'rank' => $data['rank'] ?? $result->rank,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'awarded_date' => $data['awarded_date'] ?? now()->toDateString(),
                'awarded_by' => $request->user()?->id,
            ])->save();

            $result->forceFill([
                'award_title' => $award->title,
                'rank' => $award->rank ?: $result->rank,
                'status' => 'final',
                'published_by' => $request->user()?->id,
                'published_at' => now(),
            ])->save();

            Auditor::record($before ? 'event_awards.updated' : 'event_awards.created', $award, $before, $award->fresh()->getAttributes(), $request);

            return $award->fresh(['result', 'recipients']);
        });
    }

    public function summarize(Request $request, SchoolEvent $event, ?string $summaryReport = null): array
    {
        abort_unless($request->user()?->hasPermission('activities.event_results.update'), 403);
        $this->access->assertCanManage($request->user());

        $applied = ['conduct' => 0, 'class' => 0, 'rewards' => 0];

        DB::transaction(function () use ($request, $event, $summaryReport, &$applied): void {
            $before = [
                'status' => $event->status,
                'summary_report' => $event->summary_report,
                'summarized_by' => $event->summarized_by,
                'summarized_at' => $event->summarized_at,
            ];
            $rule = $this->eventConductRule();

            $results = EventResult::query()
                ->with(['event', 'category', 'registration', 'team.members', 'student'])
                ->where('event_id', $event->id)
                ->whereIn('status', ['published', 'final'])
                ->get();

            foreach ($results as $result) {
                $classId = $this->classIdForResult($result);
                $conductPoints = (int) ($result->conduct_points ?? $event->conduct_points_per_student ?? 0);
                $studentIds = $this->studentIdsForResult($result, $event);

                if ($conductPoints > 0) {
                    foreach ($studentIds as $studentId) {
                        if ($this->alreadyApplied($result, 'conduct', $studentId, null)) {
                            continue;
                        }

                        $studentClassId = $this->access->classIdForStudent($studentId, (int) $event->semester_id) ?: $classId;
                        $record = $this->createConductRecord($request, $event, $result, $rule, $studentId, (int) $studentClassId, $conductPoints);
                        EventPointApplication::create([
                            'event_id' => $event->id,
                            'event_result_id' => $result->id,
                            'event_registration_id' => $result->event_registration_id,
                            'event_team_id' => $result->event_team_id,
                            'application_type' => 'conduct',
                            'student_id' => $studentId,
                            'class_id' => $studentClassId ?: null,
                            'conduct_record_id' => $record->id,
                            'points' => $conductPoints,
                            'applied_by' => $request->user()?->id,
                            'applied_at' => now(),
                        ]);
                        $applied['conduct']++;
                    }
                }

                $classPoints = (float) ($result->class_points ?? $event->class_competition_points ?? 0);
                if ($classPoints > 0 && $classId && ! $this->alreadyApplied($result, 'class_competition', null, $classId)) {
                    $classScore = $this->applyClassScore($request, $event, $result, $classId, $classPoints);
                    EventPointApplication::create([
                        'event_id' => $event->id,
                        'event_result_id' => $result->id,
                        'event_registration_id' => $result->event_registration_id,
                        'event_team_id' => $result->event_team_id,
                        'application_type' => 'class_competition',
                        'class_id' => $classId,
                        'event_class_score_id' => $classScore->id,
                        'points' => $classPoints,
                        'applied_by' => $request->user()?->id,
                        'applied_at' => now(),
                    ]);
                    $applied['class']++;
                }

                if ($result->award_title) {
                    $award = $this->ensureAward($request, $event, $result);
                    foreach ($studentIds as $studentId) {
                        if ($this->alreadyApplied($result, 'reward', $studentId, null)) {
                            continue;
                        }

                        $rewardId = $this->createReward($request, $event, $result, $studentId);
                        EventAwardRecipient::query()->firstOrCreate([
                            'event_award_id' => $award->id,
                            'student_id' => $studentId,
                        ], [
                            'class_id' => $this->access->classIdForStudent($studentId, (int) $event->semester_id) ?: $classId,
                            'reward_id' => $rewardId,
                        ]);
                        EventPointApplication::create([
                            'event_id' => $event->id,
                            'event_result_id' => $result->id,
                            'event_registration_id' => $result->event_registration_id,
                            'event_team_id' => $result->event_team_id,
                            'application_type' => 'reward',
                            'student_id' => $studentId,
                            'class_id' => $this->access->classIdForStudent($studentId, (int) $event->semester_id) ?: $classId,
                            'reward_id' => $rewardId,
                            'points' => 0,
                            'applied_by' => $request->user()?->id,
                            'applied_at' => now(),
                        ]);
                        $applied['rewards']++;
                    }
                }
            }

            $event->forceFill([
                'status' => 'summarized',
                'summary_report' => $summaryReport ?? $event->summary_report,
                'summarized_by' => $request->user()?->id,
                'summarized_at' => now(),
            ])->save();

            Auditor::record('events.summarized', $event, $before, [
                'status' => $event->status,
                'summary_report' => $event->summary_report,
                'summarized_by' => $event->summarized_by,
                'summarized_at' => $event->summarized_at,
            ], $request, ['applied' => $applied]);
        });

        return $applied;
    }

    public function awardPayload(EventAward $award): array
    {
        return [
            'id' => $award->id,
            'event_id' => $award->event_id,
            'event_result_id' => $award->event_result_id,
            'event_category_id' => $award->event_category_id,
            'category_name' => $award->result?->category?->name,
            'participant_name' => $award->result?->team?->name ?? $award->result?->student?->full_name ?? $award->result?->registration?->participant_name,
            'award_type' => $award->award_type,
            'rank' => $award->rank,
            'title' => $award->title,
            'description' => $award->description,
            'awarded_date' => $award->awarded_date?->toDateString(),
            'recipient_count' => $award->recipients->count(),
        ];
    }

    private function createConductRecord(Request $request, SchoolEvent $event, EventResult $result, ConductRule $rule, int $studentId, int $classId, int $points): ConductRecord
    {
        $record = ConductRecord::create([
            'school_year_id' => $event->school_year_id,
            'semester_id' => $event->semester_id,
            'class_id' => $classId ?: null,
            'student_id' => $studentId,
            'conduct_rule_id' => $rule->id,
            'points' => $points,
            'recorded_date' => $event->ends_at?->toDateString() ?? now()->toDateString(),
            'description' => 'Tự động cộng điểm từ hội thi/hội thao: '.$event->title,
            'note' => $result->award_title,
            'status' => 'approved',
            'recorded_by' => $request->user()?->id,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'metadata' => [
                'source' => 'event_summary',
                'event_id' => $event->id,
                'event_result_id' => $result->id,
            ],
        ]);

        if ($event->semester_id) {
            $summary = $this->conductScores->ensureSummary((int) $event->school_year_id, (int) $event->semester_id, $classId, $studentId);
            $this->conductScores->recalculate($summary);
        }

        Auditor::record('conduct_records.created_from_event', $record, null, $record->fresh()->getAttributes(), $request);

        return $record;
    }

    private function applyClassScore(Request $request, SchoolEvent $event, EventResult $result, int $classId, float $points): EventClassScore
    {
        $score = EventClassScore::query()->firstOrNew([
            'event_id' => $event->id,
            'event_result_id' => $result->id,
            'class_id' => $classId,
        ]);

        $score->fill([
            'event_category_id' => $result->event_category_id,
            'score' => ($score->exists ? (float) $score->score : 0) + $points,
            'note' => trim(($score->note ? $score->note."\n" : '').'Cộng từ kết quả #'.$result->id),
            'applied_by' => $request->user()?->id,
            'applied_at' => now(),
        ])->save();

        return $score;
    }

    private function ensureAward(Request $request, SchoolEvent $event, EventResult $result): EventAward
    {
        return EventAward::query()->firstOrCreate([
            'event_id' => $event->id,
            'event_result_id' => $result->id,
            'title' => $result->award_title,
        ], [
            'event_category_id' => $result->event_category_id,
            'event_team_id' => $result->event_team_id,
            'student_id' => $result->student_id,
            'class_id' => $this->classIdForResult($result),
            'award_type' => 'ranked',
            'rank' => $result->rank,
            'description' => 'Tạo tự động khi tổng kết sự kiện',
            'awarded_date' => now()->toDateString(),
            'awarded_by' => $request->user()?->id,
        ]);
    }

    private function createReward(Request $request, SchoolEvent $event, EventResult $result, int $studentId): int
    {
        $rewardTypeId = DB::table('reward_types')->where('code', 'EVENT_AWARD')->value('id');
        if (! $rewardTypeId) {
            $rewardTypeId = DB::table('reward_types')->insertGetId([
                'code' => 'EVENT_AWARD',
                'name' => 'Khen thưởng hội thi/hội thao',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('rewards')->insertGetId([
            'school_year_id' => $event->school_year_id,
            'semester_id' => $event->semester_id,
            'reward_type_id' => $rewardTypeId,
            'student_id' => $studentId,
            'title' => $result->award_title ?: 'Khen thưởng hội thi/hội thao',
            'issued_date' => $event->ends_at?->toDateString() ?? now()->toDateString(),
            'description' => 'Tự động tạo từ sự kiện: '.$event->title,
            'status' => 'approved',
            'issued_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function studentIdsForResult(EventResult $result, SchoolEvent $event): Collection
    {
        if ($result->student_id) {
            return collect([(int) $result->student_id]);
        }

        if ($result->team) {
            return $result->team->members()->pluck('student_id')->unique()->values();
        }

        if ($result->registration?->registration_type === 'class' && $result->registration->class_id) {
            return DB::table('student_class_enrollments')
                ->where('class_id', $result->registration->class_id)
                ->when($event->semester_id, fn ($query) => $query->where('semester_id', $event->semester_id))
                ->where('status', 'active')
                ->pluck('student_id')
                ->unique()
                ->values();
        }

        if ($result->registration?->student_id) {
            return collect([(int) $result->registration->student_id]);
        }

        return collect();
    }

    private function classIdForResult(EventResult $result): ?int
    {
        if ($result->registration?->class_id) {
            return (int) $result->registration->class_id;
        }

        if ($result->team?->class_id) {
            return (int) $result->team->class_id;
        }

        if ($result->student_id && $result->event?->semester_id) {
            return $this->access->classIdForStudent((int) $result->student_id, (int) $result->event->semester_id);
        }

        return null;
    }

    private function alreadyApplied(EventResult $result, string $type, ?int $studentId, ?int $classId): bool
    {
        return EventPointApplication::query()
            ->where('event_result_id', $result->id)
            ->where('application_type', $type)
            ->when($studentId, fn (Builder $query): Builder => $query->where('student_id', $studentId))
            ->when($classId, fn (Builder $query): Builder => $query->where('class_id', $classId))
            ->exists();
    }

    private function eventConductRule(): ConductRule
    {
        return ConductRule::query()->firstOrCreate(
            ['code' => 'EVENT_CONTEST_PARTICIPATION'],
            [
                'name' => 'Tham gia/đạt giải hội thi hội thao',
                'points' => 5,
                'rule_type' => 'bonus',
                'severity' => 'normal',
                'requires_approval' => false,
                'description' => 'Tự động cộng từ module hội thi/hội thao',
                'sort_order' => 100,
                'status' => 'active',
            ]
        );
    }
}
