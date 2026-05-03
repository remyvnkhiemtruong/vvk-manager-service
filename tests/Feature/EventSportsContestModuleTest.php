<?php

namespace Tests\Feature;

use App\Models\ConductRecord;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventResult;
use App\Models\EventTeam;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventSportsContestModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_football_group_standings_use_win_draw_loss_points(): void
    {
        $this->seed();
        $btc = User::where('username', 'doantruong')->firstOrFail();
        [$event, $category, $home, $away] = $this->sportFixture('football');

        $match = EventMatch::create([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'group_code' => 'A',
            'round' => 'Group',
            'bracket_round' => 'group',
            'match_order' => 1,
            'status' => 'scheduled',
        ]);

        $this->actingAs($btc)
            ->post('/events/matches/'.$match->id.'/score', [
                'home_score' => 2,
                'away_score' => 1,
            ])
            ->assertSessionHasNoErrors();

        $homeStanding = DB::table('event_group_standings')->where('event_team_id', $home->id)->first();
        $awayStanding = DB::table('event_group_standings')->where('event_team_id', $away->id)->first();

        $this->assertEquals(3.0, (float) $homeStanding->points);
        $this->assertEquals(1.0, (float) $homeStanding->score_diff);
        $this->assertSame(1, (int) $homeStanding->rank);
        $this->assertEquals(0.0, (float) $awayStanding->points);
    }

    public function test_best_of_three_sports_store_sets_and_standings(): void
    {
        $this->seed();
        $btc = User::where('username', 'doantruong')->firstOrFail();
        [$event, $category, $home, $away] = $this->sportFixture('volleyball_best_of_three');

        $match = EventMatch::create([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'group_code' => 'A',
            'round' => 'Group',
            'bracket_round' => 'group',
            'match_order' => 1,
            'status' => 'scheduled',
        ]);

        $this->actingAs($btc)
            ->post('/events/matches/'.$match->id.'/score', [
                'sets' => [
                    ['set_number' => 1, 'home_score' => 25, 'away_score' => 20],
                    ['set_number' => 2, 'home_score' => 19, 'away_score' => 25],
                    ['set_number' => 3, 'home_score' => 15, 'away_score' => 12],
                ],
            ])
            ->assertSessionHasNoErrors();

        $match->refresh();
        $standing = DB::table('event_group_standings')->where('event_team_id', $home->id)->first();

        $this->assertSame(2, (int) $match->home_sets_won);
        $this->assertSame(1, (int) $match->away_sets_won);
        $this->assertSame(3, DB::table('event_match_sets')->where('event_match_id', $match->id)->count());
        $this->assertEquals(3.0, (float) $standing->points);
        $this->assertEquals(1.0, (float) $standing->set_diff);
    }

    public function test_judge_scoring_uses_average_of_all_judges(): void
    {
        $this->seed();
        $btc = User::where('username', 'doantruong')->firstOrFail();
        [$event, $category, $team] = $this->judgedFixture(false);
        $criteria = $category->criteria()->orderBy('id')->get();
        $judgeOne = DB::table('event_judges')->insertGetId(['event_id' => $event->id, 'judge_name' => 'Judge 1', 'role' => 'judge', 'created_at' => now(), 'updated_at' => now()]);
        $judgeTwo = DB::table('event_judges')->insertGetId(['event_id' => $event->id, 'judge_name' => 'Judge 2', 'role' => 'judge', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($btc)
            ->post('/events/'.$event->id.'/scoring', [
                'event_category_id' => $category->id,
                'event_team_id' => $team->id,
                'event_judge_id' => $judgeOne,
                'scores' => $criteria->map(fn ($criterion): array => ['event_category_criterion_id' => $criterion->id, 'score' => 8])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($btc)
            ->post('/events/'.$event->id.'/scoring', [
                'event_category_id' => $category->id,
                'event_team_id' => $team->id,
                'event_judge_id' => $judgeTwo,
                'scores' => $criteria->map(fn ($criterion): array => ['event_category_criterion_id' => $criterion->id, 'score' => 10])->all(),
            ])
            ->assertSessionHasNoErrors();

        $result = EventResult::where('event_id', $event->id)->where('event_team_id', $team->id)->firstOrFail();

        $this->assertEquals(45.0, (float) $result->score);
        $this->assertSame(1, (int) $result->rank);
    }

    public function test_summarize_applies_conduct_class_scores_and_rewards_once(): void
    {
        $this->seed();
        $btc = User::where('username', 'doantruong')->firstOrFail();
        [$event, $category, $team] = $this->judgedFixture(false);
        $memberIds = $team->members()->pluck('student_id')->values();

        $result = EventResult::create([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
            'event_team_id' => $team->id,
            'rank' => 1,
            'score' => 50,
            'award_title' => 'Giải nhất demo',
            'conduct_points' => 7,
            'class_points' => 14,
            'status' => 'published',
        ]);

        $this->actingAs($btc)
            ->post('/events/'.$event->id.'/summarize', ['summary_report' => 'Tổng kết demo'])
            ->assertSessionHasNoErrors();

        $this->assertSame($memberIds->count(), ConductRecord::whereIn('student_id', $memberIds)->where('description', 'like', '%'.$event->title.'%')->count());
        $this->assertEquals(14.0, (float) DB::table('event_class_scores')->where('event_result_id', $result->id)->value('score'));
        $this->assertSame($memberIds->count(), DB::table('event_point_applications')->where('event_result_id', $result->id)->where('application_type', 'reward')->count());

        $this->actingAs($btc)
            ->post('/events/'.$event->id.'/summarize', ['summary_report' => 'Tổng kết lần hai'])
            ->assertSessionHasNoErrors();

        $this->assertSame($memberIds->count(), ConductRecord::whereIn('student_id', $memberIds)->where('description', 'like', '%'.$event->title.'%')->count());
        $this->assertEquals(14.0, (float) DB::table('event_class_scores')->where('event_result_id', $result->id)->value('score'));
        $this->assertSame($memberIds->count(), DB::table('event_point_applications')->where('event_result_id', $result->id)->where('application_type', 'reward')->count());
    }

    private function sportFixture(string $sportRule): array
    {
        [$year, $semester] = $this->activeCalendar();
        $event = SchoolEvent::create([
            'school_year_id' => $year->id,
            'semester_id' => $semester->id,
            'title' => 'Hội thao test '.$sportRule,
            'event_type' => 'football',
            'organizer_unit' => 'BTC test',
            'target_audience' => 'all_students',
            'registration_modes' => ['team'],
            'status' => 'in_progress',
            'created_by' => User::where('username', 'doantruong')->value('id'),
        ]);
        $category = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Nội dung test',
            'participation_type' => 'team',
            'scoring_mode' => 'sport',
            'sport_rule' => $sportRule,
            'status' => 'active',
        ]);
        $classes = SchoolClass::take(2)->get();
        $teams = $classes->map(fn (SchoolClass $class, int $index): EventTeam => EventTeam::create([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
            'class_id' => $class->id,
            'name' => 'Đội test '.($index + 1),
            'status' => 'approved',
            'group_code' => 'A',
            'seed_number' => $index + 1,
        ]));

        return [$event, $category, $teams[0], $teams[1]];
    }

    private function judgedFixture(bool $dropExtreme): array
    {
        [$year, $semester] = $this->activeCalendar();
        $event = SchoolEvent::create([
            'school_year_id' => $year->id,
            'semester_id' => $semester->id,
            'title' => 'Hội thi STEM test',
            'event_type' => 'stem',
            'organizer_unit' => 'BTC test',
            'target_audience' => 'all_students',
            'registration_modes' => ['team'],
            'conduct_points_per_student' => 5,
            'class_competition_points' => 10,
            'status' => 'ended',
            'created_by' => User::where('username', 'doantruong')->value('id'),
        ]);
        $category = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'STEM test',
            'participation_type' => 'team',
            'scoring_mode' => 'judged',
            'sport_rule' => 'judged_average',
            'drop_extreme_scores' => $dropExtreme,
            'status' => 'active',
        ]);
        foreach (['Chất lượng', 'Sáng tạo', 'Trình bày', 'Kỷ luật', 'Ý nghĩa'] as $index => $name) {
            DB::table('event_category_criteria')->insert([
                'event_category_id' => $category->id,
                'name' => $name,
                'max_score' => 10,
                'weight' => 1,
                'order_index' => $index + 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $class = SchoolClass::firstOrFail();
        $students = Student::query()
            ->whereExists(function ($query) use ($class, $semester): void {
                $query->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.class_id', $class->id)
                    ->where('student_class_enrollments.semester_id', $semester->id)
                    ->where('student_class_enrollments.status', 'active');
            })
            ->take(3)
            ->get();
        $team = EventTeam::create([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
            'class_id' => $class->id,
            'captain_student_id' => $students->first()->id,
            'name' => 'Đội STEM test',
            'status' => 'approved',
        ]);
        foreach ($students as $student) {
            DB::table('event_team_members')->insert([
                'event_team_id' => $team->id,
                'student_id' => $student->id,
                'role' => 'member',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$event, $category->fresh('criteria'), $team->fresh('members')];
    }

    private function activeCalendar(): array
    {
        return [
            SchoolYear::where('is_active', true)->firstOrFail(),
            Semester::where('is_active', true)->firstOrFail(),
        ];
    }
}
