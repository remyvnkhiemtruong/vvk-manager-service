<?php

namespace Tests\Feature;

use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductScore;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConductScoreModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_subject_teacher_can_record_conduct_for_assigned_class(): void
    {
        $this->seed();

        $teacher = User::where('username', 'giaovien')->firstOrFail();
        $assignment = $this->assignmentFor($teacher);
        $student = $this->studentForClass($assignment->class_id, $assignment->semester_id);
        $rule = ConductRule::where('code', 'PEER_SUPPORT')->firstOrFail();

        $this->actingAs($teacher)
            ->post('/conduct/records', [
                ...$this->filters($assignment),
                'student_id' => $student->id,
                'conduct_rule_id' => $rule->id,
                'points' => $rule->points,
                'recorded_date' => '2026-03-18',
                'description' => 'Hỗ trợ bạn học tập trong giờ phụ đạo',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('conduct_records', [
            'student_id' => $student->id,
            'conduct_rule_id' => $rule->id,
            'status' => 'approved',
        ]);
    }

    public function test_subject_teacher_cannot_record_outside_assignment(): void
    {
        $this->seed();

        $teacher = User::where('username', 'giaovien')->firstOrFail();
        $outside = TeachingAssignment::query()
            ->where('teacher_id', '<>', $teacher->staff->id)
            ->where('status', 'active')
            ->firstOrFail();
        TeachingAssignment::query()
            ->where('teacher_id', $teacher->staff->id)
            ->where('class_id', $outside->class_id)
            ->where('semester_id', $outside->semester_id)
            ->delete();
        $student = $this->studentForClass($outside->class_id, $outside->semester_id);
        $rule = ConductRule::where('code', 'LATE')->firstOrFail();

        $this->actingAs($teacher)
            ->post('/conduct/records', [
                ...$this->filters($outside),
                'student_id' => $student->id,
                'conduct_rule_id' => $rule->id,
                'points' => $rule->points,
                'recorded_date' => '2026-03-18',
                'description' => 'Ngoài phân công',
            ])
            ->assertForbidden();
    }

    public function test_pending_record_is_not_counted_until_homeroom_approval(): void
    {
        $this->seed();

        $gvcn = User::where('username', 'gvcn')->firstOrFail();
        $giamThi = User::where('username', 'giamthi')->firstOrFail();
        $summary = $this->summaryForHomeroomTeacher($gvcn);
        $class = SchoolClass::findOrFail($summary->class_id);
        $student = Student::findOrFail($summary->student_id);
        $rule = ConductRule::where('code', 'FIGHTING')->firstOrFail();
        $beforeScore = $summary->score;

        $this->actingAs($giamThi)
            ->post('/conduct/records', [
                'school_year_id' => $summary->school_year_id,
                'semester_id' => $summary->semester_id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'conduct_rule_id' => $rule->id,
                'points' => $rule->points,
                'recorded_date' => '2026-03-20',
                'description' => 'Vi phạm cần duyệt',
            ])
            ->assertSessionHasNoErrors();

        $record = ConductRecord::latest('id')->firstOrFail();
        $this->assertSame('pending', $record->status);
        $this->assertSame($beforeScore, $summary->fresh()->score);

        $this->actingAs($gvcn)
            ->post('/conduct/records/'.$record->id.'/approve', ['note' => 'Đã xác minh'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $record->fresh()->status);
        $this->assertLessThan($beforeScore, $summary->fresh()->score);
    }

    public function test_manual_adjustment_requires_reason_and_writes_revision_audit(): void
    {
        $this->seed();

        $bgh = User::where('username', 'bgh')->firstOrFail();
        $summary = ConductScore::firstOrFail();

        $this->actingAs($bgh)
            ->put('/conduct/summaries/'.$summary->id.'/adjust', ['points_delta' => 3])
            ->assertSessionHasErrors('reason');

        $this->actingAs($bgh)
            ->put('/conduct/summaries/'.$summary->id.'/adjust', ['points_delta' => 3, 'reason' => 'Bổ sung điểm hoạt động'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('conduct_adjustments', [
            'conduct_score_summary_id' => $summary->id,
            'points_delta' => 3,
            'reason' => 'Bổ sung điểm hoạt động',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conduct_scores.adjusted',
            'subject_id' => $summary->id,
        ]);
    }

    public function test_locked_summary_blocks_regular_record_and_bgh_can_unlock(): void
    {
        $this->seed();

        $gvcn = User::where('username', 'gvcn')->firstOrFail();
        $giamThi = User::where('username', 'giamthi')->firstOrFail();
        $bgh = User::where('username', 'bgh')->firstOrFail();
        $summary = $this->summaryForHomeroomTeacher($gvcn);
        $class = SchoolClass::findOrFail($summary->class_id);
        $student = Student::findOrFail($summary->student_id);
        $rule = ConductRule::where('code', 'LATE')->firstOrFail();

        $this->actingAs($gvcn)
            ->post('/conduct/summaries/'.$summary->id.'/lock')
            ->assertSessionHasNoErrors();

        $this->actingAs($giamThi)
            ->post('/conduct/records', [
                'school_year_id' => $summary->school_year_id,
                'semester_id' => $summary->semester_id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'conduct_rule_id' => $rule->id,
                'points' => $rule->points,
                'recorded_date' => '2026-03-22',
                'description' => 'Sau khi khóa',
            ])
            ->assertForbidden();

        $this->actingAs($bgh)
            ->post('/conduct/summaries/'.$summary->id.'/unlock')
            ->assertSessionHasNoErrors();

        $this->assertSame('open', $summary->fresh()->lock_status);
    }

    public function test_parent_and_student_timeline_is_limited_to_linked_student(): void
    {
        $this->seed();

        $parent = User::where('username', 'phuhuynh')->firstOrFail();
        $linked = $parent->guardian->students()->firstOrFail();
        $outside = Student::whereKeyNot($linked->id)->firstOrFail();

        $this->actingAs($parent)
            ->get('/conduct/students/'.$linked->id)
            ->assertOk();

        $this->actingAs($parent)
            ->get('/conduct/students/'.$outside->id)
            ->assertForbidden();
    }

    private function assignmentFor(User $teacher): TeachingAssignment
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $teacher->staff->id)
            ->where('status', 'active')
            ->firstOrFail();
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

    private function filters(TeachingAssignment $assignment): array
    {
        return [
            'school_year_id' => $assignment->school_year_id,
            'semester_id' => $assignment->semester_id,
            'class_id' => $assignment->class_id,
        ];
    }

    private function summaryForHomeroomTeacher(User $teacher): ConductScore
    {
        return ConductScore::query()
            ->whereExists(function ($query) use ($teacher): void {
                $query->selectRaw('1')
                    ->from('classes')
                    ->whereColumn('classes.id', 'conduct_score_summaries.class_id')
                    ->where('classes.homeroom_teacher_id', $teacher->staff->id);
            })
            ->firstOrFail();
    }
}
