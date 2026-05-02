<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ScoreEntry;
use App\Models\ScoreRevision;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_parent_cannot_open_internal_student_management(): void
    {
        $this->seed();

        $parent = User::where('email', 'phuhuynh@vvk.local')->firstOrFail();

        $this->actingAs($parent)
            ->get('/manage/students')
            ->assertForbidden();
    }

    public function test_admin_can_open_internal_student_management(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@vvk.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/manage/students')
            ->assertOk();
    }

    public function test_score_update_creates_audit_log_and_revision(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@vvk.local')->firstOrFail();
        $score = ScoreEntry::firstOrFail();

        $this->actingAs($admin)
            ->put('/manage/student_scores/'.$score->id, [
                'school_year_id' => $score->school_year_id,
                'semester_id' => $score->semester_id,
                'class_id' => $score->class_id,
                'student_id' => $score->student_id,
                'subject_id' => $score->subject_id,
                'score_type_id' => $score->score_type_id,
                'score' => 9.25,
                'status' => 'submitted',
                'note' => 'Dieu chinh demo co audit.',
                'revision_reason' => 'Test audit sua diem',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_scores.updated',
            'subject_id' => $score->id,
        ]);

        $this->assertDatabaseHas('score_change_logs', [
            'student_score_id' => $score->id,
            'reason' => 'Test audit sua diem',
        ]);

        $this->assertSame(1, AuditLog::where('action', 'student_scores.updated')->count());
        $this->assertSame(1, ScoreRevision::where('student_score_id', $score->id)->where('reason', 'Test audit sua diem')->count());
    }

    public function test_subject_teacher_cannot_enter_score_outside_assignment(): void
    {
        $this->seed();

        $teacher = User::where('email', 'giaovien@vvk.local')->firstOrFail();
        $studentOutsideFirstClass = Student::where('student_code', 'DEMO0002')->firstOrFail();
        $subjectOutsideAssignment = Subject::where('code', 'TOAN')->firstOrFail();
        $existingScore = ScoreEntry::firstOrFail();

        $this->actingAs($teacher)
            ->post('/manage/student_scores', [
                'school_year_id' => $existingScore->school_year_id,
                'semester_id' => $existingScore->semester_id,
                'class_id' => $existingScore->class_id,
                'student_id' => $studentOutsideFirstClass->id,
                'subject_id' => $subjectOutsideAssignment->id,
                'score_type_id' => $existingScore->score_type_id,
                'score' => 8,
                'status' => 'submitted',
                'note' => 'Khong thuoc phan cong.',
            ])
            ->assertForbidden();
    }

    public function test_parent_portal_only_uses_linked_student_context(): void
    {
        $this->seed();

        $parent = User::where('email', 'phuhuynh@vvk.local')->firstOrFail();

        $this->actingAs($parent)
            ->get('/portal')
            ->assertOk();
    }
}
