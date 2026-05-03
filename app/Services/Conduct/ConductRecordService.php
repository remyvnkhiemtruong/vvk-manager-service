<?php

namespace App\Services\Conduct;

use App\Models\ConductEvidence;
use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductScore;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Auth\ResourceScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConductRecordService
{
    public function __construct(
        private readonly ResourceScope $scope,
        private readonly ConductScoreService $scores
    ) {
    }

    public function records(Request $request, array $filters): LengthAwarePaginator
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_records.view'), 403);

        $query = $this->scope->scope($request, 'conduct_records', ConductRecord::query())
            ->with(['student:id,student_code,full_name', 'schoolClass:id,name', 'rule:id,code,name,rule_type,severity,requires_approval', 'recordedBy:id,name', 'approvedBy:id,name', 'evidences'])
            ->when(! empty($filters['school_year_id']), fn (Builder $builder): Builder => $builder->where('school_year_id', (int) $filters['school_year_id']))
            ->when(! empty($filters['semester_id']), fn (Builder $builder): Builder => $builder->where('semester_id', (int) $filters['semester_id']))
            ->when(! empty($filters['class_id']), fn (Builder $builder): Builder => $builder->where('class_id', (int) $filters['class_id']))
            ->when(! empty($filters['student_id']), fn (Builder $builder): Builder => $builder->where('student_id', (int) $filters['student_id']))
            ->when(! empty($filters['status']), fn (Builder $builder): Builder => $builder->where('status', $filters['status']))
            ->when(! empty($filters['rule_type']), fn (Builder $builder): Builder => $builder->whereHas('rule', fn (Builder $rule): Builder => $rule->where('rule_type', $filters['rule_type'])));

        return $query
            ->latest('recorded_date')
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100))
            ->withQueryString()
            ->through(fn (ConductRecord $record): array => $this->recordPayload($record));
    }

    public function pendingApprovals(Request $request, array $filters): LengthAwarePaginator
    {
        return $this->records($request, ['status' => 'pending', ...$filters]);
    }

    public function create(Request $request, array $data, array $files = []): ConductRecord
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_records.create'), 403);

        $rule = ConductRule::query()->where('status', 'active')->findOrFail((int) $data['conduct_rule_id']);
        $data['points'] = (int) ($data['points'] ?? $rule->points);
        $data['class_id'] = $this->classIdForStudent((int) $data['student_id'], (int) $data['semester_id'], (int) ($data['class_id'] ?? 0));
        $this->assertCanRecordFor($request, (int) $data['class_id'], (int) $data['semester_id'], (int) $data['student_id']);

        return DB::transaction(function () use ($request, $data, $rule, $files): ConductRecord {
            $summary = $this->scores->ensureSummary((int) $data['school_year_id'], (int) $data['semester_id'], (int) $data['class_id'], (int) $data['student_id']);
            $this->scores->assertUnlockedForWrite($request, $summary);

            $status = $this->requiresApproval($rule, (int) $data['points']) ? 'pending' : 'approved';
            $payload = [
                ...Arr::only($data, ['school_year_id', 'semester_id', 'class_id', 'student_id', 'conduct_rule_id', 'points', 'recorded_date', 'description', 'note']),
                'status' => $status,
                'recorded_by' => $request->user()?->id,
                'approved_by' => $status === 'approved' ? $request->user()?->id : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'metadata' => ['auto_approved' => $status === 'approved'],
            ];

            $record = ConductRecord::create($payload);
            $this->storeEvidences($request, $record, $files);
            Auditor::record('conduct_records.created', $record, null, $this->recordSnapshot($record->fresh()), $request);

            if ($status === 'approved') {
                $this->scores->recalculate($summary);
            }

            return $record->fresh(['student', 'schoolClass', 'rule', 'recordedBy', 'approvedBy', 'evidences']);
        });
    }

    public function update(Request $request, ConductRecord $record, array $data): ConductRecord
    {
        abort_unless($request->user()?->hasPermission('conduct.conduct_records.update'), 403);
        $this->assertCanRecordFor($request, (int) $record->class_id, (int) $record->semester_id, (int) $record->student_id);

        if ($record->status === 'approved') {
            throw ValidationException::withMessages(['record' => 'Bản ghi đã duyệt cần hủy hoặc điều chỉnh qua nghiệp vụ riêng.']);
        }

        return DB::transaction(function () use ($request, $record, $data): ConductRecord {
            $summary = $this->scores->ensureSummary((int) $record->school_year_id, (int) $record->semester_id, (int) $record->class_id, (int) $record->student_id);
            $this->scores->assertUnlockedForWrite($request, $summary);
            $before = $this->recordSnapshot($record);
            $record->fill(Arr::only($data, ['points', 'recorded_date', 'description', 'note']))->save();
            Auditor::record('conduct_records.updated', $record, $before, $this->recordSnapshot($record->fresh()), $request);

            return $record->fresh(['student', 'schoolClass', 'rule', 'recordedBy', 'approvedBy', 'evidences']);
        });
    }

    public function approve(Request $request, ConductRecord $record, ?string $note = null): ConductRecord
    {
        $this->assertCanApprove($request, $record);

        return DB::transaction(function () use ($request, $record, $note): ConductRecord {
            $summary = $this->scores->ensureSummary((int) $record->school_year_id, (int) $record->semester_id, (int) $record->class_id, (int) $record->student_id);
            $this->scores->assertUnlockedForWrite($request, $summary);
            $before = $this->recordSnapshot($record);
            $record->forceFill([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            DB::table('conduct_approval_logs')->insert([
                'conduct_record_id' => $record->id,
                'conduct_score_summary_id' => $summary->id,
                'approved_by' => $request->user()?->id,
                'resolved_by' => $request->user()?->id,
                'resolved_at' => now(),
                'status' => 'approved',
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->scores->recalculate($summary);
            Auditor::record('conduct_records.approved', $record, $before, $this->recordSnapshot($record->fresh()), $request, ['note' => $note]);

            return $record->fresh(['student', 'schoolClass', 'rule', 'recordedBy', 'approvedBy', 'evidences']);
        });
    }

    public function reject(Request $request, ConductRecord $record, string $reason): ConductRecord
    {
        $this->assertCanApprove($request, $record);

        return DB::transaction(function () use ($request, $record, $reason): ConductRecord {
            $summary = $this->scores->ensureSummary((int) $record->school_year_id, (int) $record->semester_id, (int) $record->class_id, (int) $record->student_id);
            $this->scores->assertUnlockedForWrite($request, $summary);
            $before = $this->recordSnapshot($record);
            $record->forceFill([
                'status' => 'rejected',
                'rejected_by' => $request->user()?->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            DB::table('conduct_approval_logs')->insert([
                'conduct_record_id' => $record->id,
                'conduct_score_summary_id' => $summary->id,
                'resolved_by' => $request->user()?->id,
                'resolved_at' => now(),
                'status' => 'rejected',
                'note' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->scores->recalculate($summary);
            Auditor::record('conduct_records.rejected', $record, $before, $this->recordSnapshot($record->fresh()), $request, ['reason' => $reason]);

            return $record->fresh(['student', 'schoolClass', 'rule', 'recordedBy', 'approvedBy', 'evidences']);
        });
    }

    public function cancel(Request $request, ConductRecord $record, string $reason): ConductRecord
    {
        $user = $request->user();
        abort_unless($user && ($this->canApproveRecord($user, $record) || (int) $record->recorded_by === (int) $user->id), 403);

        return DB::transaction(function () use ($request, $record, $reason): ConductRecord {
            $summary = $this->scores->ensureSummary((int) $record->school_year_id, (int) $record->semester_id, (int) $record->class_id, (int) $record->student_id);
            $this->scores->assertUnlockedForWrite($request, $summary);
            $before = $this->recordSnapshot($record);
            $record->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $request->user()?->id,
                'cancelled_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->scores->recalculate($summary);
            Auditor::record('conduct_records.cancelled', $record, $before, $this->recordSnapshot($record->fresh()), $request, ['reason' => $reason]);

            return $record->fresh(['student', 'schoolClass', 'rule', 'recordedBy', 'approvedBy', 'evidences']);
        });
    }

    public function downloadEvidence(Request $request, ConductRecord $record, ConductEvidence $evidence): StreamedResponse
    {
        abort_unless((int) $evidence->conduct_record_id === (int) $record->id, 404);
        abort_unless($request->user()?->hasPermission('conduct.conduct_records.view'), 403);
        abort_unless($this->scope->scope($request, 'conduct_records', ConductRecord::query())->whereKey($record->id)->exists(), 403);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    public function recordPayload(ConductRecord $record): array
    {
        return [
            'id' => $record->id,
            'school_year_id' => $record->school_year_id,
            'semester_id' => $record->semester_id,
            'class_id' => $record->class_id,
            'class_name' => $record->schoolClass?->name,
            'student_id' => $record->student_id,
            'student_code' => $record->student?->student_code,
            'student_name' => $record->student?->full_name,
            'conduct_rule_id' => $record->conduct_rule_id,
            'rule_code' => $record->rule?->code,
            'rule_name' => $record->rule?->name,
            'rule_type' => $record->rule?->rule_type,
            'severity' => $record->rule?->severity,
            'points' => $record->points,
            'recorded_date' => $record->recorded_date?->toDateString(),
            'description' => $record->description ?: $record->note,
            'status' => $record->status,
            'recorded_by' => $record->recordedBy?->name,
            'approved_by' => $record->approvedBy?->name,
            'rejection_reason' => $record->rejection_reason,
            'evidences' => $record->evidences->map(fn (ConductEvidence $evidence): array => [
                'id' => $evidence->id,
                'original_name' => $evidence->original_name,
                'mime_type' => $evidence->mime_type,
                'size' => $evidence->size,
            ])->values(),
        ];
    }

    private function storeEvidences(Request $request, ConductRecord $record, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('conduct-evidences/'.$record->id, 'local');

            ConductEvidence::create([
                'conduct_record_id' => $record->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => $request->user()?->id,
            ]);
        }
    }

    private function requiresApproval(ConductRule $rule, int $points): bool
    {
        return (bool) $rule->requires_approval
            || in_array($rule->severity, config('school.conduct.approval_severities', ['major', 'serious']), true)
            || abs($points) >= (int) config('school.conduct.approval_point_threshold', 10);
    }

    private function assertCanRecordFor(Request $request, int $classId, int $semesterId, int $studentId): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        abort_unless($this->studentInClass($studentId, $classId, $semesterId), 422, 'Học sinh không thuộc lớp/học kỳ đã chọn.');

        if ($user->hasRole('admin') || $user->hasRole('bgh') || $user->hasRole('giam_thi') || $user->hasRole('doan_truong')) {
            return;
        }

        if ($user->hasRole('gvcn') && $user->staff && SchoolClass::query()->whereKey($classId)->where('homeroom_teacher_id', $user->staff->id)->exists()) {
            return;
        }

        if ($user->hasRole('giao_vien_bo_mon') && $user->staff && TeachingAssignment::query()
            ->where('teacher_id', $user->staff->id)
            ->where('class_id', $classId)
            ->where('semester_id', $semesterId)
            ->where('status', 'active')
            ->exists()) {
            return;
        }

        abort(403, 'Không có quyền ghi nhận rèn luyện cho học sinh này.');
    }

    private function assertCanApprove(Request $request, ConductRecord $record): void
    {
        $user = $request->user();
        abort_unless($user && $this->canApproveRecord($user, $record), 403);
    }

    private function canApproveRecord(User $user, ConductRecord $record): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('bgh')) {
            return true;
        }

        return $user->hasRole('gvcn')
            && $user->staff
            && SchoolClass::query()->whereKey($record->class_id)->where('homeroom_teacher_id', $user->staff->id)->exists();
    }

    private function classIdForStudent(int $studentId, int $semesterId, int $classId): int
    {
        if ($classId > 0) {
            return $classId;
        }

        return (int) DB::table('student_class_enrollments')
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('status', 'active')
            ->value('class_id');
    }

    private function studentInClass(int $studentId, int $classId, int $semesterId): bool
    {
        return DB::table('student_class_enrollments')
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('semester_id', $semesterId)
            ->where('status', 'active')
            ->exists();
    }

    private function recordSnapshot(ConductRecord $record): array
    {
        return Arr::only($record->fresh()?->getAttributes() ?? $record->getAttributes(), [
            'id',
            'school_year_id',
            'semester_id',
            'class_id',
            'student_id',
            'conduct_rule_id',
            'points',
            'recorded_date',
            'description',
            'status',
            'recorded_by',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
        ]);
    }
}
