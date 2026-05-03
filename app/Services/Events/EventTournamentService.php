<?php

namespace App\Services\Events;

use App\Models\EventCategory;
use App\Models\EventGroupStanding;
use App\Models\EventMatch;
use App\Models\EventMatchSet;
use App\Models\EventSchedule;
use App\Models\EventScore;
use App\Models\EventTeam;
use App\Models\SchoolEvent;
use App\Support\Audit\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventTournamentService
{
    public function __construct(private readonly EventAccess $access)
    {
    }

    public function teams(Request $request, SchoolEvent $event, array $filters = []): array
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return EventTeam::query()
            ->with(['category:id,name,sport_rule,scoring_mode', 'schoolClass:id,name', 'captain:id,student_code,full_name', 'members.student:id,student_code,full_name'])
            ->where('event_id', $event->id)
            ->when(! empty($filters['event_category_id']), fn (Builder $query): Builder => $query->where('event_category_id', (int) $filters['event_category_id']))
            ->orderBy('event_category_id')
            ->orderBy('group_code')
            ->orderBy('seed_number')
            ->orderBy('name')
            ->get()
            ->map(fn (EventTeam $team): array => $this->teamPayload($team))
            ->values()
            ->all();
    }

    public function schedules(Request $request, SchoolEvent $event): array
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return EventSchedule::query()
            ->with(['category:id,name'])
            ->where('event_id', $event->id)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (EventSchedule $schedule): array => [
                'id' => $schedule->id,
                'event_id' => $schedule->event_id,
                'event_category_id' => $schedule->event_category_id,
                'category_name' => $schedule->category?->name,
                'name' => $schedule->name,
                'schedule_type' => $schedule->schedule_type,
                'starts_at' => $schedule->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $schedule->ends_at?->format('Y-m-d\TH:i'),
                'location' => $schedule->location,
                'status' => $schedule->status,
            ])
            ->values()
            ->all();
    }

    public function matches(Request $request, SchoolEvent $event, array $filters = []): array
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return EventMatch::query()
            ->with(['category:id,name,sport_rule,scoring_mode', 'schedule:id,starts_at,location', 'homeTeam:id,name,class_id', 'awayTeam:id,name,class_id', 'winner:id,name', 'sets'])
            ->where('event_id', $event->id)
            ->when(! empty($filters['event_category_id']), fn (Builder $query): Builder => $query->where('event_category_id', (int) $filters['event_category_id']))
            ->orderBy('event_category_id')
            ->orderBy('group_code')
            ->orderBy('match_order')
            ->get()
            ->map(fn (EventMatch $match): array => $this->matchPayload($match))
            ->values()
            ->all();
    }

    public function standings(Request $request, SchoolEvent $event, ?EventCategory $category = null): array
    {
        $this->access->assertCanViewEvent($request->user(), $event);

        return EventGroupStanding::query()
            ->with(['team:id,name,class_id,group_code'])
            ->where('event_id', $event->id)
            ->when($category, fn (Builder $query): Builder => $query->where('event_category_id', $category->id))
            ->orderBy('event_category_id')
            ->orderBy('group_code')
            ->orderBy('rank')
            ->get()
            ->map(fn (EventGroupStanding $standing): array => [
                'id' => $standing->id,
                'event_category_id' => $standing->event_category_id,
                'event_team_id' => $standing->event_team_id,
                'team_name' => $standing->team?->name,
                'group_code' => $standing->group_code,
                'played' => $standing->played,
                'won' => $standing->won,
                'drawn' => $standing->drawn,
                'lost' => $standing->lost,
                'points' => $standing->points,
                'score_for' => $standing->score_for,
                'score_against' => $standing->score_against,
                'score_diff' => $standing->score_diff,
                'set_for' => $standing->set_for,
                'set_against' => $standing->set_against,
                'set_diff' => $standing->set_diff,
                'buchholz' => $standing->buchholz,
                'rank' => $standing->rank,
                'needs_manual_rank' => (bool) $standing->needs_manual_rank,
            ])
            ->values()
            ->all();
    }

    public function drawGroups(Request $request, SchoolEvent $event, array $data): array
    {
        abort_unless($request->user()?->hasPermission('activities.event_teams.update'), 403);
        $this->access->assertCanManage($request->user());

        $category = EventCategory::where('event_id', $event->id)->findOrFail((int) $data['event_category_id']);
        $teams = EventTeam::query()
            ->where('event_id', $event->id)
            ->where('event_category_id', $category->id)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();

        if ($teams->count() < 2) {
            throw ValidationException::withMessages(['teams' => 'Cần ít nhất hai đội/thí sinh đã duyệt để bốc thăm.']);
        }

        $groupCount = max(1, min((int) ($data['group_count'] ?? 2), $teams->count()));
        $startAt = $data['starts_at'] ?? $event->starts_at?->format('Y-m-d H:i:s') ?? now()->addDay()->setTime(7, 30)->format('Y-m-d H:i:s');
        $minutesPerMatch = max(10, (int) ($data['minutes_per_match'] ?? 45));
        $location = $data['location'] ?? $event->location;

        DB::transaction(function () use ($request, $event, $category, $teams, $groupCount, $startAt, $minutesPerMatch, $location): void {
            EventMatch::query()->where('event_id', $event->id)->where('event_category_id', $category->id)->delete();
            EventSchedule::query()->where('event_id', $event->id)->where('event_category_id', $category->id)->delete();
            EventGroupStanding::query()->where('event_id', $event->id)->where('event_category_id', $category->id)->delete();

            $groups = collect(range(0, $groupCount - 1))->mapWithKeys(fn (int $index): array => [chr(65 + $index) => collect()]);
            foreach ($teams->values() as $index => $team) {
                $groupCode = chr(65 + ($index % $groupCount));
                $team->forceFill(['group_code' => $groupCode, 'seed_number' => $index + 1])->save();
                $groups[$groupCode]->push($team->fresh());
            }

            $matchOrder = 1;
        $cursor = \Illuminate\Support\Carbon::parse($startAt);
            foreach ($groups as $groupCode => $groupTeams) {
                $pairs = $this->pairings($groupTeams);
                foreach ($pairs as [$home, $away]) {
                    $schedule = EventSchedule::create([
                        'event_id' => $event->id,
                        'event_category_id' => $category->id,
                        'name' => $category->sport_rule === 'chess_swiss' ? 'Ván 1 bảng '.$groupCode : 'Bảng '.$groupCode.' trận '.$matchOrder,
                        'schedule_type' => 'match',
                        'starts_at' => $cursor->copy(),
                        'ends_at' => $cursor->copy()->addMinutes($minutesPerMatch),
                        'location' => $location,
                        'status' => 'scheduled',
                    ]);

                    EventMatch::create([
                        'event_id' => $event->id,
                        'event_category_id' => $category->id,
                        'event_schedule_id' => $schedule->id,
                        'home_team_id' => $home->id,
                        'away_team_id' => $away->id,
                        'group_code' => $groupCode,
                        'round' => $category->sport_rule === 'chess_swiss' ? 'Swiss round 1' : 'Group',
                        'bracket_round' => $category->sport_rule === 'chess_swiss' ? 'swiss_1' : 'group',
                        'match_order' => $matchOrder,
                        'status' => 'scheduled',
                    ]);
                    $matchOrder++;
                    $cursor->addMinutes($minutesPerMatch);
                }
            }

            $this->recalculateStandings($category);
            Auditor::record('event_groups.drawn', $event, null, ['event_category_id' => $category->id, 'group_count' => $groupCount, 'teams' => $teams->pluck('id')->values()], $request);
        });

        return [
            'teams' => $this->teams($request, $event, ['event_category_id' => $category->id]),
            'matches' => $this->matches($request, $event, ['event_category_id' => $category->id]),
            'standings' => $this->standings($request, $event, $category),
        ];
    }

    public function saveSchedule(Request $request, SchoolEvent $event, array $data): EventSchedule
    {
        abort_unless($request->user()?->hasPermission('activities.event_schedules.update'), 403);
        $this->access->assertCanManage($request->user());

        return DB::transaction(function () use ($request, $event, $data): EventSchedule {
            $schedule = empty($data['id'])
                ? new EventSchedule(['event_id' => $event->id])
                : EventSchedule::where('event_id', $event->id)->findOrFail((int) $data['id']);
            $before = $schedule->exists ? $schedule->getAttributes() : null;
            $schedule->fill([
                'event_id' => $event->id,
                'event_category_id' => $data['event_category_id'] ?? null,
                'name' => $data['name'] ?? null,
                'schedule_type' => $data['schedule_type'] ?? 'match',
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? 'scheduled',
            ])->save();

            Auditor::record($before ? 'event_schedules.updated' : 'event_schedules.created', $schedule, $before, $schedule->fresh()->getAttributes(), $request);

            return $schedule;
        });
    }

    public function saveMatchScore(Request $request, EventMatch $match, array $data): EventMatch
    {
        abort_unless($request->user()?->hasPermission('activities.event_results.update'), 403);
        $this->access->assertCanManage($request->user());
        $category = $match->category()->firstOrFail();

        return DB::transaction(function () use ($request, $match, $category, $data): EventMatch {
            $before = $match->getAttributes();
            [$homeScore, $awayScore, $homeSets, $awaySets, $winnerId] = $this->scoreTuple($match, $category, $data);

            $match->forceFill([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'home_sets_won' => $homeSets,
                'away_sets_won' => $awaySets,
                'winner_team_id' => $winnerId,
                'played_at' => $data['played_at'] ?? now(),
                'result_note' => $data['result_note'] ?? null,
                'status' => 'completed',
                'metadata' => ['sport_rule' => $category->sport_rule],
            ])->save();

            $this->syncSets($match, $data['sets'] ?? []);
            $this->syncLegacyScores($match, $homeScore, $awayScore);
            $this->recalculateStandings($category);

            Auditor::record('event_matches.scored', $match, $before, $match->fresh()->getAttributes(), $request);

            return $match->fresh(['category', 'homeTeam', 'awayTeam', 'sets']);
        });
    }

    public function recalculateStandings(EventCategory $category): void
    {
        $teams = EventTeam::query()
            ->where('event_id', $category->event_id)
            ->where('event_category_id', $category->id)
            ->where('status', 'approved')
            ->get();

        $rows = $teams->mapWithKeys(fn (EventTeam $team): array => [$team->id => [
            'team' => $team,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'points' => 0.0,
            'score_for' => 0.0,
            'score_against' => 0.0,
            'score_diff' => 0.0,
            'set_for' => 0.0,
            'set_against' => 0.0,
            'set_diff' => 0.0,
            'buchholz' => 0.0,
            'opponents' => [],
        ]])->all();

        $matches = EventMatch::query()
            ->where('event_category_id', $category->id)
            ->where('status', 'completed')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->get();

        foreach ($matches as $match) {
            if (! array_key_exists((int) $match->home_team_id, $rows) || ! array_key_exists((int) $match->away_team_id, $rows)) {
                continue;
            }

            $this->applyMatchToRows($rows, $match, $category);
        }

        foreach ($rows as $teamId => $row) {
            $rows[$teamId]['score_diff'] = $row['score_for'] - $row['score_against'];
            $rows[$teamId]['set_diff'] = $row['set_for'] - $row['set_against'];
            if (($category->sport_rule ?: '') === 'chess_swiss') {
                $rows[$teamId]['buchholz'] = collect($row['opponents'])
                    ->sum(fn (array $opponent): float => (float) ($rows[$opponent['team_id']]['points'] ?? 0));
            }
        }

        $ranked = collect($rows)->values()->sort(function (array $a, array $b) use ($category): int {
            foreach ($this->sortKeys($category) as $key) {
                $comparison = ($b[$key] <=> $a[$key]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strcmp($a['team']->name, $b['team']->name);
        })->values();

        EventGroupStanding::query()->where('event_category_id', $category->id)->delete();

        foreach ($ranked as $index => $row) {
            $samePrevious = $index > 0 && $this->sameRankTuple($category, $ranked[$index - 1], $row);
            EventGroupStanding::create([
                'event_id' => $category->event_id,
                'event_category_id' => $category->id,
                'event_team_id' => $row['team']->id,
                'group_code' => $row['team']->group_code,
                'played' => $row['played'],
                'won' => $row['won'],
                'drawn' => $row['drawn'],
                'lost' => $row['lost'],
                'points' => $row['points'],
                'score_for' => $row['score_for'],
                'score_against' => $row['score_against'],
                'score_diff' => $row['score_diff'],
                'set_for' => $row['set_for'],
                'set_against' => $row['set_against'],
                'set_diff' => $row['set_diff'],
                'buchholz' => $row['buchholz'] ?? 0,
                'rank' => $index + 1,
                'needs_manual_rank' => $samePrevious,
            ]);
        }
    }

    public function teamPayload(EventTeam $team): array
    {
        return [
            'id' => $team->id,
            'event_id' => $team->event_id,
            'event_category_id' => $team->event_category_id,
            'category_name' => $team->category?->name,
            'class_id' => $team->class_id,
            'class_name' => $team->schoolClass?->name,
            'captain_student_id' => $team->captain_student_id,
            'captain_name' => $team->captain?->full_name,
            'name' => $team->name,
            'group_code' => $team->group_code,
            'seed_number' => $team->seed_number,
            'status' => $team->status,
            'members' => $team->members->map(fn ($member): array => [
                'id' => $member->student_id,
                'student_code' => $member->student?->student_code,
                'full_name' => $member->student?->full_name,
                'role' => $member->role,
            ])->values(),
        ];
    }

    public function matchPayload(EventMatch $match): array
    {
        return [
            'id' => $match->id,
            'event_id' => $match->event_id,
            'event_category_id' => $match->event_category_id,
            'category_name' => $match->category?->name,
            'sport_rule' => $match->category?->sport_rule,
            'event_schedule_id' => $match->event_schedule_id,
            'starts_at' => $match->schedule?->starts_at?->format('Y-m-d\TH:i'),
            'location' => $match->schedule?->location,
            'home_team_id' => $match->home_team_id,
            'home_team_name' => $match->homeTeam?->name,
            'away_team_id' => $match->away_team_id,
            'away_team_name' => $match->awayTeam?->name,
            'group_code' => $match->group_code,
            'round' => $match->round,
            'bracket_round' => $match->bracket_round,
            'match_order' => $match->match_order,
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'home_sets_won' => $match->home_sets_won,
            'away_sets_won' => $match->away_sets_won,
            'winner_team_id' => $match->winner_team_id,
            'winner_name' => $match->winner?->name,
            'played_at' => $match->played_at?->toDateTimeString(),
            'status' => $match->status,
            'result_note' => $match->result_note,
            'sets' => $match->sets->sortBy('set_number')->map(fn (EventMatchSet $set): array => [
                'set_number' => $set->set_number,
                'home_score' => $set->home_score,
                'away_score' => $set->away_score,
                'winner_team_id' => $set->winner_team_id,
            ])->values(),
        ];
    }

    private function pairings(Collection $teams): array
    {
        $pairs = [];
        $values = $teams->values();
        for ($i = 0; $i < $values->count(); $i++) {
            for ($j = $i + 1; $j < $values->count(); $j++) {
                $pairs[] = [$values[$i], $values[$j]];
            }
        }

        return $pairs;
    }

    private function scoreTuple(EventMatch $match, EventCategory $category, array $data): array
    {
        $rule = $category->sport_rule ?: 'football';

        if (in_array($rule, ['volleyball_best_of_three', 'badminton_best_of_three', 'shuttlecock_best_of_three', 'tug_of_war_best_of_three'], true)) {
            $sets = collect($data['sets'] ?? []);
            if ($sets->isEmpty()) {
                throw ValidationException::withMessages(['sets' => 'Cần nhập điểm từng set/lượt.']);
            }

            $homeSets = 0;
            $awaySets = 0;
            $homePoints = 0;
            $awayPoints = 0;
            foreach ($sets as $set) {
                $home = (float) ($set['home_score'] ?? 0);
                $away = (float) ($set['away_score'] ?? 0);
                $homePoints += $home;
                $awayPoints += $away;
                if ($home > $away) {
                    $homeSets++;
                } elseif ($away > $home) {
                    $awaySets++;
                }
            }

            return [$homePoints, $awayPoints, $homeSets, $awaySets, $homeSets > $awaySets ? $match->home_team_id : $match->away_team_id];
        }

        $homeScore = (float) ($data['home_score'] ?? 0);
        $awayScore = (float) ($data['away_score'] ?? 0);
        $winnerId = null;
        if ($homeScore > $awayScore) {
            $winnerId = $match->home_team_id;
        } elseif ($awayScore > $homeScore) {
            $winnerId = $match->away_team_id;
        }

        return [$homeScore, $awayScore, 0, 0, $winnerId];
    }

    private function syncSets(EventMatch $match, array $sets): void
    {
        EventMatchSet::query()->where('event_match_id', $match->id)->delete();
        foreach (array_values($sets) as $index => $set) {
            $home = (float) ($set['home_score'] ?? 0);
            $away = (float) ($set['away_score'] ?? 0);
            EventMatchSet::create([
                'event_match_id' => $match->id,
                'set_number' => $set['set_number'] ?? ($index + 1),
                'home_score' => $home,
                'away_score' => $away,
                'winner_team_id' => $home === $away ? null : ($home > $away ? $match->home_team_id : $match->away_team_id),
            ]);
        }
    }

    private function syncLegacyScores(EventMatch $match, float $homeScore, float $awayScore): void
    {
        EventScore::query()->where('event_match_id', $match->id)->delete();
        foreach ([[$match->home_team_id, $homeScore], [$match->away_team_id, $awayScore]] as [$teamId, $score]) {
            EventScore::create([
                'event_match_id' => $match->id,
                'event_team_id' => $teamId,
                'score' => $score,
                'note' => 'Phase 8 match score',
            ]);
        }
    }

    private function applyMatchToRows(array &$rows, EventMatch $match, EventCategory $category): void
    {
        $home = $rows[$match->home_team_id];
        $away = $rows[$match->away_team_id];
        $rule = $category->sport_rule ?: 'football';
        $homeScore = (float) $match->home_score;
        $awayScore = (float) $match->away_score;
        $homeSet = (float) $match->home_sets_won;
        $awaySet = (float) $match->away_sets_won;

        foreach ([[$match->home_team_id, $awayScore], [$match->away_team_id, $homeScore]] as [$teamId, $opponentScore]) {
            $rows[$teamId]['played']++;
            $rows[$teamId]['opponents'][] = ['team_id' => $teamId === $match->home_team_id ? $match->away_team_id : $match->home_team_id, 'opponent_score' => $opponentScore];
        }

        $rows[$match->home_team_id]['score_for'] += $homeScore;
        $rows[$match->home_team_id]['score_against'] += $awayScore;
        $rows[$match->home_team_id]['set_for'] += $homeSet;
        $rows[$match->home_team_id]['set_against'] += $awaySet;
        $rows[$match->away_team_id]['score_for'] += $awayScore;
        $rows[$match->away_team_id]['score_against'] += $homeScore;
        $rows[$match->away_team_id]['set_for'] += $awaySet;
        $rows[$match->away_team_id]['set_against'] += $homeSet;

        $isChess = $rule === 'chess_swiss';
        $isSetSport = in_array($rule, ['volleyball_best_of_three', 'badminton_best_of_three', 'shuttlecock_best_of_three', 'tug_of_war_best_of_three'], true);
        $homeMetric = $isSetSport ? $homeSet : $homeScore;
        $awayMetric = $isSetSport ? $awaySet : $awayScore;

        if ($homeMetric > $awayMetric) {
            $rows[$match->home_team_id]['won']++;
            $rows[$match->away_team_id]['lost']++;
            $rows[$match->home_team_id]['points'] += $isChess ? 1 : 3;
        } elseif ($awayMetric > $homeMetric) {
            $rows[$match->away_team_id]['won']++;
            $rows[$match->home_team_id]['lost']++;
            $rows[$match->away_team_id]['points'] += $isChess ? 1 : 3;
        } else {
            $rows[$match->home_team_id]['drawn']++;
            $rows[$match->away_team_id]['drawn']++;
            $rows[$match->home_team_id]['points'] += $isChess ? 0.5 : 1;
            $rows[$match->away_team_id]['points'] += $isChess ? 0.5 : 1;
        }

        if ($isChess) {
            $rows[$match->home_team_id]['buchholz'] = ($rows[$match->home_team_id]['buchholz'] ?? 0) + $away['points'];
            $rows[$match->away_team_id]['buchholz'] = ($rows[$match->away_team_id]['buchholz'] ?? 0) + $home['points'];
        }
    }

    private function sortKeys(EventCategory $category): array
    {
        return match ($category->sport_rule) {
            'chess_swiss' => ['points', 'buchholz', 'score_for'],
            'volleyball_best_of_three', 'badminton_best_of_three', 'shuttlecock_best_of_three', 'tug_of_war_best_of_three' => ['points', 'set_diff', 'score_diff', 'score_for'],
            default => ['points', 'score_diff', 'score_for'],
        };
    }

    private function sameRankTuple(EventCategory $category, array $a, array $b): bool
    {
        foreach ($this->sortKeys($category) as $key) {
            if ((float) $a[$key] !== (float) $b[$key]) {
                return false;
            }
        }

        return true;
    }
}
