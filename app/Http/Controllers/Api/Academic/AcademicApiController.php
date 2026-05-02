<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\Guardian;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\Academic\AcademicService;
use App\Services\Academic\StudentExcelService;
use App\Support\Audit\Auditor;
use App\Support\Auth\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AcademicApiController extends Controller
{
    public function __construct(
        private readonly AcademicService $academic,
        private readonly StudentExcelService $excel,
        private readonly ResourceScope $scope
    ) {
    }

    public function students(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.view');

        return $this->ok($this->academic->students($request));
    }

    public function storeStudent(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.create');
        $student = $this->academic->createStudent($request, $request->validate($this->studentRules(true)));

        return $this->created($this->academic->studentPayload($student), 'Đã tạo hồ sơ học sinh.');
    }

    public function showStudent(Request $request, Student $student): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.view');

        return $this->ok($this->academic->studentPayload($this->academic->student($request, $student->id)));
    }

    public function updateStudent(Request $request, Student $student): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $student = $this->academic->updateStudent($request, $student, $request->validate($this->studentRules(false)));

        return $this->ok($this->academic->studentPayload($student), 'Đã cập nhật hồ sơ học sinh.');
    }

    public function destroyStudent(Request $request, Student $student): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.delete');
        $this->academic->deleteStudent($request, $student);

        return $this->ok(null, 'Đã xóa hồ sơ học sinh.');
    }

    public function importStudents(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.create');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        return $this->ok($this->excel->import($request, $data['file']), 'Import học sinh hoàn tất.');
    }

    public function exportClassStudents(Request $request, SchoolClass $schoolClass): BinaryFileResponse
    {
        $this->academic->assertPermission($request, 'academic.students.view');
        $path = $this->excel->exportClass($request, $schoolClass);

        return response()->download($path, 'danh-sach-'.$schoolClass->name.'.xlsx')->deleteFileAfterSend(true);
    }

    public function linkGuardian(Request $request, Student $student): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $data = $request->validate([
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
        ]);

        $this->academic->linkGuardian($request, $student, $data);

        return $this->ok(null, 'Đã liên kết phụ huynh.');
    }

    public function unlinkGuardian(Request $request, Student $student, Guardian $guardian): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $this->academic->unlinkGuardian($request, $student, $guardian);

        return $this->ok(null, 'Đã bỏ liên kết phụ huynh.');
    }

    public function enrollStudent(Request $request, Student $student): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.student_class_enrollments.create');
        $enrollment = $this->academic->enrollStudent($request, $student, $request->validate($this->enrollmentRules()));

        return $this->created($enrollment->fresh()->toArray(), 'Đã xếp lớp học sinh.');
    }

    public function transferStudent(Request $request, Student $student): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.student_class_enrollments.update');
        $log = $this->academic->transferStudent($request, $student, $request->validate([
            'to_class_id' => ['required', 'integer', 'exists:classes,id'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'transferred_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]));

        return $this->created($log->toArray(), 'Đã chuyển lớp học sinh.');
    }

    public function guardians(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.guardians.view');

        return $this->ok($this->paginated($request, 'guardians', Guardian::class, ['full_name', 'phone', 'email'], ['status']));
    }

    public function storeGuardian(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.guardians.create');

        return $this->storeSimple($request, Guardian::class, 'guardians', $this->guardianRules(), 'Đã tạo phụ huynh.');
    }

    public function updateGuardian(Request $request, Guardian $guardian): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.guardians.update');

        return $this->updateSimple($request, $guardian, 'guardians', $this->guardianRules(), 'Đã cập nhật phụ huynh.');
    }

    public function destroyGuardian(Request $request, Guardian $guardian): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.guardians.delete');

        return $this->deleteSimple($request, $guardian, 'guardians', 'Đã xóa phụ huynh.');
    }

    public function teachers(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.view');

        return $this->ok($this->academic->teachers($request));
    }

    public function storeTeacher(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.create');

        return $this->storeSimple($request, Staff::class, 'teachers', $this->teacherRules(), 'Đã tạo giáo viên.');
    }

    public function updateTeacher(Request $request, Staff $teacher): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.update');

        return $this->updateSimple($request, $teacher, 'teachers', $this->teacherRules(), 'Đã cập nhật giáo viên.');
    }

    public function destroyTeacher(Request $request, Staff $teacher): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.delete');

        return $this->deleteSimple($request, $teacher, 'teachers', 'Đã xóa giáo viên.');
    }

    public function classes(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.view');

        return $this->ok($this->academic->classes($request));
    }

    public function showClass(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.view');
        $class = $this->academic->classDetail($request, $schoolClass->id);

        return $this->ok([
            'class' => $this->academic->classPayload($class),
            'students' => $this->academic->classStudents($request, $class)->map(fn (Student $student): array => $this->academic->studentPayload($student))->values(),
            'teaching_assignments' => $class->teachingAssignments->map(fn (TeachingAssignment $assignment): array => [
                'id' => $assignment->id,
                'teacher' => $assignment->teacher?->full_name,
                'subject' => $assignment->subject?->name,
                'semester' => $assignment->semester?->name,
                'status' => $assignment->status,
            ])->values(),
        ]);
    }

    public function storeClass(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.create');

        return $this->storeSimple($request, SchoolClass::class, 'classes', $this->classRules(), 'Đã tạo lớp.');
    }

    public function updateClass(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.update');

        return $this->updateSimple($request, $schoolClass, 'classes', $this->classRules(), 'Đã cập nhật lớp.');
    }

    public function destroyClass(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.delete');

        return $this->deleteSimple($request, $schoolClass, 'classes', 'Đã xóa lớp.');
    }

    public function assignHomeroom(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.update');
        $class = $this->academic->assignHomeroom($request, $schoolClass, $request->validate([
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]));

        return $this->ok($this->academic->classPayload($class), 'Đã phân công GVCN.');
    }

    public function schoolYears(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.school_years.view');

        return $this->ok($this->paginated($request, 'school_years', SchoolYear::class, ['name'], ['status', 'is_active']));
    }

    public function storeSchoolYear(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.school_years.create');

        return $this->storeSimple($request, SchoolYear::class, 'school_years', $this->schoolYearRules(), 'Đã tạo năm học.');
    }

    public function updateSchoolYear(Request $request, SchoolYear $schoolYear): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.school_years.update');

        return $this->updateSimple($request, $schoolYear, 'school_years', $this->schoolYearRules(), 'Đã cập nhật năm học.');
    }

    public function destroySchoolYear(Request $request, SchoolYear $schoolYear): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.school_years.delete');

        return $this->deleteSimple($request, $schoolYear, 'school_years', 'Đã xóa năm học.');
    }

    public function semesters(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.semesters.view');

        return $this->ok($this->paginated($request, 'semesters', Semester::class, ['name'], ['school_year_id', 'status', 'is_active']));
    }

    public function storeSemester(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.semesters.create');

        return $this->storeSimple($request, Semester::class, 'semesters', $this->semesterRules(), 'Đã tạo học kỳ.');
    }

    public function updateSemester(Request $request, Semester $semester): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.semesters.update');

        return $this->updateSimple($request, $semester, 'semesters', $this->semesterRules(), 'Đã cập nhật học kỳ.');
    }

    public function destroySemester(Request $request, Semester $semester): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.semesters.delete');

        return $this->deleteSimple($request, $semester, 'semesters', 'Đã xóa học kỳ.');
    }

    public function grades(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.grades.view');

        return $this->ok($this->paginated($request, 'grades', Grade::class, ['name'], ['status']));
    }

    public function storeGrade(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.grades.create');

        return $this->storeSimple($request, Grade::class, 'grades', $this->gradeRules(), 'Đã tạo khối.');
    }

    public function updateGrade(Request $request, Grade $grade): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.grades.update');

        return $this->updateSimple($request, $grade, 'grades', $this->gradeRules(), 'Đã cập nhật khối.');
    }

    public function destroyGrade(Request $request, Grade $grade): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.grades.delete');

        return $this->deleteSimple($request, $grade, 'grades', 'Đã xóa khối.');
    }

    public function enrollments(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.student_class_enrollments.view');

        return $this->ok($this->paginated($request, 'student_class_enrollments', ClassEnrollment::class, ['note'], ['student_id', 'class_id', 'school_year_id', 'semester_id', 'status']));
    }

    public function storeEnrollment(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.student_class_enrollments.create');
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:students,id'], ...$this->enrollmentRules()]);
        $student = Student::findOrFail($data['student_id']);
        $enrollment = $this->academic->enrollStudent($request, $student, Arr::except($data, ['student_id']));

        return $this->created($enrollment->toArray(), 'Đã xếp lớp học sinh.');
    }

    public function teachingAssignments(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teaching_assignments.view');

        return $this->ok($this->paginated($request, 'teaching_assignments', TeachingAssignment::class, [], ['teacher_id', 'class_id', 'subject_id', 'semester_id', 'status']));
    }

    public function storeTeachingAssignment(Request $request): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teaching_assignments.create');

        return $this->storeSimple($request, TeachingAssignment::class, 'teaching_assignments', $this->teachingAssignmentRules(), 'Đã phân công giáo viên bộ môn.');
    }

    public function updateTeachingAssignment(Request $request, TeachingAssignment $teachingAssignment): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teaching_assignments.update');

        return $this->updateSimple($request, $teachingAssignment, 'teaching_assignments', $this->teachingAssignmentRules(), 'Đã cập nhật phân công.');
    }

    public function destroyTeachingAssignment(Request $request, TeachingAssignment $teachingAssignment): JsonResponse
    {
        $this->academic->assertPermission($request, 'academic.teaching_assignments.delete');

        return $this->deleteSimple($request, $teachingAssignment, 'teaching_assignments', 'Đã xóa phân công.');
    }

    private function paginated(Request $request, string $resource, string $model, array $searchColumns, array $filters)
    {
        /** @var class-string<Model> $model */
        $query = $this->scope->scope($request, $resource, $model::query());

        if ($searchColumns && ($search = trim((string) $request->query('search', '')))) {
            $query->where(function (Builder $builder) use ($searchColumns, $search): void {
                foreach ($searchColumns as $column) {
                    $builder->orWhere($column, 'ilike', '%'.$search.'%');
                }
            });
        }

        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return $query->latest('id')->paginate(min(max((int) $request->query('per_page', 15), 1), 100))->withQueryString();
    }

    private function storeSimple(Request $request, string $model, string $resource, array $rules, string $message): JsonResponse
    {
        return DB::transaction(function () use ($request, $model, $resource, $rules, $message): JsonResponse {
            /** @var class-string<Model> $model */
            $record = $model::create($request->validate($rules));
            Auditor::record($resource.'.created', $record, null, $record->fresh()->getAttributes(), $request);

            return $this->created($record->fresh()->toArray(), $message);
        });
    }

    private function updateSimple(Request $request, Model $record, string $resource, array $rules, string $message): JsonResponse
    {
        $this->academic->assertScopedModel($request, $resource, $record::class, $record->getKey());

        return DB::transaction(function () use ($request, $record, $resource, $rules, $message): JsonResponse {
            $before = $record->getAttributes();
            $record->fill($request->validate($rules));
            $record->save();
            Auditor::record($resource.'.updated', $record, $before, $record->fresh()->getAttributes(), $request);

            return $this->ok($record->fresh()->toArray(), $message);
        });
    }

    private function deleteSimple(Request $request, Model $record, string $resource, string $message): JsonResponse
    {
        $this->academic->assertScopedModel($request, $resource, $record::class, $record->getKey());

        return DB::transaction(function () use ($request, $record, $resource, $message): JsonResponse {
            $before = $record->getAttributes();
            $record->delete();
            Auditor::record($resource.'.deleted', $record, $before, null, $request);

            return $this->ok(null, $message);
        });
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $data]);
    }

    private function created(mixed $data, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $data], 201);
    }

    private function studentRules(bool $create): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_code' => ['required', 'string', 'max:255', $create ? 'unique:students,student_code' : 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:female,male,other'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }

    private function guardianRules(): array
    {
        return ['user_id' => ['nullable', 'exists:users,id'], 'full_name' => ['required', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'relationship' => ['nullable', 'string'], 'address' => ['nullable', 'string'], 'status' => ['required', 'string']];
    }

    private function teacherRules(): array
    {
        return ['user_id' => ['nullable', 'exists:users,id'], 'teacher_code' => ['required', 'string'], 'staff_code' => ['nullable', 'string'], 'full_name' => ['required', 'string'], 'gender' => ['nullable', 'string'], 'birth_date' => ['nullable', 'date'], 'position' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'specialization' => ['nullable', 'string'], 'qualification' => ['nullable', 'string'], 'hire_date' => ['nullable', 'date'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'status' => ['required', 'string']];
    }

    private function classRules(): array
    {
        return ['school_year_id' => ['required', 'exists:school_years,id'], 'grade_id' => ['required', 'exists:grades,id'], 'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'], 'name' => ['required', 'string'], 'code' => ['nullable', 'string'], 'room' => ['nullable', 'string'], 'capacity' => ['nullable', 'integer', 'min:1'], 'status' => ['required', 'string']];
    }

    private function schoolYearRules(): array
    {
        return ['name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean'], 'status' => ['required', 'string']];
    }

    private function semesterRules(): array
    {
        return ['school_year_id' => ['required', 'exists:school_years,id'], 'name' => ['required', 'string'], 'term_number' => ['required', 'integer'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean'], 'status' => ['required', 'string']];
    }

    private function gradeRules(): array
    {
        return ['level' => ['required', 'integer', 'min:10', 'max:12'], 'name' => ['required', 'string'], 'status' => ['required', 'string']];
    }

    private function enrollmentRules(): array
    {
        return ['class_id' => ['required', 'exists:classes,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'enrolled_at' => ['nullable', 'date'], 'status' => ['nullable', 'string'], 'note' => ['nullable', 'string']];
    }

    private function teachingAssignmentRules(): array
    {
        return ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'teacher_id' => ['required', 'exists:teachers,id'], 'class_id' => ['required', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'status' => ['required', 'string']];
    }
}
