<?php

namespace App\Services\Assessment;

use App\Models\ClassEnrollment;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\ScoreCategory;
use App\Models\ScoreColumn;
use App\Models\ScoreEntry;
use App\Models\ScoreLockRequest;
use App\Models\ScoreRevision;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Auth\ResourceScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScorebookService
{
    public function __construct(
        private readonly ResourceScope $scope,
        private readonly ScoreFormulaService $formula
    ) {
    }

    public function lookups(Request $request): array
    {
        return [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->get(['id', 'name', 'is_active']),
            'semesters' => Semester::query()->orderByDesc('school_year_id')->orderBy('term_number')->get(['id', 'school_year_id', 'name', 'term_number', 'is_active']),
            'classes' => $this->scope->scope($request, 'classes', SchoolClass::query())
                ->orderBy('name')
                ->get(['id', 'school_year_id', 'name', 'code']),
            'subjects' => $this->scopedSubjectQuery($request->user())
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'assessment_mode']),
            'scoreTypes' => ScoreCategory::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'weight', 'input_type', 'counts_toward_average']),
        ];
    }

    public function defaultFilters(Request $request, array $filters = []): array
    {
        $lookups = $this->lookups($request);
        $activeYear = $lookups['schoolYears']->firstWhere('is_active', true) ?? $lookups['schoolYears']->first();
        $activeSemester = $lookups['semesters']->firstWhere('is_active', true) ?? $lookups['semesters']->first();

        return [
            'school_year_id' => (int) ($filters['school_year_id'] ?? $activeYear?->id ?? 0),
            'semester_id' => (int) ($filters['semester_id'] ?? $activeSemester?->id ?? 0),
            'class_id' => (int) ($filters['class_id'] ?? $lookups['classes']->first()?->id ?? 0),
            'subject_id' => (int) ($filters['subject_id'] ?? $lookups['subjects']->first()?->id ?? 0),
        ];
    }

    public function scorebook(Request $request, array $filters): array
    {
        $filters = $this->defaultFilters($request, $filters);

        if (! $filters['school_year_id'] || ! $filters['semester_id'] || ! $filters['class_id'] || ! $filters['subject_id']) {
            return [
                'filters' => $filters,
                'students' => [],
                'columns' => [],
                'scores' => [],
                'averages' => [],
                'can' => $this->abilities($request),
            ];
        }

        $this->assertCanViewScorebook($request, $filters['class_id'], $filters['subject_id'], $filters['semester_id']);

        $subject = Subject::findOrFail($filters['subject_id']);
        $students = $this->studentsForClass($filters['class_id'], $filters['semester_id']);
        $columns = $this->columnsFor($filters)->get();
        $scores = ScoreEntry::query()
            ->with(['category', 'scoreColumn.scoreType'])
            ->where('school_year_id', $filters['school_year_id'])
            ->where('semester_id', $filters['semester_id'])
            ->where('class_id', $filters['class_id'])
            ->where('subject_id', $filters['subject_id'])
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('score_column_id', $columns->pluck('id'))
            ->get();

        $scoreMap = [];
        foreach ($scores as $score) {
            $scoreMap[$score->student_id][$score->score_column_id] = $this->scorePayload($score);
        }

        $averages = [];
        foreach ($students as $student) {
            $averages[$student->id] = $this->formula->averageForEntries($scores->where('student_id', $student->id), $subject);
        }

        return [
            'filters' => $filters,
            'students' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
            ])->values(),
            'columns' => $columns->map(fn (ScoreColumn $column): array => $this->columnPayload($column))->values(),
            'scores' => $scoreMap,
            'averages' => $averages,
            'subject' => Arr::only($subject->toArray(), ['id', 'code', 'name', 'assessment_mode']),
            'can' => $this->abilities($request, $filters),
        ];
    }

    public function createColumn(Request $request, array $data): ScoreColumn
    {
        $this->assertCanManageColumns($request);

        return DB::transaction(function () use ($request, $data): ScoreColumn {
            $data['lock_status'] ??= 'open';
            $data['status'] ??= 'active';
            $column = ScoreColumn::create($this->columnData($data));
            Auditor::record('score_columns.created', $column, null, $column->fresh()->getAttributes(), $request);

            return $column->fresh(['scoreType', 'schoolClass', 'subject', 'semester']);
        });
    }

    public function updateColumn(Request $request, ScoreColumn $column, array $data): ScoreColumn
    {
        $this->assertCanManageColumns($request);

        return DB::transaction(function () use ($request, $column, $data): ScoreColumn {
            $before = $column->getAttributes();
            $column->fill($this->columnData($data))->save();
            Auditor::record('score_columns.updated', $column, $before, $column->fresh()->getAttributes(), $request);

            return $column->fresh(['scoreType', 'schoolClass', 'subject', 'semester']);
        });
    }

    public function deleteColumn(Request $request, ScoreColumn $column): void
    {
        $this->assertCanManageColumns($request);

        DB::transaction(function () use ($request, $column): void {
            if ($column->scores()->exists()) {
                throw ValidationException::withMessages(['score_column_id' => 'Cot diem da co diem, khong the xoa.']);
            }

            $before = $column->getAttributes();
            $column->delete();
            Auditor::record('score_columns.deleted', $column, $before, null, $request);
        });
    }

    public function bulkUpsert(Request $request, array $data, string $source = 'manual'): array
    {
        $filters = [
            'school_year_id' => (int) $data['school_year_id'],
            'semester_id' => (int) $data['semester_id'],
            'class_id' => (int) $data['class_id'],
            'subject_id' => (int) $data['subject_id'],
        ];

        $this->assertCanEnterScores($request, $filters['class_id'], $filters['subject_id'], $filters['semester_id']);

        $rows = collect($data['scores'] ?? []);
        $reason = trim((string) ($data['revision_reason'] ?? ''));
        $columns = $this->columnsFor($filters)->with('scoreType')->get()->keyBy('id');
        $studentIds = $this->studentsForClass($filters['class_id'], $filters['semester_id'])->pluck('id')->flip();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        DB::transaction(function () use ($request, $rows, $reason, $columns, $studentIds, $filters, $source, &$created, &$updated, &$unchanged): void {
            foreach ($rows as $index => $row) {
                $studentId = (int) ($row['student_id'] ?? 0);
                $columnId = (int) ($row['score_column_id'] ?? 0);

                if (! $studentIds->has($studentId) || ! $columns->has($columnId)) {
                    throw ValidationException::withMessages(['scores' => 'Dong '.($index + 1).' khong thuoc lop, mon hoac hoc ky dang nhap.']);
                }

                $column = $columns[$columnId];

                if ($column->lock_status !== 'open') {
                    throw ValidationException::withMessages(['scores' => 'Cot '.$column->code.' dang khoa, can mo khoa truoc khi sua diem.']);
                }

                $score = ScoreEntry::query()
                    ->where('student_id', $studentId)
                    ->where('score_column_id', $columnId)
                    ->first();

                $payload = $this->scoreData($filters, $studentId, $column, $row, $request);

                if ($score) {
                    $before = $this->scoreSnapshot($score);
                    $score->fill($payload);

                    if (! $score->isDirty()) {
                        $unchanged++;
                        continue;
                    }

                    if ($reason === '') {
                        throw ValidationException::withMessages(['revision_reason' => 'Can nhap ly do khi sua diem da ton tai.']);
                    }

                    $score->save();
                    $this->recordScoreRevision($request, $score, $before, $this->scoreSnapshot($score->fresh()), $reason, $source, 'student_scores.updated');
                    $updated++;
                    continue;
                }

                $score = ScoreEntry::create($payload);
                $this->recordScoreRevision($request, $score, null, $this->scoreSnapshot($score->fresh()), $reason ?: 'Nhap diem moi', $source, 'student_scores.created');
                $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated, 'unchanged' => $unchanged];
    }

    public function lockColumn(Request $request, ScoreColumn $column): ScoreColumn
    {
        $this->assertCanManageColumns($request);

        return DB::transaction(function () use ($request, $column): ScoreColumn {
            $before = $column->getAttributes();
            $column->forceFill([
                'lock_status' => 'locked',
                'locked_by' => $request->user()?->id,
                'locked_at' => now(),
                'unlock_requested_by' => null,
                'unlock_requested_at' => null,
                'unlock_reason' => null,
            ])->save();

            Auditor::record('score_columns.locked', $column, $before, $column->fresh()->getAttributes(), $request);

            return $column->fresh(['scoreType', 'schoolClass', 'subject', 'semester']);
        });
    }

    public function requestUnlock(Request $request, ScoreColumn $column, string $reason): ScoreColumn
    {
        $this->assertCanRequestUnlock($request, $column);

        if ($column->lock_status === 'open') {
            throw ValidationException::withMessages(['score_column_id' => 'Cot diem dang mo.']);
        }

        return DB::transaction(function () use ($request, $column, $reason): ScoreColumn {
            $before = $column->getAttributes();
            $column->forceFill([
                'lock_status' => 'unlock_requested',
                'unlock_requested_by' => $request->user()?->id,
                'unlock_requested_at' => now(),
                'unlock_reason' => $reason,
            ])->save();

            $lockRequest = ScoreLockRequest::create([
                'score_column_id' => $column->id,
                'requested_by' => $request->user()?->id,
                'status' => 'pending',
                'reason' => $reason,
                'requested_at' => now(),
            ]);

            Auditor::record('score_columns.unlock_requested', $column, $before, $column->fresh()->getAttributes(), $request, ['score_lock_request_id' => $lockRequest->id]);

            return $column->fresh(['scoreType', 'schoolClass', 'subject', 'semester']);
        });
    }

    public function approveUnlock(Request $request, ScoreColumn $column, ?string $note = null): ScoreColumn
    {
        $this->assertCanManageColumns($request);

        return DB::transaction(function () use ($request, $column, $note): ScoreColumn {
            $before = $column->getAttributes();
            $requestRow = $column->lockRequests()->where('status', 'pending')->latest('id')->first();

            if ($requestRow) {
                $requestRow->forceFill([
                    'status' => 'approved',
                    'resolved_by' => $request->user()?->id,
                    'resolved_at' => now(),
                    'resolution_note' => $note,
                ])->save();
            }

            $column->forceFill([
                'lock_status' => 'open',
                'unlock_requested_by' => null,
                'unlock_requested_at' => null,
                'unlock_reason' => null,
            ])->save();

            Auditor::record('score_columns.unlock_approved', $column, $before, $column->fresh()->getAttributes(), $request, ['score_lock_request_id' => $requestRow?->id]);

            return $column->fresh(['scoreType', 'schoolClass', 'subject', 'semester']);
        });
    }

    public function rejectUnlock(Request $request, ScoreColumn $column, ?string $note = null): ScoreColumn
    {
        $this->assertCanManageColumns($request);

        return DB::transaction(function () use ($request, $column, $note): ScoreColumn {
            $before = $column->getAttributes();
            $requestRow = $column->lockRequests()->where('status', 'pending')->latest('id')->first();

            if ($requestRow) {
                $requestRow->forceFill([
                    'status' => 'rejected',
                    'resolved_by' => $request->user()?->id,
                    'resolved_at' => now(),
                    'resolution_note' => $note,
                ])->save();
            }

            $column->forceFill([
                'lock_status' => 'locked',
                'unlock_requested_by' => null,
                'unlock_requested_at' => null,
                'unlock_reason' => null,
            ])->save();

            Auditor::record('score_columns.unlock_rejected', $column, $before, $column->fresh()->getAttributes(), $request, ['score_lock_request_id' => $requestRow?->id]);

            return $column->fresh(['scoreType', 'schoolClass', 'subject', 'semester']);
        });
    }

    public function revisions(Request $request, array $filters): LengthAwarePaginator
    {
        $allowedScores = $this->scope->scope($request, 'student_scores', ScoreEntry::query())->select('student_scores.id');
        $query = ScoreRevision::query()
            ->with(['changedBy:id,name', 'score.student:id,student_code,full_name', 'score.subject:id,name', 'score.scoreColumn:id,code,name'])
            ->whereIn('student_score_id', $allowedScores);

        foreach (['student_id', 'class_id', 'subject_id', 'semester_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->whereHas('score', fn (Builder $builder): Builder => $builder->where($filter, $filters[$filter]));
            }
        }

        return $query
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100))
            ->withQueryString()
            ->through(fn (ScoreRevision $revision): array => $this->revisionPayload($revision));
    }

    public function studentScores(Request $request, Student $student, array $filters = []): array
    {
        $this->assertCanViewStudentScores($request, $student);

        $query = $this->scope->scope($request, 'student_scores', ScoreEntry::query())
            ->with(['subject', 'category', 'scoreColumn.scoreType', 'semester', 'schoolClass'])
            ->where('student_id', $student->id);

        foreach (['school_year_id', 'semester_id', 'subject_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        $scores = $query->orderByDesc('semester_id')->orderBy('subject_id')->get();

        return [
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
            ],
            'scores' => $scores->map(fn (ScoreEntry $score): array => $this->scorePayload($score))->values(),
            'averages' => $scores
                ->groupBy('subject_id')
                ->map(fn ($entries) => $this->formula->averageForEntries($entries, $entries->first()?->subject))
                ->all(),
        ];
    }

    public function scoreEntryQueryForReports(Request $request): Builder
    {
        return $this->scope->scope($request, 'student_scores', ScoreEntry::query());
    }

    private function columnsFor(array $filters): Builder
    {
        return ScoreColumn::query()
            ->with(['scoreType', 'schoolClass', 'subject', 'semester'])
            ->where('school_year_id', $filters['school_year_id'])
            ->where('semester_id', $filters['semester_id'])
            ->where('subject_id', $filters['subject_id'])
            ->where(function (Builder $builder) use ($filters): void {
                $builder->where('class_id', $filters['class_id'])->orWhereNull('class_id');
            })
            ->where('status', 'active')
            ->orderBy('order_index')
            ->orderBy('id');
    }

    private function studentsForClass(int $classId, int $semesterId)
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
            ->orderBy('full_name')
            ->get();
    }

    private function scopedSubjectQuery(?User $user): Builder
    {
        $query = Subject::query();

        if (! $user || $this->hasGlobalScope($user) || $user->hasRole('gvcn')) {
            return $query;
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff) {
            return $query->whereIn('id', TeachingAssignment::query()
                ->where('teacher_id', $user->staff->id)
                ->where('status', 'active')
                ->select('subject_id'));
        }

        return $query->whereRaw('1 = 0');
    }

    private function assertCanViewScorebook(Request $request, int $classId, int $subjectId, int $semesterId): void
    {
        $user = $request->user();

        abort_unless($user?->hasPermission('assessment.student_scores.view'), 403);

        if ($this->hasGlobalScope($user)) {
            return;
        }

        if ($user->hasRole('gvcn') && $user->staff) {
            $allowed = SchoolClass::query()
                ->whereKey($classId)
                ->where('homeroom_teacher_id', $user->staff->id)
                ->exists();
            abort_unless($allowed, 403);

            return;
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff) {
            $allowed = TeachingAssignment::query()
                ->where('teacher_id', $user->staff->id)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('semester_id', $semesterId)
                ->where('status', 'active')
                ->exists();
            abort_unless($allowed, 403);

            return;
        }

        abort(403);
    }

    private function assertCanEnterScores(Request $request, int $classId, int $subjectId, int $semesterId): void
    {
        $user = $request->user();

        abort_unless($user?->hasPermission('assessment.student_scores.update'), 403);

        if ($user->hasRole('admin') || $user->hasRole('bgh')) {
            return;
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff) {
            $allowed = TeachingAssignment::query()
                ->where('teacher_id', $user->staff->id)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('semester_id', $semesterId)
                ->where('status', 'active')
                ->exists();
            abort_unless($allowed, 403);

            return;
        }

        abort(403);
    }

    private function assertCanRequestUnlock(Request $request, ScoreColumn $column): void
    {
        $user = $request->user();

        abort_unless($user?->hasPermission('assessment.student_scores.view'), 403);

        if ($this->hasGlobalScope($user)) {
            return;
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff) {
            $allowed = TeachingAssignment::query()
                ->where('teacher_id', $user->staff->id)
                ->where('subject_id', $column->subject_id)
                ->where('semester_id', $column->semester_id)
                ->when($column->class_id, fn (Builder $builder): Builder => $builder->where('class_id', $column->class_id))
                ->where('status', 'active')
                ->exists();
            abort_unless($allowed, 403);

            return;
        }

        if ($user->hasRole('gvcn') && $user->staff && $column->class_id) {
            $allowed = SchoolClass::query()
                ->whereKey($column->class_id)
                ->where('homeroom_teacher_id', $user->staff->id)
                ->exists();
            abort_unless($allowed, 403);

            return;
        }

        abort(403);
    }

    private function assertCanManageColumns(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('assessment.score_columns.update'), 403);
    }

    private function assertCanViewStudentScores(Request $request, Student $student): void
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($user->hasPermission('assessment.student_scores.view') && $this->scope->scope($request, 'student_scores', ScoreEntry::query())->where('student_id', $student->id)->exists()) {
            return;
        }

        if ($user->student?->id === $student->id) {
            return;
        }

        if ($user->guardian && $user->guardian->students()->where('students.id', $student->id)->exists()) {
            return;
        }

        abort(403);
    }

    private function scoreData(array $filters, int $studentId, ScoreColumn $column, array $row, Request $request): array
    {
        $type = $column->scoreType;
        $inputType = $type?->input_type ?? 'numeric';
        $scoreValue = $row['score'] ?? null;
        $comment = trim((string) ($row['comment'] ?? ''));

        if ($inputType === 'comment') {
            $score = null;
        } else {
            $score = $scoreValue === '' || $scoreValue === null ? null : (float) $scoreValue;

            if ($score !== null && ($score < 0 || $score > (float) $column->max_score)) {
                throw ValidationException::withMessages(['scores' => 'Diem cot '.$column->code.' phai tu 0 den '.$column->max_score.'.']);
            }
        }

        return [
            ...$filters,
            'student_id' => $studentId,
            'score_type_id' => $column->score_type_id,
            'score_column_id' => $column->id,
            'score' => $score,
            'comment' => $comment === '' ? null : $comment,
            'status' => $row['status'] ?? 'submitted',
            'note' => $row['note'] ?? null,
            'entered_by' => $request->user()?->id,
        ];
    }

    private function recordScoreRevision(Request $request, ScoreEntry $score, ?array $before, ?array $after, string $reason, string $source, string $action): void
    {
        ScoreRevision::create([
            'student_score_id' => $score->id,
            'before_values' => $before,
            'after_values' => $after,
            'changed_by' => $request->user()?->id,
            'reason' => $reason,
        ]);

        Auditor::record($action, $score, $before, $after, $request, ['source' => $source]);
    }

    private function scoreSnapshot(ScoreEntry $score): array
    {
        return Arr::only($score->fresh()?->getAttributes() ?? $score->getAttributes(), [
            'id',
            'school_year_id',
            'semester_id',
            'class_id',
            'student_id',
            'subject_id',
            'score_type_id',
            'score_column_id',
            'score',
            'comment',
            'status',
            'note',
            'entered_by',
        ]);
    }

    private function scorePayload(ScoreEntry $score): array
    {
        return [
            'id' => $score->id,
            'school_year_id' => $score->school_year_id,
            'semester_id' => $score->semester_id,
            'semester_name' => $score->semester?->name,
            'class_id' => $score->class_id,
            'class_name' => $score->schoolClass?->name,
            'student_id' => $score->student_id,
            'student_code' => $score->student?->student_code,
            'student_name' => $score->student?->full_name,
            'subject_id' => $score->subject_id,
            'subject_name' => $score->subject?->name,
            'score_type_id' => $score->score_type_id,
            'score_type_name' => $score->category?->name,
            'score_column_id' => $score->score_column_id,
            'score_column_code' => $score->scoreColumn?->code,
            'score_column_name' => $score->scoreColumn?->name,
            'input_type' => $score->scoreColumn?->scoreType?->input_type ?? $score->category?->input_type ?? 'numeric',
            'score' => $score->score,
            'comment' => $score->comment,
            'status' => $score->status,
            'note' => $score->note,
        ];
    }

    private function columnPayload(ScoreColumn $column): array
    {
        return [
            'id' => $column->id,
            'school_year_id' => $column->school_year_id,
            'semester_id' => $column->semester_id,
            'semester_name' => $column->semester?->name,
            'class_id' => $column->class_id,
            'class_name' => $column->schoolClass?->name,
            'subject_id' => $column->subject_id,
            'subject_name' => $column->subject?->name,
            'score_type_id' => $column->score_type_id,
            'score_type_code' => $column->scoreType?->code,
            'score_type_name' => $column->scoreType?->name,
            'input_type' => $column->scoreType?->input_type ?? 'numeric',
            'code' => $column->code,
            'name' => $column->name,
            'order_index' => $column->order_index,
            'max_score' => $column->max_score,
            'status' => $column->status,
            'lock_status' => $column->lock_status,
            'unlock_reason' => $column->unlock_reason,
        ];
    }

    private function revisionPayload(ScoreRevision $revision): array
    {
        $score = $revision->score;

        return [
            'id' => $revision->id,
            'student_score_id' => $revision->student_score_id,
            'student_code' => $score?->student?->student_code,
            'student_name' => $score?->student?->full_name,
            'subject_name' => $score?->subject?->name,
            'score_column' => $score?->scoreColumn?->code,
            'before_values' => $revision->before_values,
            'after_values' => $revision->after_values,
            'changed_by' => $revision->changedBy?->name,
            'reason' => $revision->reason,
            'created_at' => $revision->created_at?->toDateTimeString(),
        ];
    }

    private function columnData(array $data): array
    {
        return Arr::only($data, [
            'school_year_id',
            'semester_id',
            'class_id',
            'subject_id',
            'score_type_id',
            'code',
            'name',
            'order_index',
            'max_score',
            'status',
            'lock_status',
        ]);
    }

    private function abilities(Request $request, ?array $filters = null): array
    {
        $user = $request->user();
        $canEnter = false;

        if ($filters) {
            try {
                $this->assertCanEnterScores($request, $filters['class_id'], $filters['subject_id'], $filters['semester_id']);
                $canEnter = true;
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $canEnter = false;
            }
        }

        return [
            'enter' => $canEnter,
            'manage_columns' => (bool) $user?->hasPermission('assessment.score_columns.update'),
            'view_revisions' => (bool) $user?->hasPermission('assessment.student_scores.view'),
        ];
    }

    private function hasGlobalScope(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('bgh') || $user->hasRole('giao_vu');
    }
}
