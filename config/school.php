<?php

use App\Models\Announcement;
use App\Models\ClassEnrollment;
use App\Models\ConductRatingRule;
use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductScore;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\EventRegistration;
use App\Models\EventResult;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeePlan;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolYear;
use App\Models\ScoreCategory;
use App\Models\ScoreColumn;
use App\Models\ScoreEntry;
use App\Models\ScoreRevision;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

$statusOptions = [
    'active' => 'Dang hoat dong',
    'inactive' => 'Tam dung',
    'draft' => 'Nhap',
    'submitted' => 'Da nop',
    'approved' => 'Da duyet',
    'locked' => 'Da khoa',
];

$yearLookup = ['model' => SchoolYear::class, 'value' => 'id', 'label' => ['name']];
$semesterLookup = ['model' => Semester::class, 'value' => 'id', 'label' => ['name']];
$classLookup = ['model' => SchoolClass::class, 'value' => 'id', 'label' => ['name']];
$studentLookup = ['model' => Student::class, 'value' => 'id', 'label' => ['student_code', 'full_name']];
$teacherLookup = ['model' => Staff::class, 'value' => 'id', 'label' => ['teacher_code', 'full_name']];
$subjectLookup = ['model' => Subject::class, 'value' => 'id', 'label' => ['code', 'name']];

return [
    'school' => [
        'name' => 'Truong THPT Vo Van Kiet',
        'address' => 'So 10B - Ap Long Hoa - Xa Phuoc Long - Tinh Ca Mau',
        'email' => 'c3phuoclong.sobaclieu@moet.edu.vn',
        'website' => 'https://thptvovankiet.sgdcamau.edu.vn/',
    ],

    'module_labels' => [
        'identity' => 'Tai khoan va phan quyen',
        'academic' => 'Hoc vu',
        'assessment' => 'Diem hoc tap',
        'conduct' => 'Ren luyen va ky luat',
        'attendance' => 'Diem danh',
        'activities' => 'Phong trao va hoi thi',
        'finance' => 'Hoc phi',
        'communication' => 'Thong bao',
    ],

    'assessment' => [
        'score_types' => [
            'TX' => ['name' => 'Diem thuong xuyen', 'weight' => 1, 'input_type' => 'numeric', 'counts_toward_average' => true],
            'GK' => ['name' => 'Diem giua ky', 'weight' => 2, 'input_type' => 'numeric', 'counts_toward_average' => true],
            'CK' => ['name' => 'Diem cuoi ky', 'weight' => 3, 'input_type' => 'numeric', 'counts_toward_average' => true],
            'NX' => ['name' => 'Diem nhan xet', 'weight' => 0, 'input_type' => 'comment', 'counts_toward_average' => false],
        ],
        'lock_statuses' => [
            'open' => 'Dang mo',
            'locked' => 'Da khoa',
            'unlock_requested' => 'Yeu cau mo khoa',
        ],
        'average' => [
            'numeric_subject_mode' => 'numeric',
            'comment_subject_mode' => 'comment',
            'precision' => 2,
        ],
    ],

    'conduct' => [
        'base_score' => 100,
        'min_score' => 0,
        'max_score' => 100,
        'approval_point_threshold' => 10,
        'approval_severities' => ['major', 'serious'],
        'record_statuses' => [
            'pending' => 'Cho duyet',
            'approved' => 'Da duyet',
            'rejected' => 'Tu choi',
            'cancelled' => 'Da huy',
        ],
        'lock_statuses' => [
            'open' => 'Dang mo',
            'locked' => 'Da khoa',
        ],
        'ratings' => [
            'Tốt' => ['min' => 90, 'max' => 100],
            'Khá' => ['min' => 75, 'max' => 89],
            'Trung bình' => ['min' => 50, 'max' => 74],
            'Yếu' => ['min' => 0, 'max' => 49],
        ],
    ],

    'roles' => [
        'admin' => 'Admin',
        'bgh' => 'Ban giam hieu',
        'giao_vu' => 'Giao vu',
        'gvcn' => 'Giao vien chu nhiem',
        'giao_vien_bo_mon' => 'Giao vien bo mon',
        'doan_truong' => 'Doan truong/BTC phong trao',
        'giam_thi' => 'Giam thi',
        'ke_toan' => 'Ke toan',
        'phu_huynh' => 'Phu huynh',
        'hoc_sinh' => 'Hoc sinh',
    ],

    'role_permissions' => [
        'admin' => ['*'],
        'bgh' => ['dashboard.view', 'reports.view', 'audit.view', 'portal.view', 'identity.*', 'academic.*', 'assessment.*', 'conduct.*', 'attendance.*', 'activities.*', 'finance.*', 'communication.*'],
        'giao_vu' => ['dashboard.view', 'reports.view', 'portal.view', 'academic.*', 'assessment.score_types.view', 'assessment.score_columns.*', 'assessment.student_scores.view', 'conduct.conduct_scores.view', 'attendance.*', 'communication.announcements.*'],
        'gvcn' => ['dashboard.view', 'portal.view', 'academic.students.view', 'academic.classes.view', 'academic.student_class_enrollments.view', 'assessment.student_scores.view', 'conduct.conduct_records.*', 'conduct.conduct_scores.*', 'conduct.conduct_rating_rules.view', 'conduct.discipline_cases.*', 'attendance.attendance_records.*', 'communication.announcements.view'],
        'giao_vien_bo_mon' => ['dashboard.view', 'portal.view', 'academic.classes.view', 'academic.subjects.view', 'academic.teaching_assignments.view', 'assessment.student_scores.*', 'conduct.conduct_records.view', 'conduct.conduct_records.create', 'attendance.attendance_records.*', 'communication.announcements.view'],
        'doan_truong' => ['dashboard.view', 'portal.view', 'conduct.conduct_records.view', 'conduct.conduct_records.create', 'conduct.conduct_scores.view', 'activities.*', 'communication.announcements.*'],
        'giam_thi' => ['dashboard.view', 'portal.view', 'conduct.conduct_records.view', 'conduct.conduct_records.create', 'conduct.conduct_records.update', 'conduct.conduct_scores.*', 'conduct.discipline_cases.*', 'conduct.discipline_actions.*', 'attendance.attendance_records.*', 'communication.announcements.view'],
        'ke_toan' => ['dashboard.view', 'portal.view', 'finance.*', 'communication.announcements.view'],
        'phu_huynh' => ['dashboard.view', 'portal.view', 'conduct.conduct_scores.view', 'conduct.conduct_records.view', 'communication.announcements.view'],
        'hoc_sinh' => ['dashboard.view', 'portal.view', 'conduct.conduct_scores.view', 'conduct.conduct_records.view', 'communication.announcements.view'],
    ],

    'resources' => [
        'users' => [
            'module' => 'identity',
            'label' => 'Tai khoan',
            'model' => User::class,
            'permission' => 'identity.users',
            'columns' => ['name', 'username', 'email', 'status', 'role_ids'],
            'search' => ['name', 'username', 'email'],
            'fields' => [
                ['name' => 'name', 'label' => 'Ho ten', 'type' => 'text', 'required' => true],
                ['name' => 'username', 'label' => 'Ten dang nhap', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['name' => 'password', 'label' => 'Mat khau', 'type' => 'password', 'storeOnlyRequired' => true, 'skipEmptyOnUpdate' => true],
                ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true],
                ['name' => 'role_ids', 'label' => 'Vai tro', 'type' => 'multiselect', 'lookup' => ['model' => Role::class, 'value' => 'id', 'label' => ['name']]],
            ],
            'validation' => [
                'store' => ['name' => ['required', 'string', 'max:255'], 'username' => ['required', 'string', 'max:255', 'unique:users,username'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'string', 'min:8'], 'status' => ['required', 'string'], 'role_ids' => ['array'], 'role_ids.*' => ['integer', 'exists:roles,id']],
                'update' => ['name' => ['required', 'string', 'max:255'], 'username' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], 'password' => ['nullable', 'string', 'min:8'], 'status' => ['required', 'string'], 'role_ids' => ['array'], 'role_ids.*' => ['integer', 'exists:roles,id']],
            ],
            'sync' => ['role_ids' => 'roles'],
            'audit' => true,
        ],
        'roles' => [
            'module' => 'identity',
            'label' => 'Vai tro',
            'model' => Role::class,
            'permission' => 'identity.roles',
            'columns' => ['name', 'slug', 'permission_ids'],
            'fields' => [
                ['name' => 'name', 'label' => 'Ten vai tro', 'type' => 'text', 'required' => true],
                ['name' => 'slug', 'label' => 'Ma vai tro', 'type' => 'text', 'required' => true],
                ['name' => 'permission_ids', 'label' => 'Quyen', 'type' => 'multiselect', 'lookup' => ['model' => Permission::class, 'value' => 'id', 'label' => ['key']]],
            ],
            'validation' => [
                'store' => ['name' => ['required', 'string'], 'slug' => ['required', 'string', 'unique:roles,slug'], 'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']],
                'update' => ['name' => ['required', 'string'], 'slug' => ['required', 'string'], 'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']],
            ],
            'sync' => ['permission_ids' => 'permissions'],
            'audit' => true,
        ],
        'permissions' => [
            'module' => 'identity',
            'label' => 'Quyen chi tiet',
            'model' => Permission::class,
            'permission' => 'identity.permissions',
            'columns' => ['key', 'name', 'module'],
            'fields' => [
                ['name' => 'key', 'label' => 'Permission key', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => 'Ten quyen', 'type' => 'text', 'required' => true],
                ['name' => 'module', 'label' => 'Module', 'type' => 'text', 'required' => true],
            ],
            'validation' => ['store' => ['key' => ['required', 'string', 'unique:permissions,key'], 'name' => ['required', 'string'], 'module' => ['required', 'string']], 'update' => ['key' => ['required', 'string'], 'name' => ['required', 'string'], 'module' => ['required', 'string']]],
            'audit' => true,
        ],
        'school_years' => [
            'module' => 'academic',
            'label' => 'Nam hoc',
            'model' => SchoolYear::class,
            'permission' => 'academic.school_years',
            'columns' => ['name', 'start_date', 'end_date', 'status'],
            'fields' => [['name' => 'name', 'label' => 'Ten nam hoc', 'type' => 'text', 'required' => true], ['name' => 'start_date', 'label' => 'Ngay bat dau', 'type' => 'date', 'required' => true], ['name' => 'end_date', 'label' => 'Ngay ket thuc', 'type' => 'date', 'required' => true], ['name' => 'is_active', 'label' => 'Dang dung', 'type' => 'checkbox'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean'], 'status' => ['required', 'string']], 'update' => ['name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'semesters' => [
            'module' => 'academic',
            'label' => 'Hoc ky',
            'model' => Semester::class,
            'permission' => 'academic.semesters',
            'columns' => ['name', 'school_year_id', 'term_number', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'name', 'label' => 'Ten hoc ky', 'type' => 'text', 'required' => true], ['name' => 'term_number', 'label' => 'Thu tu', 'type' => 'number', 'required' => true], ['name' => 'start_date', 'label' => 'Ngay bat dau', 'type' => 'date', 'required' => true], ['name' => 'end_date', 'label' => 'Ngay ket thuc', 'type' => 'date', 'required' => true], ['name' => 'is_active', 'label' => 'Dang dung', 'type' => 'checkbox'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'name' => ['required', 'string'], 'term_number' => ['required', 'integer'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'name' => ['required', 'string'], 'term_number' => ['required', 'integer'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'grades' => [
            'module' => 'academic',
            'label' => 'Khoi lop',
            'model' => App\Models\Grade::class,
            'permission' => 'academic.grades',
            'columns' => ['level', 'name', 'status'],
            'fields' => [['name' => 'level', 'label' => 'Khoi', 'type' => 'number', 'required' => true], ['name' => 'name', 'label' => 'Ten khoi', 'type' => 'text', 'required' => true], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['level' => ['required', 'integer', 'min:10', 'max:12'], 'name' => ['required', 'string'], 'status' => ['required', 'string']], 'update' => ['level' => ['required', 'integer', 'min:10', 'max:12'], 'name' => ['required', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'classes' => [
            'module' => 'academic',
            'label' => 'Lop hoc',
            'model' => SchoolClass::class,
            'permission' => 'academic.classes',
            'columns' => ['name', 'school_year_id', 'grade_id', 'homeroom_teacher_id', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'grade_id', 'label' => 'Khoi', 'type' => 'select', 'lookup' => ['model' => App\Models\Grade::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'homeroom_teacher_id', 'label' => 'GVCN', 'type' => 'select', 'lookup' => $teacherLookup], ['name' => 'name', 'label' => 'Ten lop', 'type' => 'text', 'required' => true], ['name' => 'room', 'label' => 'Phong', 'type' => 'text'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'grade_id' => ['required', 'exists:grades,id'], 'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'], 'name' => ['required', 'string'], 'room' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'grade_id' => ['required', 'exists:grades,id'], 'homeroom_teacher_id' => ['nullable', 'exists:teachers,id'], 'name' => ['required', 'string'], 'room' => ['nullable', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'subjects' => [
            'module' => 'academic',
            'label' => 'Mon hoc',
            'model' => Subject::class,
            'permission' => 'academic.subjects',
            'columns' => ['code', 'name', 'department', 'status'],
            'fields' => [['name' => 'code', 'label' => 'Ma mon', 'type' => 'text', 'required' => true], ['name' => 'name', 'label' => 'Ten mon', 'type' => 'text', 'required' => true], ['name' => 'department', 'label' => 'To chuyen mon', 'type' => 'text'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'department' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'department' => ['nullable', 'string'], 'status' => ['required', 'string']]],
        ],
        'teachers' => [
            'module' => 'academic',
            'label' => 'Giao vien',
            'model' => Staff::class,
            'permission' => 'academic.teachers',
            'columns' => ['teacher_code', 'full_name', 'position', 'department', 'status'],
            'search' => ['teacher_code', 'full_name'],
            'fields' => [['name' => 'user_id', 'label' => 'Tai khoan', 'type' => 'select', 'lookup' => ['model' => User::class, 'value' => 'id', 'label' => ['name', 'email']]], ['name' => 'teacher_code', 'label' => 'Ma giao vien', 'type' => 'text', 'required' => true], ['name' => 'full_name', 'label' => 'Ho ten', 'type' => 'text', 'required' => true], ['name' => 'position', 'label' => 'Chuc vu', 'type' => 'text'], ['name' => 'department', 'label' => 'To/Bophan', 'type' => 'text'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['user_id' => ['nullable', 'exists:users,id'], 'teacher_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'position' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['user_id' => ['nullable', 'exists:users,id'], 'teacher_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'position' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'students' => [
            'module' => 'academic',
            'label' => 'Hoc sinh',
            'model' => Student::class,
            'permission' => 'academic.students',
            'columns' => ['student_code', 'full_name', 'gender', 'birth_date', 'status'],
            'search' => ['student_code', 'full_name'],
            'fields' => [['name' => 'user_id', 'label' => 'Tai khoan', 'type' => 'select', 'lookup' => ['model' => User::class, 'value' => 'id', 'label' => ['name', 'email']]], ['name' => 'student_code', 'label' => 'Ma hoc sinh', 'type' => 'text', 'required' => true], ['name' => 'full_name', 'label' => 'Ho ten', 'type' => 'text', 'required' => true], ['name' => 'gender', 'label' => 'Gioi tinh', 'type' => 'select', 'options' => ['female' => 'Nu', 'male' => 'Nam', 'other' => 'Khac'], 'required' => true], ['name' => 'birth_date', 'label' => 'Ngay sinh', 'type' => 'date'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoc', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['user_id' => ['nullable', 'exists:users,id'], 'student_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'gender' => ['required', 'string'], 'birth_date' => ['nullable', 'date'], 'status' => ['required', 'string']], 'update' => ['user_id' => ['nullable', 'exists:users,id'], 'student_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'gender' => ['required', 'string'], 'birth_date' => ['nullable', 'date'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'guardians' => [
            'module' => 'academic',
            'label' => 'Phu huynh',
            'model' => Guardian::class,
            'permission' => 'academic.guardians',
            'columns' => ['full_name', 'phone', 'email', 'relationship', 'status'],
            'fields' => [['name' => 'user_id', 'label' => 'Tai khoan', 'type' => 'select', 'lookup' => ['model' => User::class, 'value' => 'id', 'label' => ['name', 'email']]], ['name' => 'full_name', 'label' => 'Ho ten', 'type' => 'text', 'required' => true], ['name' => 'phone', 'label' => 'Dien thoai', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'relationship', 'label' => 'Quan he', 'type' => 'text'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['user_id' => ['nullable', 'exists:users,id'], 'full_name' => ['required', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'relationship' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['user_id' => ['nullable', 'exists:users,id'], 'full_name' => ['required', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'relationship' => ['nullable', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'student_class_enrollments' => [
            'module' => 'academic',
            'label' => 'Xep lop',
            'model' => ClassEnrollment::class,
            'permission' => 'academic.student_class_enrollments',
            'columns' => ['student_id', 'class_id', 'school_year_id', 'semester_id', 'status'],
            'fields' => [['name' => 'student_id', 'label' => 'Hoc sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup, 'required' => true], ['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoc', 'transferred' => 'Da chuyen', 'completed' => 'Hoan thanh'], 'required' => true]],
            'validation' => ['store' => ['student_id' => ['required', 'exists:students,id'], 'class_id' => ['required', 'exists:classes,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'status' => ['required', 'string']], 'update' => ['student_id' => ['required', 'exists:students,id'], 'class_id' => ['required', 'exists:classes,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'teaching_assignments' => [
            'module' => 'academic',
            'label' => 'Phan cong giang day',
            'model' => TeachingAssignment::class,
            'permission' => 'academic.teaching_assignments',
            'columns' => ['teacher_id', 'class_id', 'subject_id', 'semester_id', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'teacher_id', 'label' => 'Giao vien', 'type' => 'select', 'lookup' => $teacherLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup, 'required' => true], ['name' => 'subject_id', 'label' => 'Mon', 'type' => 'select', 'lookup' => $subjectLookup, 'required' => true], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang hoat dong', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'teacher_id' => ['required', 'exists:teachers,id'], 'class_id' => ['required', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'teacher_id' => ['required', 'exists:teachers,id'], 'class_id' => ['required', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'score_types' => [
            'module' => 'assessment',
            'label' => 'Loai diem',
            'model' => ScoreCategory::class,
            'permission' => 'assessment.score_types',
            'columns' => ['code', 'name', 'weight', 'input_type', 'counts_toward_average', 'status'],
            'fields' => [['name' => 'code', 'label' => 'Ma', 'type' => 'text', 'required' => true], ['name' => 'name', 'label' => 'Ten loai diem', 'type' => 'text', 'required' => true], ['name' => 'weight', 'label' => 'He so', 'type' => 'number', 'step' => '0.1', 'required' => true], ['name' => 'input_type', 'label' => 'Kieu nhap', 'type' => 'select', 'options' => ['numeric' => 'Diem so', 'comment' => 'Nhan xet'], 'required' => true], ['name' => 'counts_toward_average', 'label' => 'Tinh diem TB', 'type' => 'checkbox'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang dung', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'weight' => ['required', 'numeric'], 'input_type' => ['required', 'string', 'in:numeric,comment'], 'counts_toward_average' => ['boolean'], 'status' => ['required', 'string']], 'update' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'weight' => ['required', 'numeric'], 'input_type' => ['required', 'string', 'in:numeric,comment'], 'counts_toward_average' => ['boolean'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'score_columns' => [
            'module' => 'assessment',
            'label' => 'Cot diem',
            'model' => ScoreColumn::class,
            'permission' => 'assessment.score_columns',
            'columns' => ['code', 'name', 'class_id', 'subject_id', 'semester_id', 'score_type_id', 'lock_status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup], ['name' => 'subject_id', 'label' => 'Mon', 'type' => 'select', 'lookup' => $subjectLookup, 'required' => true], ['name' => 'score_type_id', 'label' => 'Loai diem', 'type' => 'select', 'lookup' => ['model' => ScoreCategory::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'code', 'label' => 'Ma cot', 'type' => 'text', 'required' => true], ['name' => 'name', 'label' => 'Ten cot', 'type' => 'text', 'required' => true], ['name' => 'order_index', 'label' => 'Thu tu', 'type' => 'number', 'required' => true], ['name' => 'max_score', 'label' => 'Diem toi da', 'type' => 'number', 'step' => '0.25', 'required' => true], ['name' => 'lock_status', 'label' => 'Trang thai khoa', 'type' => 'select', 'options' => ['open' => 'Dang mo', 'locked' => 'Da khoa', 'unlock_requested' => 'Yeu cau mo khoa'], 'required' => true], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang dung', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'score_type_id' => ['required', 'exists:score_types,id'], 'code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string'], 'order_index' => ['required', 'integer'], 'max_score' => ['required', 'numeric', 'min:0'], 'lock_status' => ['required', 'string'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'score_type_id' => ['required', 'exists:score_types,id'], 'code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string'], 'order_index' => ['required', 'integer'], 'max_score' => ['required', 'numeric', 'min:0'], 'lock_status' => ['required', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'student_scores' => [
            'module' => 'assessment',
            'label' => 'Diem hoc tap',
            'model' => ScoreEntry::class,
            'permission' => 'assessment.student_scores',
            'columns' => ['student_id', 'subject_id', 'semester_id', 'score_column_id', 'score', 'comment', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup], ['name' => 'student_id', 'label' => 'Hoc sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'subject_id', 'label' => 'Mon', 'type' => 'select', 'lookup' => $subjectLookup, 'required' => true], ['name' => 'score_type_id', 'label' => 'Loai diem', 'type' => 'select', 'lookup' => ['model' => ScoreCategory::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'score_column_id', 'label' => 'Cot diem', 'type' => 'select', 'lookup' => ['model' => ScoreColumn::class, 'value' => 'id', 'label' => ['code', 'name']]], ['name' => 'score', 'label' => 'Diem', 'type' => 'number', 'step' => '0.25'], ['name' => 'comment', 'label' => 'Nhan xet', 'type' => 'textarea'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['draft' => 'Nhap', 'submitted' => 'Da nop', 'locked' => 'Da khoa'], 'required' => true], ['name' => 'note', 'label' => 'Ghi chu', 'type' => 'textarea']],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'student_id' => ['required', 'exists:students,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'score_type_id' => ['required', 'exists:score_types,id'], 'score_column_id' => ['nullable', 'exists:score_columns,id'], 'score' => ['nullable', 'numeric', 'min:0', 'max:10'], 'comment' => ['nullable', 'string'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'student_id' => ['required', 'exists:students,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'score_type_id' => ['required', 'exists:score_types,id'], 'score_column_id' => ['nullable', 'exists:score_columns,id'], 'score' => ['nullable', 'numeric', 'min:0', 'max:10'], 'comment' => ['nullable', 'string'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']]],
            'audit' => true,
            'revision' => ['model' => ScoreRevision::class, 'foreign_key' => 'student_score_id'],
        ],
        'conduct_rules' => [
            'module' => 'conduct',
            'label' => 'Tieu chi ren luyen',
            'model' => ConductRule::class,
            'permission' => 'conduct.conduct_rules',
            'columns' => ['code', 'name', 'rule_type', 'points', 'severity', 'requires_approval', 'status'],
            'fields' => [['name' => 'code', 'label' => 'Ma tieu chi', 'type' => 'text', 'required' => true], ['name' => 'name', 'label' => 'Ten tieu chi', 'type' => 'text', 'required' => true], ['name' => 'rule_type', 'label' => 'Loai', 'type' => 'select', 'options' => ['bonus' => 'Cong diem', 'deduction' => 'Tru diem'], 'required' => true], ['name' => 'points', 'label' => 'So diem', 'type' => 'number', 'required' => true], ['name' => 'severity', 'label' => 'Muc do', 'type' => 'select', 'options' => ['minor' => 'Nhe', 'normal' => 'Thong thuong', 'major' => 'Nang', 'serious' => 'Rat nang'], 'required' => true], ['name' => 'requires_approval', 'label' => 'Can duyet', 'type' => 'checkbox'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang dung', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255'], 'rule_type' => ['required', 'string', 'in:bonus,deduction'], 'points' => ['required', 'integer'], 'severity' => ['required', 'string'], 'requires_approval' => ['boolean'], 'status' => ['required', 'string']], 'update' => ['code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255'], 'rule_type' => ['required', 'string', 'in:bonus,deduction'], 'points' => ['required', 'integer'], 'severity' => ['required', 'string'], 'requires_approval' => ['boolean'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'conduct_records' => [
            'module' => 'conduct',
            'label' => 'Su kien ren luyen',
            'model' => ConductRecord::class,
            'permission' => 'conduct.conduct_records',
            'columns' => ['student_id', 'class_id', 'conduct_rule_id', 'points', 'recorded_date', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup], ['name' => 'student_id', 'label' => 'Hoc sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'conduct_rule_id', 'label' => 'Tieu chi', 'type' => 'select', 'lookup' => ['model' => ConductRule::class, 'value' => 'id', 'label' => ['code', 'name']], 'required' => true], ['name' => 'points', 'label' => 'Diem', 'type' => 'number', 'required' => true], ['name' => 'recorded_date', 'label' => 'Ngay xay ra', 'type' => 'date', 'required' => true], ['name' => 'description', 'label' => 'Mo ta', 'type' => 'textarea'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['pending' => 'Cho duyet', 'approved' => 'Da duyet', 'rejected' => 'Tu choi', 'cancelled' => 'Da huy'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'student_id' => ['required', 'exists:students,id'], 'conduct_rule_id' => ['required', 'exists:conduct_rules,id'], 'points' => ['required', 'integer'], 'recorded_date' => ['required', 'date'], 'description' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'student_id' => ['required', 'exists:students,id'], 'conduct_rule_id' => ['required', 'exists:conduct_rules,id'], 'points' => ['required', 'integer'], 'recorded_date' => ['required', 'date'], 'description' => ['nullable', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'conduct_rating_rules' => [
            'module' => 'conduct',
            'label' => 'Xep loai ren luyen',
            'model' => ConductRatingRule::class,
            'permission' => 'conduct.conduct_rating_rules',
            'columns' => ['rating', 'min_score', 'max_score', 'status'],
            'fields' => [['name' => 'rating', 'label' => 'Xep loai', 'type' => 'text', 'required' => true], ['name' => 'min_score', 'label' => 'Diem tu', 'type' => 'number', 'required' => true], ['name' => 'max_score', 'label' => 'Diem den', 'type' => 'number', 'required' => true], ['name' => 'description', 'label' => 'Mo ta', 'type' => 'textarea'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang dung', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['rating' => ['required', 'string'], 'min_score' => ['required', 'integer'], 'max_score' => ['required', 'integer'], 'description' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['rating' => ['required', 'string'], 'min_score' => ['required', 'integer'], 'max_score' => ['required', 'integer'], 'description' => ['nullable', 'string'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'conduct_scores' => [
            'module' => 'conduct',
            'label' => 'Tong hop ren luyen',
            'model' => ConductScore::class,
            'permission' => 'conduct.conduct_scores',
            'columns' => ['student_id', 'semester_id', 'base_score', 'bonus_points', 'minus_points', 'adjustment_points', 'score', 'rating', 'lock_status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup], ['name' => 'student_id', 'label' => 'Hoc sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'base_score', 'label' => 'Diem nen', 'type' => 'number', 'required' => true], ['name' => 'score', 'label' => 'Diem cuoi', 'type' => 'number', 'required' => true], ['name' => 'rating', 'label' => 'Xep loai', 'type' => 'text'], ['name' => 'lock_status', 'label' => 'Trang thai khoa', 'type' => 'select', 'options' => ['open' => 'Dang mo', 'locked' => 'Da khoa'], 'required' => true], ['name' => 'homeroom_comment', 'label' => 'Nhan xet GVCN', 'type' => 'textarea']],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'student_id' => ['required', 'exists:students,id'], 'base_score' => ['nullable', 'integer'], 'score' => ['required', 'integer'], 'rating' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'lock_status' => ['nullable', 'string'], 'homeroom_comment' => ['nullable', 'string'], 'note' => ['nullable', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'student_id' => ['required', 'exists:students,id'], 'base_score' => ['nullable', 'integer'], 'score' => ['required', 'integer'], 'rating' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'lock_status' => ['nullable', 'string'], 'homeroom_comment' => ['nullable', 'string'], 'note' => ['nullable', 'string']]],
            'audit' => true,
        ],
        'events' => [
            'module' => 'activities',
            'label' => 'Su kien/Hoi thao',
            'model' => SchoolEvent::class,
            'permission' => 'activities.events',
            'columns' => ['title', 'event_type', 'starts_at', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup], ['name' => 'title', 'label' => 'Ten su kien', 'type' => 'text', 'required' => true], ['name' => 'event_type', 'label' => 'Loai', 'type' => 'select', 'options' => ['stem' => 'STEM', 'sports' => 'Hoi thao', 'contest' => 'Hoi thi', 'movement' => 'Phong trao'], 'required' => true], ['name' => 'starts_at', 'label' => 'Bat dau', 'type' => 'datetime-local'], ['name' => 'ends_at', 'label' => 'Ket thuc', 'type' => 'datetime-local'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['draft' => 'Nhap', 'open' => 'Dang mo', 'closed' => 'Da dong'], 'required' => true], ['name' => 'description', 'label' => 'Mo ta', 'type' => 'textarea']],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'title' => ['required', 'string'], 'event_type' => ['required', 'string'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'description' => ['nullable', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'title' => ['required', 'string'], 'event_type' => ['required', 'string'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'description' => ['nullable', 'string']]],
        ],
        'fee_types' => [
            'module' => 'finance',
            'label' => 'Loai khoan thu',
            'model' => FeeCategory::class,
            'permission' => 'finance.fee_types',
            'columns' => ['code', 'name', 'default_amount', 'status'],
            'fields' => [['name' => 'code', 'label' => 'Ma', 'type' => 'text', 'required' => true], ['name' => 'name', 'label' => 'Ten khoan thu', 'type' => 'text', 'required' => true], ['name' => 'default_amount', 'label' => 'So tien mac dinh', 'type' => 'number', 'required' => true], ['name' => 'is_required', 'label' => 'Bat buoc', 'type' => 'checkbox'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang dung', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'default_amount' => ['required', 'numeric'], 'is_required' => ['boolean'], 'status' => ['required', 'string']], 'update' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'default_amount' => ['required', 'numeric'], 'is_required' => ['boolean'], 'status' => ['required', 'string']]],
        ],
        'fee_plans' => [
            'module' => 'finance',
            'label' => 'Ke hoach thu',
            'model' => FeePlan::class,
            'permission' => 'finance.fee_plans',
            'columns' => ['fee_type_id', 'school_year_id', 'semester_id', 'amount', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup], ['name' => 'fee_type_id', 'label' => 'Loai thu', 'type' => 'select', 'lookup' => ['model' => FeeCategory::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'amount', 'label' => 'So tien', 'type' => 'number', 'required' => true], ['name' => 'applies_to', 'label' => 'Ap dung', 'type' => 'text'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['active' => 'Dang dung', 'inactive' => 'Tam dung'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'fee_type_id' => ['required', 'exists:fee_types,id'], 'amount' => ['required', 'numeric'], 'applies_to' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'fee_type_id' => ['required', 'exists:fee_types,id'], 'amount' => ['required', 'numeric'], 'applies_to' => ['nullable', 'string'], 'status' => ['required', 'string']]],
        ],
        'student_fees' => [
            'module' => 'finance',
            'label' => 'Hoc phi hoc sinh',
            'model' => FeeInvoice::class,
            'permission' => 'finance.student_fees',
            'columns' => ['invoice_no', 'student_id', 'total_amount', 'paid_amount', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup], ['name' => 'student_id', 'label' => 'Hoc sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lop', 'type' => 'select', 'lookup' => $classLookup], ['name' => 'fee_plan_id', 'label' => 'Ke hoach thu', 'type' => 'select', 'lookup' => ['model' => FeePlan::class, 'value' => 'id', 'label' => ['id', 'amount']]], ['name' => 'invoice_no', 'label' => 'So phieu', 'type' => 'text', 'required' => true], ['name' => 'total_amount', 'label' => 'Tong tien', 'type' => 'number', 'required' => true], ['name' => 'paid_amount', 'label' => 'Da thu', 'type' => 'number', 'required' => true], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['unpaid' => 'Chua thu', 'partial' => 'Thu mot phan', 'paid' => 'Da thu'], 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'student_id' => ['required', 'exists:students,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'fee_plan_id' => ['nullable', 'exists:fee_plans,id'], 'invoice_no' => ['required', 'string'], 'total_amount' => ['required', 'numeric'], 'paid_amount' => ['required', 'numeric'], 'status' => ['required', 'string']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'student_id' => ['required', 'exists:students,id'], 'class_id' => ['nullable', 'exists:classes,id'], 'fee_plan_id' => ['nullable', 'exists:fee_plans,id'], 'invoice_no' => ['required', 'string'], 'total_amount' => ['required', 'numeric'], 'paid_amount' => ['required', 'numeric'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'payments' => [
            'module' => 'finance',
            'label' => 'Thanh toan',
            'model' => Payment::class,
            'permission' => 'finance.payments',
            'columns' => ['student_fee_id', 'amount', 'method', 'paid_at', 'status'],
            'fields' => [['name' => 'student_fee_id', 'label' => 'Khoan thu hoc sinh', 'type' => 'select', 'lookup' => ['model' => FeeInvoice::class, 'value' => 'id', 'label' => ['invoice_no']], 'required' => true], ['name' => 'amount', 'label' => 'So tien', 'type' => 'number', 'required' => true], ['name' => 'method', 'label' => 'Phuong thuc', 'type' => 'select', 'options' => ['cash' => 'Tien mat', 'transfer' => 'Chuyen khoan'], 'required' => true], ['name' => 'paid_at', 'label' => 'Thoi diem thu', 'type' => 'datetime-local', 'required' => true], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['completed' => 'Hoan tat', 'refunded' => 'Da hoan'], 'required' => true], ['name' => 'note', 'label' => 'Ghi chu', 'type' => 'textarea']],
            'validation' => ['store' => ['student_fee_id' => ['required', 'exists:student_fees,id'], 'amount' => ['required', 'numeric'], 'method' => ['required', 'string'], 'paid_at' => ['required', 'date'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']], 'update' => ['student_fee_id' => ['required', 'exists:student_fees,id'], 'amount' => ['required', 'numeric'], 'method' => ['required', 'string'], 'paid_at' => ['required', 'date'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']]],
            'audit' => true,
        ],
        'announcements' => [
            'module' => 'communication',
            'label' => 'Thong bao',
            'model' => Announcement::class,
            'permission' => 'communication.announcements',
            'columns' => ['title', 'audience', 'published_at', 'status'],
            'fields' => [['name' => 'school_year_id', 'label' => 'Nam hoc', 'type' => 'select', 'lookup' => $yearLookup], ['name' => 'semester_id', 'label' => 'Hoc ky', 'type' => 'select', 'lookup' => $semesterLookup], ['name' => 'title', 'label' => 'Tieu de', 'type' => 'text', 'required' => true], ['name' => 'audience', 'label' => 'Doi tuong', 'type' => 'select', 'options' => ['all' => 'Toan truong', 'staff' => 'Giao vien', 'students' => 'Hoc sinh', 'guardians' => 'Phu huynh'], 'required' => true], ['name' => 'published_at', 'label' => 'Ngay dang', 'type' => 'datetime-local'], ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'options' => ['draft' => 'Nhap', 'published' => 'Da dang'], 'required' => true], ['name' => 'body', 'label' => 'Noi dung', 'type' => 'textarea', 'required' => true]],
            'validation' => ['store' => ['school_year_id' => ['nullable', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'title' => ['required', 'string'], 'audience' => ['required', 'string'], 'published_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'body' => ['required', 'string']], 'update' => ['school_year_id' => ['nullable', 'exists:school_years,id'], 'semester_id' => ['nullable', 'exists:semesters,id'], 'title' => ['required', 'string'], 'audience' => ['required', 'string'], 'published_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'body' => ['required', 'string']]],
        ],
    ],
];
