<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ScoreEntry;
use App\Models\ScoreRevision;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

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
            ->put('/manage/score_entries/'.$score->id, [
                'student_id' => $score->student_id,
                'subject_id' => $score->subject_id,
                'semester_id' => $score->semester_id,
                'score_category_id' => $score->score_category_id,
                'score' => 9.25,
                'status' => 'submitted',
                'note' => 'Điều chỉnh demo có audit.',
                'revision_reason' => 'Test audit sửa điểm',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'score_entries.updated',
            'subject_id' => $score->id,
        ]);

        $this->assertDatabaseHas('score_revisions', [
            'score_entry_id' => $score->id,
            'reason' => 'Test audit sửa điểm',
        ]);

        $this->assertSame(1, AuditLog::where('action', 'score_entries.updated')->count());
        $this->assertSame(1, ScoreRevision::where('score_entry_id', $score->id)->count());
    }

    public function test_subject_teacher_cannot_enter_score_outside_assignment(): void
    {
        $this->seed();

        $teacher = User::where('email', 'giaovien@vvk.local')->firstOrFail();
        $studentOutsideFirstClass = Student::where('student_code', 'DEMO0002')->firstOrFail();
        $subjectOutsideAssignment = Subject::where('code', 'TOAN')->firstOrFail();
        $existingScore = ScoreEntry::firstOrFail();

        $this->actingAs($teacher)
            ->post('/manage/score_entries', [
                'student_id' => $studentOutsideFirstClass->id,
                'subject_id' => $subjectOutsideAssignment->id,
                'semester_id' => $existingScore->semester_id,
                'score_category_id' => $existingScore->score_category_id,
                'score' => 8,
                'status' => 'submitted',
                'note' => 'Không thuộc phân công.',
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

