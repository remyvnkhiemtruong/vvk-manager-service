<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Services\Academic\AcademicService;
use App\Services\Academic\StudentExcelService;
use App\Support\Audit\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AcademicPageController extends Controller
{
    public function __construct(
        private readonly AcademicService $academic,
        private readonly StudentExcelService $excel
    ) {
    }

    public function students(Request $request): Response
    {
        $this->academic->assertPermission($request, 'academic.students.view');

        return Inertia::render('Academic/Students/Index', [
            'students' => $this->academic->students($request),
            'lookups' => $this->academic->lookups(),
            'filters' => $request->only(['search', 'class_id', 'school_year_id', 'semester_id', 'status', 'gender']),
            'can' => $this->can($request, 'academic.students'),
        ]);
    }

    public function createStudent(Request $request): Response
    {
        $this->academic->assertPermission($request, 'academic.students.create');

        return Inertia::render('Academic/Students/Form', [
            'student' => null,
            'lookups' => $this->academic->lookups(),
        ]);
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.students.create');
        $student = $this->academic->createStudent($request, $request->validate($this->studentRules(true)));

        return redirect()->route('academic.students.show', $student)->with('success', 'Đã tạo hồ sơ học sinh.');
    }

    public function showStudent(Request $request, Student $student): Response
    {
        $this->academic->assertPermission($request, 'academic.students.view');
        $student = $this->academic->student($request, $student->id);

        return Inertia::render('Academic/Students/Show', [
            'student' => $this->academic->studentPayload($student),
            'enrollments' => $student->enrollments
                ->map(fn ($enrollment): array => [
                    'id' => $enrollment->id,
                    'class_id' => $enrollment->class_id,
                    'class_name' => $enrollment->schoolClass?->name,
                    'school_year' => $enrollment->schoolYear?->name,
                    'semester' => $enrollment->semester?->name,
                    'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                    'left_at' => $enrollment->left_at?->toDateString(),
                    'status' => $enrollment->status,
                    'note' => $enrollment->note,
                ])
                ->values(),
            'transfers' => $student->transferLogs
                ->map(fn ($log): array => [
                    'id' => $log->id,
                    'from_class' => $log->fromClass?->name,
                    'to_class' => $log->toClass?->name,
                    'school_year' => $log->schoolYear?->name,
                    'semester' => $log->semester?->name,
                    'transferred_at' => $log->transferred_at?->toDateString(),
                    'note' => $log->note,
                ])
                ->values(),
            'lookups' => $this->academic->lookups(),
            'can' => [
                'update' => $request->user()->hasPermission('academic.students.update'),
                'enroll' => $request->user()->hasPermission('academic.student_class_enrollments.create'),
                'transfer' => $request->user()->hasPermission('academic.student_class_enrollments.update'),
            ],
        ]);
    }

    public function editStudent(Request $request, Student $student): Response
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $student = $this->academic->student($request, $student->id);

        return Inertia::render('Academic/Students/Form', [
            'student' => $this->academic->studentPayload($student),
            'lookups' => $this->academic->lookups(),
        ]);
    }

    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $this->academic->updateStudent($request, $student, $request->validate($this->studentRules(false)));

        return redirect()->route('academic.students.show', $student)->with('success', 'Đã cập nhật hồ sơ học sinh.');
    }

    public function destroyStudent(Request $request, Student $student): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.students.delete');
        $this->academic->deleteStudent($request, $student);

        return redirect()->route('academic.students.index')->with('success', 'Đã xóa hồ sơ học sinh.');
    }

    public function importStudents(Request $request): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.students.create');
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);
        $result = $this->excel->import($request, $data['file']);

        return back()->with('success', "Import hoàn tất: {$result['created']} tạo mới, {$result['updated']} cập nhật.");
    }

    public function exportClassStudents(Request $request, SchoolClass $schoolClass): BinaryFileResponse
    {
        $this->academic->assertPermission($request, 'academic.students.view');
        $path = $this->excel->exportClass($request, $schoolClass);

        return response()->download($path, 'danh-sach-'.$schoolClass->name.'.xlsx')->deleteFileAfterSend(true);
    }

    public function linkGuardian(Request $request, Student $student): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $this->academic->linkGuardian($request, $student, $request->validate([
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
        ]));

        return back()->with('success', 'Đã liên kết phụ huynh.');
    }

    public function unlinkGuardian(Request $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.students.update');
        $this->academic->unlinkGuardian($request, $student, $guardian);

        return back()->with('success', 'Đã bỏ liên kết phụ huynh.');
    }

    public function enrollStudent(Request $request, Student $student): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.student_class_enrollments.create');
        $this->academic->enrollStudent($request, $student, $request->validate($this->enrollmentRules()));

        return back()->with('success', 'Đã xếp lớp học sinh.');
    }

    public function transferStudent(Request $request, Student $student): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.student_class_enrollments.update');
        $this->academic->transferStudent($request, $student, $request->validate([
            'to_class_id' => ['required', 'integer', 'exists:classes,id'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'transferred_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Đã chuyển lớp học sinh.');
    }

    public function teachers(Request $request): Response
    {
        $this->academic->assertPermission($request, 'academic.teachers.view');

        return Inertia::render('Academic/Teachers/Index', [
            'teachers' => $this->academic->teachers($request),
            'filters' => $request->only(['search', 'department', 'status']),
            'can' => $this->can($request, 'academic.teachers'),
        ]);
    }

    public function storeTeacher(Request $request): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.create');
        $this->storeSimple($request, Staff::class, 'teachers', $this->teacherRules());

        return back()->with('success', 'Đã tạo giáo viên.');
    }

    public function updateTeacher(Request $request, Staff $teacher): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.update');
        $this->updateSimple($request, $teacher, 'teachers', $this->teacherRules());

        return back()->with('success', 'Đã cập nhật giáo viên.');
    }

    public function destroyTeacher(Request $request, Staff $teacher): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.teachers.delete');
        $this->deleteSimple($request, $teacher, 'teachers');

        return back()->with('success', 'Đã xóa giáo viên.');
    }

    public function classes(Request $request): Response
    {
        $this->academic->assertPermission($request, 'academic.classes.view');

        return Inertia::render('Academic/Classes/Index', [
            'classes' => $this->academic->classes($request),
            'lookups' => $this->academic->lookups(),
            'filters' => $request->only(['search', 'school_year_id', 'grade_id', 'status']),
            'can' => $this->can($request, 'academic.classes'),
        ]);
    }

    public function showClass(Request $request, SchoolClass $schoolClass): Response
    {
        $this->academic->assertPermission($request, 'academic.classes.view');
        $class = $this->academic->classDetail($request, $schoolClass->id);

        return Inertia::render('Academic/Classes/Show', [
            'classRecord' => $this->academic->classPayload($class),
            'students' => $this->academic->classStudents($request, $class)->map(fn (Student $student): array => $this->academic->studentPayload($student))->values(),
            'teachingAssignments' => $class->teachingAssignments
                ->map(fn (TeachingAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'teacher_id' => $assignment->teacher_id,
                    'teacher_name' => $assignment->teacher?->full_name,
                    'subject_id' => $assignment->subject_id,
                    'subject_name' => $assignment->subject?->name,
                    'semester_id' => $assignment->semester_id,
                    'semester_name' => $assignment->semester?->name,
                    'status' => $assignment->status,
                ])
                ->values(),
            'lookups' => $this->academic->lookups(),
            'can' => [
                'update' => $request->user()->hasPermission('academic.classes.update'),
                'assign' => $request->user()->hasPermission('academic.teaching_assignments.create'),
                'export' => $request->user()->hasPermission('academic.students.view'),
            ],
        ]);
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.create');
        $this->storeSimple($request, SchoolClass::class, 'classes', $this->classRules());

        return back()->with('success', 'Đã tạo lớp.');
    }

    public function updateClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.update');
        $this->updateSimple($request, $schoolClass, 'classes', $this->classRules());

        return back()->with('success', 'Đã cập nhật lớp.');
    }

    public function destroyClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.delete');
        $this->deleteSimple($request, $schoolClass, 'classes');

        return back()->with('success', 'Đã xóa lớp.');
    }

    public function assignHomeroom(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.classes.update');
        $this->academic->assignHomeroom($request, $schoolClass, $request->validate([
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]));

        return back()->with('success', 'Đã phân công GVCN.');
    }

    public function storeTeachingAssignment(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.teaching_assignments.create');
        $data = $request->validate($this->teachingAssignmentRules());
        $data['class_id'] = $schoolClass->id;
        $record = TeachingAssignment::create($data);
        Auditor::record('teaching_assignments.created', $record, null, $record->fresh()->getAttributes(), $request);

        return back()->with('success', 'Đã phân công giáo viên bộ môn.');
    }

    public function destroyTeachingAssignment(Request $request, TeachingAssignment $teachingAssignment): RedirectResponse
    {
        $this->academic->assertPermission($request, 'academic.teaching_assignments.delete');
        $this->deleteSimple($request, $teachingAssignment, 'teaching_assignments');

        return back()->with('success', 'Đã xóa phân công.');
    }

    private function can(Request $request, string $base): array
    {
        return [
            'create' => $request->user()->hasPermission($base.'.create'),
            'update' => $request->user()->hasPermission($base.'.update'),
            'delete' => $request->user()->hasPermission($base.'.delete'),
        ];
    }

    private function storeSimple(Request $request, string $model, string $resource, array $rules): void
    {
        DB::transaction(function () use ($request, $model, $resource, $rules): void {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $record = $model::create($request->validate($rules));
            Auditor::record($resource.'.created', $record, null, $record->fresh()->getAttributes(), $request);
        });
    }

    private function updateSimple(Request $request, \Illuminate\Database\Eloquent\Model $record, string $resource, array $rules): void
    {
        $this->academic->assertScopedModel($request, $resource, $record::class, $record->getKey());

        DB::transaction(function () use ($request, $record, $resource, $rules): void {
            $before = $record->getAttributes();
            $record->fill($request->validate($rules))->save();
            Auditor::record($resource.'.updated', $record, $before, $record->fresh()->getAttributes(), $request);
        });
    }

    private function deleteSimple(Request $request, \Illuminate\Database\Eloquent\Model $record, string $resource): void
    {
        $this->academic->assertScopedModel($request, $resource, $record::class, $record->getKey());

        DB::transaction(function () use ($request, $record, $resource): void {
            $before = $record->getAttributes();
            $record->delete();
            Auditor::record($resource.'.deleted', $record, $before, null, $request);
        });
    }

    private function studentRules(bool $create): array
    {
        return ['user_id' => ['nullable', 'integer', 'exists:users,id'], 'student_code' => ['required', 'string', 'max:255', $create ? 'unique:students,student_code' : 'max:255'], 'full_name' => ['required', 'string', 'max:255'], 'gender' => ['required', 'string', 'in:female,male,other'], 'birth_date' => ['nullable', 'date'], 'phone' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'status' => ['required', 'string', 'max:255']];
    }

    private function teacherRules(): array
    {
        return ['user_id' => ['nullable', 'exists:users,id'], 'teacher_code' => ['required', 'string'], 'staff_code' => ['nullable', 'string'], 'full_name' => ['required', 'string'], 'gender' => ['nullable', 'string'], 'birth_date' => ['nullable', 'date'], 'position' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'specialization' => ['nullable', 'string'], 'qualification' => ['nullable', 'string'], 'hire_date' => ['nullable', 'date'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'status' => ['required', 'string']];
    }

    private function classRules(): array
    {
        return ['school_year_id' => ['required', 'exists:school_years,id'], 'grade_id' => ['required', 'exists:grades,id'], 'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'], 'name' => ['required', 'string'], 'code' => ['nullable', 'string'], 'room' => ['nullable', 'string'], 'capacity' => ['nullable', 'integer', 'min:1'], 'status' => ['required', 'string']];
    }

    private function enrollmentRules(): array
    {
        return ['class_id' => ['required', 'exists:classes,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'enrolled_at' => ['nullable', 'date'], 'status' => ['nullable', 'string'], 'note' => ['nullable', 'string']];
    }

    private function teachingAssignmentRules(): array
    {
        return ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'teacher_id' => ['required', 'exists:teachers,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'status' => ['required', 'string']];
    }
}
