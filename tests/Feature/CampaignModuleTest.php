<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignCriterion;
use App\Models\CampaignParticipant;
use App\Models\ConductRecord;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_btc_can_create_campaign_with_plan_file_and_default_criteria(): void
    {
        $this->seed();
        Storage::fake('local');

        $btc = User::where('username', 'doantruong')->firstOrFail();
        [$year, $semester] = $this->activeCalendar();

        $this->actingAs($btc)
            ->post('/campaigns', [
                'school_year_id' => $year->id,
                'semester_id' => $semester->id,
                'title' => 'Ngày hội STEM kiểm thử',
                'campaign_type' => 'stem_day',
                'organizer_unit' => 'Đoàn trường',
                'target_audience' => 'all_students',
                'registration_modes' => ['individual', 'team', 'class'],
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'description' => 'Dữ liệu kiểm thử demo',
                'conduct_points_per_student' => 5,
                'class_competition_points' => 10,
                'status' => 'registration_open',
                'plan_file' => UploadedFile::fake()->create('ke-hoach.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $campaign = Campaign::where('title', 'Ngày hội STEM kiểm thử')->firstOrFail();
        $this->assertSame(5, $campaign->criteria()->count());
        $this->assertDatabaseHas('campaign_files', [
            'campaign_id' => $campaign->id,
            'file_type' => 'plan',
            'original_name' => 'ke-hoach.pdf',
        ]);
    }

    public function test_student_can_register_only_when_campaign_is_open(): void
    {
        $this->seed();

        $studentUser = User::where('username', 'hocsinh')->firstOrFail();
        $campaign = $this->campaign(['status' => 'draft']);

        $this->actingAs($studentUser)
            ->post('/campaigns/'.$campaign->id.'/registrations', [
                'participant_type' => 'individual',
                'student_id' => $studentUser->student->id,
            ])
            ->assertStatus(422);

        $campaign->forceFill(['status' => 'registration_open'])->save();

        $this->actingAs($studentUser)
            ->post('/campaigns/'.$campaign->id.'/registrations', [
                'participant_type' => 'individual',
                'student_id' => $studentUser->student->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('campaign_participants', [
            'campaign_id' => $campaign->id,
            'student_id' => $studentUser->student->id,
            'participant_type' => 'individual',
            'status' => 'pending',
        ]);
    }

    public function test_homeroom_teacher_can_only_approve_own_class_registration(): void
    {
        $this->seed();

        $gvcn = User::where('username', 'gvcn')->firstOrFail();
        $campaign = $this->campaign(['status' => 'registration_open']);
        $ownClass = SchoolClass::where('homeroom_teacher_id', $gvcn->staff->id)->firstOrFail();
        $ownStudent = $this->studentForClass($ownClass->id, (int) $campaign->semester_id);
        $outsideClass = SchoolClass::where('homeroom_teacher_id', '<>', $gvcn->staff->id)->firstOrFail();
        $outsideStudent = $this->studentForClass($outsideClass->id, (int) $campaign->semester_id);

        $own = CampaignParticipant::create([
            'campaign_id' => $campaign->id,
            'participant_type' => 'individual',
            'class_id' => $ownClass->id,
            'student_id' => $ownStudent->id,
            'participant_name' => $ownStudent->full_name,
            'status' => 'pending',
        ]);

        $outside = CampaignParticipant::create([
            'campaign_id' => $campaign->id,
            'participant_type' => 'individual',
            'class_id' => $outsideClass->id,
            'student_id' => $outsideStudent->id,
            'participant_name' => $outsideStudent->full_name,
            'status' => 'pending',
        ]);

        $this->actingAs($gvcn)
            ->post('/campaigns/registrations/'.$own->id.'/approve')
            ->assertSessionHasNoErrors();

        $this->actingAs($gvcn)
            ->post('/campaigns/registrations/'.$outside->id.'/approve')
            ->assertForbidden();

        $this->assertSame('approved', $own->fresh()->status);
        $this->assertSame('pending', $outside->fresh()->status);
    }

    public function test_scoring_summary_applies_conduct_and_class_points_once(): void
    {
        $this->seed();

        $btc = User::where('username', 'doantruong')->firstOrFail();
        $campaign = $this->campaign(['status' => 'ended', 'conduct_points_per_student' => 7, 'class_competition_points' => 12]);
        $class = SchoolClass::firstOrFail();
        $student = $this->studentForClass($class->id, (int) $campaign->semester_id);
        $participant = CampaignParticipant::create([
            'campaign_id' => $campaign->id,
            'participant_type' => 'individual',
            'class_id' => $class->id,
            'student_id' => $student->id,
            'participant_name' => $student->full_name,
            'status' => 'approved',
            'approved_by' => $btc->id,
            'approved_at' => now(),
        ]);
        $criteria = $campaign->criteria()->get();

        $this->actingAs($btc)
            ->post('/campaigns/'.$campaign->id.'/results', [
                'campaign_participant_id' => $participant->id,
                'award_title' => 'Giải kiểm thử',
                'conduct_points' => 7,
                'class_points' => 12,
                'status' => 'published',
                'scores' => $criteria->map(fn (CampaignCriterion $criterion): array => [
                    'campaign_criterion_id' => $criterion->id,
                    'score' => 8,
                ])->values()->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($btc)
            ->post('/campaigns/'.$campaign->id.'/summarize', ['summary_report' => 'Tổng kết demo'])
            ->assertSessionHasNoErrors();

        $conductCount = ConductRecord::where('student_id', $student->id)->where('description', 'like', '%'.$campaign->title.'%')->count();
        $classScore = DB::table('campaign_class_scores')->where('campaign_id', $campaign->id)->where('class_id', $class->id)->first();
        $applications = DB::table('campaign_point_applications')->where('campaign_id', $campaign->id)->count();

        $this->assertSame(1, $conductCount);
        $this->assertEquals(12.0, (float) $classScore->score);
        $this->assertSame(2, $applications);

        $this->actingAs($btc)
            ->post('/campaigns/'.$campaign->id.'/summarize', ['summary_report' => 'Tổng kết lần hai'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ConductRecord::where('student_id', $student->id)->where('description', 'like', '%'.$campaign->title.'%')->count());
        $this->assertSame(2, DB::table('campaign_point_applications')->where('campaign_id', $campaign->id)->count());
        $this->assertEquals(12.0, (float) DB::table('campaign_class_scores')->where('campaign_id', $campaign->id)->where('class_id', $class->id)->value('score'));
    }

    public function test_campaign_exports_require_scope_and_return_files(): void
    {
        $this->seed();

        $btc = User::where('username', 'doantruong')->firstOrFail();
        $campaign = Campaign::where('status', 'registration_open')->firstOrFail();

        $this->actingAs($btc)
            ->get('/campaigns/'.$campaign->id.'/exports/participants?format=xlsx')
            ->assertOk();

        $this->actingAs($btc)
            ->get('/campaigns/'.$campaign->id.'/exports/participants?format=pdf')
            ->assertOk();
    }

    private function campaign(array $overrides = []): Campaign
    {
        [$year, $semester] = $this->activeCalendar();
        $campaign = Campaign::create([
            'school_year_id' => $year->id,
            'semester_id' => $semester->id,
            'title' => $overrides['title'] ?? 'Phong trào kiểm thử',
            'campaign_type' => 'stem_day',
            'organizer_unit' => 'Đoàn trường',
            'target_audience' => 'all_students',
            'registration_modes' => ['individual', 'team', 'class'],
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
            'description' => 'Demo only',
            'conduct_points_per_student' => $overrides['conduct_points_per_student'] ?? 5,
            'class_competition_points' => $overrides['class_competition_points'] ?? 10,
            'status' => $overrides['status'] ?? 'registration_open',
            'created_by' => User::where('username', 'doantruong')->value('id'),
        ]);

        foreach (config('school.campaigns.default_criteria') as $index => $criterion) {
            CampaignCriterion::create([
                'campaign_id' => $campaign->id,
                'code' => $criterion['code'],
                'name' => $criterion['name'],
                'max_score' => $criterion['max_score'],
                'weight' => $criterion['weight'],
                'order_index' => $index + 1,
                'status' => 'active',
            ]);
        }

        return $campaign;
    }

    private function activeCalendar(): array
    {
        return [
            SchoolYear::where('is_active', true)->firstOrFail(),
            Semester::where('is_active', true)->firstOrFail(),
        ];
    }

    private function studentForClass(int $classId, int $semesterId): Student
    {
        return Student::query()
            ->whereExists(function ($query) use ($classId, $semesterId): void {
                $query->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.class_id', $classId)
                    ->where('student_class_enrollments.semester_id', $semesterId)
                    ->where('student_class_enrollments.status', 'active');
            })
            ->firstOrFail();
    }
}
