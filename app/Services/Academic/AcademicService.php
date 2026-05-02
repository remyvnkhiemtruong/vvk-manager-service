<?php

namespace App\Services\Academic;

use App\Models\ClassEnrollment;
use App\Models\Guardian;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentClassTransferLog;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Support\Audit\Auditor;
use App\Support\Auth\ResourceScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicService
{
    public function __construct(private readonly ResourceScope $scope)
    {
    }

    public function students(Request $request): LengthAwarePaginator
    {
        $query = $this->scope->scope($request, 'students', Student::query())
            ->with(['guardians', 'enrollments.schoolClass.grade', 'enrollments.schoolYear', 'enrollments.semester']);

        $this->applyStudentFilters($request, $query);

        return $query
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Student $student): array => $this->studentPayload($student));
    }

    public function student(Request $request, int $id): Student
    {
        return $this->scope->scope($request, 'students', Student::query())
            ->with(['user:id,name,email,username', 'guardians', 'enrollments.schoolClass.grade', 'enrollments.schoolYear', 'enrollments.semester', 'transferLogs.fromClass', 'transferLogs.toClass', 'transferLogs.schoolYear', 'transferLogs.semester'])
            ->findOrFail($id);
    }

    public function createStudent(Request $request, array $data): Student
    {
        return DB::transaction(function () use ($request, $data): Student {
            $student = Student::create($this->studentData($data));

            Auditor::record('students.created', $student, null, $this->studentSnapshot($student), $request);

            return $student->fresh();
        });
    }

    public function updateStudent(Request $request, Student $student, array $data): Student
    {
        $this->assertScopedModel($request, 'students', Student::class, $student->id);

        return DB::transaction(function () use ($request, $student, $data): Student {
            $before = $this->studentSnapshot($student);
            $student->fill($this->studentData($data));
            $student->save();

            Auditor::record('students.updated', $student, $before, $this->studentSnapshot($student->fresh()), $request);

            return $student->fresh();
        });
    }

    public function deleteStudent(Request $request, Student $student): void
    {
        $this->assertScopedModel($request, 'students', Student::class, $student->id);

        DB::transaction(function () use ($request, $student): void {
            $before = $this->studentSnapshot($student);
            $student->delete();

            Auditor::record('students.deleted', $student, $before, null, $request);
        });
    }

    public function linkGuardian(Request $request, Student $student, array $data): void
    {
        $this->assertScopedModel($request, 'students', Student::class, $student->id);

        DB::transaction(function () use ($request, $student, $data): void {
            if ($data['is_primary'] ?? false) {
                DB::table('student_guardians')
                    ->where('student_id', $student->id)
                    ->update(['is_primary' => false, 'updated_at' => now()]);
            }

            $student->guardians()->syncWithoutDetaching([
                $data['guardian_id'] => [
                    'relationship' => $data['relationship'] ?? null,
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                    'updated_at' => now(),
                ],
            ]);

            Auditor::record('student_guardians.linked', $student, null, [
                'student_id' => $student->id,
                'guardian_id' => $data['guardian_id'],
                'relationship' => $data['relationship'] ?? null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
            ], $request);
        });
    }

    public function unlinkGuardian(Request $request, Student $student, Guardian $guardian): void
    {
        $this->assertScopedModel($request, 'students', Student::class, $student->id);

        DB::transaction(function () use ($request, $student, $guardian): void {
            $before = DB::table('student_guardians')
                ->where('student_id', $student->id)
                ->where('guardian_id', $guardian->id)
                ->first();

            $student->guardians()->detach($guardian->id);

            Auditor::record('student_guardians.unlinked', $student, $before ? (array) $before : null, null, $request);
        });
    }

    public function enrollStudent(Request $request, Student $student, array $data): ClassEnrollment
    {
        $this->assertScopedModel($request, 'students', Student::class, $student->id);
        $this->scope->assertCanWrite($request, 'student_class_enrollments', ['student_id' => $student->id, ...$data]);

        return DB::transaction(function () use ($request, $student, $data): ClassEnrollment {
            $enrollment = ClassEnrollment::withTrashed()
                ->where('student_id', $student->id)
                ->where('semester_id', $data['semester_id'])
                ->first();

            $before = $enrollment?->getAttributes();

            if (! $enrollment) {
                $enrollment = new ClassEnrollment(['student_id' => $student->id]);
            }

            if (method_exists($enrollment, 'restore') && $enrollment->trashed()) {
                $enrollment->restore();
            }

            $enrollment->fill([
                'class_id' => $data['class_id'],
                'school_year_id' => $data['school_year_id'],
                'semester_id' => $data['semester_id'],
                'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
                'left_at' => null,
                'status' => $data['status'] ?? 'active',
                'note' => $data['note'] ?? null,
            ]);
            $enrollment->save();

            Auditor::record($before ? 'student_class_enrollments.updated' : 'student_class_enrollments.created', $enrollment, $before, $enrollment->fresh()->getAttributes(), $request);

            return $enrollment->fresh();
        });
    }

    public function transferStudent(Request $request, Student $student, array $data): StudentClassTransferLog
    {
        $this->assertScopedModel($request, 'students', Student::class, $student->id);
        $this->scope->assertCanWrite($request, 'student_class_enrollments', [
            'student_id' => $student->id,
            'class_id' => $data['to_class_id'],
        ]);

        return DB::transaction(function () use ($request, $student, $data): StudentClassTransferLog {
            $current = ClassEnrollment::query()
                ->where('student_id', $student->id)
                ->where('school_year_id', $data['school_year_id'])
                ->where('semester_id', $data['semester_id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($current && (int) $current->class_id === (int) $data['to_class_id']) {
                throw ValidationException::withMessages([
                    'to_class_id' => 'Học sinh đang ở lớp này.',
                ]);
            }

            $fromClassId = $current?->class_id;

            if ($current) {
                $before = $current->getAttributes();
                $current->forceFill([
                    'status' => 'transferred',
                    'left_at' => $data['transferred_at'],
                    'note' => $data['note'] ?? $current->note,
                ])->save();
                Auditor::record('student_class_enrollments.transferred_out', $current, $before, $current->fresh()->getAttributes(), $request);
            }

            $enrollment = ClassEnrollment::withTrashed()
                ->where('student_id', $student->id)
                ->where('semester_id', $data['semester_id'])
                ->first();

            if (! $enrollment) {
                $enrollment = new ClassEnrollment(['student_id' => $student->id]);
            }

            if (method_exists($enrollment, 'restore') && $enrollment->trashed()) {
                $enrollment->restore();
            }

            $enrollment->forceFill([
                'class_id' => $data['to_class_id'],
                'school_year_id' => $data['school_year_id'],
                'semester_id' => $data['semester_id'],
                'enrolled_at' => $data['transferred_at'],
                'left_at' => null,
                'status' => 'active',
                'note' => $data['note'] ?? null,
            ])->save();

            $log = StudentClassTransferLog::create([
                'student_id' => $student->id,
                'from_class_id' => $fromClassId,
                'to_class_id' => $data['to_class_id'],
                'school_year_id' => $data['school_year_id'],
                'semester_id' => $data['semester_id'],
                'transferred_at' => $data['transferred_at'],
                'transferred_by' => $request->user()?->id,
                'note' => $data['note'] ?? null,
            ]);

            Auditor::record('student_class_transfers.created', $log, null, $log->fresh()->getAttributes(), $request);

            return $log->fresh();
        });
    }

    public function assignHomeroom(Request $request, SchoolClass $class, array $data): SchoolClass
    {
        $this->assertScopedModel($request, 'classes', SchoolClass::class, $class->id);

        return DB::transaction(function () use ($request, $class, $data): SchoolClass {
            $before = $class->getAttributes();
            $class->forceFill(['homeroom_teacher_id' => $data['homeroom_teacher_id'] ?? null])->save();

            Auditor::record('classes.homeroom_updated', $class, $before, $class->fresh()->getAttributes(), $request);

            return $class->fresh();
        });
    }

    public function classes(Request $request): LengthAwarePaginator
    {
        $query = $this->scope->scope($request, 'classes', SchoolClass::query())
            ->with(['grade', 'schoolYear', 'homeroomTeacher'])
            ->withCount(['enrollments as active_students_count' => fn (Builder $builder) => $builder->where('status', 'active')]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%')
                    ->orWhere('room', 'ilike', '%'.$search.'%');
            });
        }

        foreach (['school_year_id', 'grade_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return $query
            ->orderByDesc('school_year_id')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (SchoolClass $class): array => $this->classPayload($class));
    }

    public function classDetail(Request $request, int $id): SchoolClass
    {
        return $this->scope->scope($request, 'classes', SchoolClass::query())
            ->with(['grade', 'schoolYear', 'homeroomTeacher', 'teachingAssignments.teacher', 'teachingAssignments.subject', 'teachingAssignments.semester'])
            ->withCount(['enrollments as active_students_count' => fn (Builder $builder) => $builder->where('status', 'active')])
            ->findOrFail($id);
    }

    public function teachers(Request $request): LengthAwarePaginator
    {
        $query = Staff::query()->with('user:id,name,email,username');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('teacher_code', 'ilike', '%'.$search.'%')
                    ->orWhere('full_name', 'ilike', '%'.$search.'%')
                    ->orWhere('department', 'ilike', '%'.$search.'%')
                    ->orWhere('email', 'ilike', '%'.$search.'%');
            });
        }

        foreach (['department', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return $query
            ->orderBy('teacher_code')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Staff $teacher): array => $this->teacherPayload($teacher));
    }

    public function classStudents(Request $request, SchoolClass $class)
    {
        $this->assertScopedModel($request, 'classes', SchoolClass::class, $class->id);

        return Student::query()
            ->whereExists(function ($query) use ($class): void {
                $query->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.class_id', $class->id)
                    ->where('student_class_enrollments.status', 'active');
            })
            ->orderBy('full_name')
            ->get();
    }

    public function lookups(): array
    {
        return [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->get(['id', 'name']),
            'semesters' => Semester::query()->orderByDesc('school_year_id')->orderBy('term_number')->get(['id', 'school_year_id', 'name', 'term_number']),
            'grades' => Grade::query()->orderBy('level')->get(['id', 'level', 'name']),
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'school_year_id', 'grade_id', 'name', 'code']),
            'teachers' => Staff::query()->orderBy('full_name')->get(['id', 'teacher_code', 'full_name', 'department']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'code', 'name']),
            'guardians' => Guardian::query()->orderBy('full_name')->get(['id', 'full_name', 'phone', 'email']),
        ];
    }

    public function studentPayload(Student $student): array
    {
        $student->loadMissing(['guardians', 'enrollments.schoolClass.grade', 'enrollments.schoolYear', 'enrollments.semester']);
        $currentEnrollment = $student->enrollments
            ->where('status', 'active')
            ->sortByDesc('id')
            ->first();

        return [
            'id' => $student->id,
            'user_id' => $student->user_id,
            'student_code' => $student->student_code,
            'full_name' => $student->full_name,
            'gender' => $student->gender,
            'birth_date' => $student->birth_date?->toDateString(),
            'phone' => $student->phone,
            'email' => $student->email,
            'address' => $student->address,
            'status' => $student->status,
            'current_class' => $currentEnrollment ? [
                'id' => $currentEnrollment->schoolClass?->id,
                'name' => $currentEnrollment->schoolClass?->name,
                'code' => $currentEnrollment->schoolClass?->code,
                'school_year' => $currentEnrollment->schoolYear?->name,
                'semester' => $currentEnrollment->semester?->name,
            ] : null,
            'guardians' => $student->guardians
                ->map(fn (Guardian $guardian): array => [
                    'id' => $guardian->id,
                    'full_name' => $guardian->full_name,
                    'phone' => $guardian->phone,
                    'email' => $guardian->email,
                    'relationship' => $guardian->pivot?->relationship,
                    'is_primary' => (bool) $guardian->pivot?->is_primary,
                ])
                ->values(),
        ];
    }

    public function teacherPayload(Staff $teacher): array
    {
        return [
            'id' => $teacher->id,
            'user_id' => $teacher->user_id,
            'teacher_code' => $teacher->teacher_code,
            'staff_code' => $teacher->staff_code,
            'full_name' => $teacher->full_name,
            'gender' => $teacher->gender,
            'birth_date' => $teacher->birth_date,
            'position' => $teacher->position,
            'department' => $teacher->department,
            'specialization' => $teacher->specialization,
            'qualification' => $teacher->qualification,
            'hire_date' => $teacher->hire_date?->toDateString(),
            'phone' => $teacher->phone,
            'email' => $teacher->email,
            'status' => $teacher->status,
            'user' => $teacher->user ? Arr::only($teacher->user->toArray(), ['id', 'name', 'email', 'username']) : null,
        ];
    }

    public function classPayload(SchoolClass $class): array
    {
        $class->loadMissing(['grade', 'schoolYear', 'homeroomTeacher']);

        return [
            'id' => $class->id,
            'school_year_id' => $class->school_year_id,
            'grade_id' => $class->grade_id,
            'homeroom_teacher_id' => $class->homeroom_teacher_id,
            'name' => $class->name,
            'code' => $class->code,
            'room' => $class->room,
            'capacity' => $class->capacity,
            'status' => $class->status,
            'school_year' => $class->schoolYear?->name,
            'grade' => $class->grade?->name,
            'homeroom_teacher' => $class->homeroomTeacher?->full_name,
            'active_students_count' => (int) ($class->active_students_count ?? 0),
        ];
    }

    public function assertPermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }

    public function assertScopedModel(Request $request, string $resource, string $model, int $id): void
    {
        $exists = $this->scope->scope($request, $resource, $model::query())
            ->whereKey($id)
            ->exists();

        abort_unless($exists, 403);
    }

    private function applyStudentFilters(Request $request, Builder $query): void
    {
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('student_code', 'ilike', '%'.$search.'%')
                    ->orWhere('full_name', 'ilike', '%'.$search.'%')
                    ->orWhere('email', 'ilike', '%'.$search.'%')
                    ->orWhere('phone', 'ilike', '%'.$search.'%');
            });
        }

        foreach (['status', 'gender'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        foreach (['class_id', 'school_year_id', 'semester_id'] as $filter) {
            if (! $request->filled($filter)) {
                continue;
            }

            $query->whereExists(function ($subQuery) use ($filter, $request): void {
                $subQuery->selectRaw('1')
                    ->from('student_class_enrollments')
                    ->whereColumn('student_class_enrollments.student_id', 'students.id')
                    ->where('student_class_enrollments.status', 'active')
                    ->where("student_class_enrollments.$filter", $request->query($filter));
            });
        }
    }

    private function studentData(array $data): array
    {
        return Arr::only($data, [
            'user_id',
            'student_code',
            'full_name',
            'gender',
            'birth_date',
            'phone',
            'email',
            'address',
            'status',
        ]);
    }

    private function studentSnapshot(Student $student): array
    {
        $student->loadMissing('guardians');

        return [
            ...$student->getAttributes(),
            'guardian_ids' => $student->guardians->pluck('id')->values()->all(),
        ];
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 15), 1), 100);
    }
}
