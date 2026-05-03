<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ScoreColumn;
use App\Models\ScoreEntry;
use App\Models\ScoreRevision;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentScoreModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_subject_teacher_can_enter_score_for_assigned_class_subject(): void
    {
        $this->seed();

        $teacher = User::where('username', 'giaovien')->firstOrFail();
        $assignment = $this->assignmentFor($teacher);
        $student = $this->studentForAssignment($assignment);
        $column = $this->columnFor($assignment);

        $this->actingAs($teacher)
            ->put('/assessment/scores/bulk', [
                ...$this->filters($assignment),
                'scores' => [[
                    'student_id' => $student->id,
                    'score_column_id' => $column->id,
                    'score' => 8.5,
                    'status' => 'submitted',
                ]],
                'revision_reason' => 'Nhap diem kiem tra',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_scores', [
            'student_id' => $student->id,
            'score_column_id' => $column->id,
            'score' => 8.5,
        ]);
    }

    public function test_subject_teacher_cannot_enter_score_outside_assignment(): void
    {
        $this->seed();

        $teacher = User::where('username', 'giaovien')->firstOrFail();
        $outside = TeachingAssignment::query()
            ->where('teacher_id', '<>', $teacher->staff->id)
            ->where('status', 'active')
            ->firstOrFail();
        $student = $this->studentForAssignment($outside);
        $column = $this->columnFor($outside);

        $this->actingAs($teacher)
            ->put('/assessment/scores/bulk', [
                ...$this->filters($outside),
                'scores' => [[
                    'student_id' => $student->id,
                    'score_column_id' => $column->id,
                    'score' => 7,
                    'status' => 'submitted',
                ]],
                'revision_reason' => 'Khong thuoc phan cong',
            ])
            ->assertForbidden();
    }

    public function test_locked_column_blocks_teacher_updates_and_unlock_is_audited(): void
    {
        $this->seed();

        $teacher = User::where('username', 'giaovien')->firstOrFail();
        $bgh = User::where('username', 'bgh')->firstOrFail();
        $assignment = $this->assignmentFor($teacher);
        $student = $this->studentForAssignment($assignment);
        $column = $this->columnFor($assignment);
        $column->forceFill(['lock_status' => 'locked'])->save();

        $this->actingAs($teacher)
            ->from('/assessment/entry')
            ->put('/assessment/scores/bulk', [
                ...$this->filters($assignment),
                'scores' => [[
                    'student_id' => $student->id,
                    'score_column_id' => $column->id,
                    'score' => 9,
                ]],
                'revision_reason' => 'Sua cot da khoa',
            ])
            ->assertSessionHasErrors('scores');

        $this->actingAs($teacher)
            ->post('/assessment/score-columns/'.$column->id.'/request-unlock', ['reason' => 'Can sua diem nhap nham'])
            ->assertSessionHasNoErrors();

        $this->actingAs($bgh)
            ->post('/assessment/score-columns/'.$column->id.'/approve-unlock', ['resolution_note' => 'Dong y'])
            ->assertSessionHasNoErrors();

        $this->assertSame('open', $column->fresh()->lock_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'score_columns.unlock_approved']);
    }

    public function test_score_update_creates_revision_and_audit_log(): void
    {
        $this->seed();

        $bgh = User::where('username', 'bgh')->firstOrFail();
        $score = ScoreEntry::query()->whereNotNull('score_column_id')->firstOrFail();

        $this->actingAs($bgh)
            ->put('/assessment/scores/bulk', [
                'school_year_id' => $score->school_year_id,
                'semester_id' => $score->semester_id,
                'class_id' => $score->class_id,
                'subject_id' => $score->subject_id,
                'scores' => [[
                    'student_id' => $score->student_id,
                    'score_column_id' => $score->score_column_id,
                    'score' => 9.25,
                ]],
                'revision_reason' => 'Dieu chinh co ly do',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('score_change_logs', [
            'student_score_id' => $score->id,
            'reason' => 'Dieu chinh co ly do',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_scores.updated', 'subject_id' => $score->id]);
        $this->assertSame(1, ScoreRevision::where('student_score_id', $score->id)->where('reason', 'Dieu chinh co ly do')->count());
    }

    public function test_parent_and_student_score_detail_is_limited_to_linked_student(): void
    {
        $this->seed();

        $parent = User::where('username', 'phuhuynh')->firstOrFail();
        $linked = $parent->guardian->students()->firstOrFail();
        $outside = Student::whereKeyNot($linked->id)->firstOrFail();

        $this->actingAs($parent)
            ->get('/assessment/students/'.$linked->id)
            ->assertOk();

        $this->actingAs($parent)
            ->get('/assessment/students/'.$outside->id)
            ->assertForbidden();
    }

    public function test_export_scores_returns_xlsx(): void
    {
        $this->seed();

        $bgh = User::where('username', 'bgh')->firstOrFail();
        $score = ScoreEntry::query()->whereNotNull('score_column_id')->firstOrFail();

        $this->actingAs($bgh)
            ->get('/assessment/scores/export?'.http_build_query([
                'school_year_id' => $score->school_year_id,
                'semester_id' => $score->semester_id,
                'class_id' => $score->class_id,
                'subject_id' => $score->subject_id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function assignmentFor(User $teacher): TeachingAssignment
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $teacher->staff->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function studentForAssignment(TeachingAssignment $assignment): Student
    {
        return Student::query()
            ->whereExists(function ($query) use ($assignment): void {
                $query->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.class_id', $assignment->class_id)
                    ->where('student_class_enrollments.semester_id', $assignment->semester_id)
                    ->where('student_class_enrollments.status', 'active');
            })
            ->firstOrFail();
    }

    private function columnFor(TeachingAssignment $assignment): ScoreColumn
    {
        return ScoreColumn::query()
            ->where('semester_id', $assignment->semester_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('lock_status', 'open')
            ->firstOrFail();
    }

    private function filters(TeachingAssignment $assignment): array
    {
        return [
            'school_year_id' => $assignment->school_year_id,
            'semester_id' => $assignment->semester_id,
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
        ];
    }
}
