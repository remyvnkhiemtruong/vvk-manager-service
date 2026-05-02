<?php

use App\Models\Announcement;
use App\Models\ClassEnrollment;
use App\Models\Commendation;
use App\Models\CommendationRecipient;
use App\Models\ConductRevision;
use App\Models\ConductScore;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\EventRegistration;
use App\Models\EventResult;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePlan;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolYear;
use App\Models\ScoreCategory;
use App\Models\ScoreEntry;
use App\Models\ScoreRevision;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

$statusOptions = [
    'active' => 'Đang hoạt động',
    'inactive' => 'Tạm dừng',
];

$genderOptions = [
    'female' => 'Nữ',
    'male' => 'Nam',
    'other' => 'Khác',
];

$classLookup = ['model' => SchoolClass::class, 'value' => 'id', 'label' => ['name']];
$studentLookup = ['model' => Student::class, 'value' => 'id', 'label' => ['student_code', 'full_name']];
$staffLookup = ['model' => Staff::class, 'value' => 'id', 'label' => ['staff_code', 'full_name']];
$subjectLookup = ['model' => Subject::class, 'value' => 'id', 'label' => ['code', 'name']];
$yearLookup = ['model' => SchoolYear::class, 'value' => 'id', 'label' => ['name']];
$semesterLookup = ['model' => Semester::class, 'value' => 'id', 'label' => ['name']];

return [
    'school' => [
        'name' => 'Trường THPT Võ Văn Kiệt',
        'address' => 'Số 10B - Ấp Long Hòa - Xã Phước Long - Tỉnh Cà Mau',
        'email' => 'c3phuoclong.sobaclieu@moet.edu.vn',
        'website' => 'https://thptvovankiet.sgdcamau.edu.vn/',
    ],

    'module_labels' => [
        'identity' => 'Tài khoản & phân quyền',
        'academic' => 'Hồ sơ học vụ',
        'assessment' => 'Điểm số',
        'conduct' => 'Rèn luyện & kỷ luật',
        'activities' => 'Phong trào',
        'finance' => 'Học phí',
        'communication' => 'Thông báo',
    ],

    'roles' => [
        'admin' => 'Admin',
        'bgh' => 'Ban giám hiệu',
        'giao_vu' => 'Giáo vụ',
        'gvcn' => 'Giáo viên chủ nhiệm',
        'giao_vien_bo_mon' => 'Giáo viên bộ môn',
        'doan_truong' => 'Đoàn trường/BTC phong trào',
        'giam_thi' => 'Giám thị',
        'ke_toan' => 'Kế toán',
        'phu_huynh' => 'Phụ huynh',
        'hoc_sinh' => 'Học sinh',
    ],

    'role_permissions' => [
        'admin' => ['*'],
        'bgh' => ['dashboard.view', 'reports.view', 'audit.view', 'portal.view', 'identity.*', 'academic.*', 'assessment.*', 'conduct.*', 'activities.*', 'finance.*', 'communication.*'],
        'giao_vu' => ['dashboard.view', 'reports.view', 'portal.view', 'academic.*', 'communication.announcements.*', 'assessment.score_entries.view', 'conduct.conduct_scores.view'],
        'gvcn' => ['dashboard.view', 'portal.view', 'academic.students.view', 'academic.classes.view', 'assessment.score_entries.view', 'conduct.conduct_scores.*', 'conduct.disciplinary_cases.*', 'conduct.commendations.*', 'communication.announcements.view'],
        'giao_vien_bo_mon' => ['dashboard.view', 'portal.view', 'academic.classes.view', 'academic.subjects.view', 'academic.teaching_assignments.view', 'assessment.score_entries.*', 'communication.announcements.view'],
        'doan_truong' => ['dashboard.view', 'portal.view', 'activities.*', 'conduct.commendations.*', 'communication.announcements.*'],
        'giam_thi' => ['dashboard.view', 'portal.view', 'conduct.conduct_scores.*', 'conduct.disciplinary_cases.*', 'conduct.disciplinary_actions.*', 'communication.announcements.view'],
        'ke_toan' => ['dashboard.view', 'portal.view', 'finance.*', 'communication.announcements.view'],
        'phu_huynh' => ['dashboard.view', 'portal.view', 'communication.announcements.view'],
        'hoc_sinh' => ['dashboard.view', 'portal.view', 'communication.announcements.view'],
    ],

    'resources' => [
        'users' => [
            'module' => 'identity',
            'label' => 'Tài khoản',
            'model' => User::class,
            'permission' => 'identity.users',
            'columns' => ['name', 'email', 'status', 'role_ids'],
            'search' => ['name', 'email'],
            'fields' => [
                ['name' => 'name', 'label' => 'Họ tên', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['name' => 'password', 'label' => 'Mật khẩu', 'type' => 'password', 'storeOnlyRequired' => true, 'skipEmptyOnUpdate' => true],
                ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => $statusOptions, 'required' => true],
                ['name' => 'role_ids', 'label' => 'Vai trò', 'type' => 'multiselect', 'lookup' => ['model' => Role::class, 'value' => 'id', 'label' => ['name']]],
            ],
            'validation' => [
                'store' => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'string', 'min:8'], 'status' => ['required', 'string'], 'role_ids' => ['array'], 'role_ids.*' => ['integer', 'exists:roles,id']],
                'update' => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], 'password' => ['nullable', 'string', 'min:8'], 'status' => ['required', 'string'], 'role_ids' => ['array'], 'role_ids.*' => ['integer', 'exists:roles,id']],
            ],
            'sync' => ['role_ids' => 'roles'],
            'audit' => true,
        ],
        'roles' => [
            'module' => 'identity',
            'label' => 'Vai trò',
            'model' => Role::class,
            'permission' => 'identity.roles',
            'columns' => ['name', 'slug', 'permission_ids'],
            'fields' => [
                ['name' => 'name', 'label' => 'Tên vai trò', 'type' => 'text', 'required' => true],
                ['name' => 'slug', 'label' => 'Mã vai trò', 'type' => 'text', 'required' => true],
                ['name' => 'permission_ids', 'label' => 'Quyền', 'type' => 'multiselect', 'lookup' => ['model' => Permission::class, 'value' => 'id', 'label' => ['key']]],
            ],
            'validation' => [
                'store' => ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:80', 'unique:roles,slug'], 'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']],
                'update' => ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:80'], 'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']],
            ],
            'sync' => ['permission_ids' => 'permissions'],
            'audit' => true,
        ],
        'permissions' => [
            'module' => 'identity',
            'label' => 'Quyền chi tiết',
            'model' => Permission::class,
            'permission' => 'identity.permissions',
            'columns' => ['key', 'name', 'module'],
            'fields' => [
                ['name' => 'key', 'label' => 'Permission key', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => 'Tên quyền', 'type' => 'text', 'required' => true],
                ['name' => 'module', 'label' => 'Module', 'type' => 'text', 'required' => true],
            ],
            'validation' => ['store' => ['key' => ['required', 'string', 'unique:permissions,key'], 'name' => ['required', 'string'], 'module' => ['required', 'string']], 'update' => ['key' => ['required', 'string'], 'name' => ['required', 'string'], 'module' => ['required', 'string']]],
            'audit' => true,
        ],
        'school_years' => [
            'module' => 'academic',
            'label' => 'Năm học',
            'model' => SchoolYear::class,
            'permission' => 'academic.school_years',
            'columns' => ['name', 'start_date', 'end_date', 'is_active'],
            'fields' => [
                ['name' => 'name', 'label' => 'Tên năm học', 'type' => 'text', 'required' => true],
                ['name' => 'start_date', 'label' => 'Ngày bắt đầu', 'type' => 'date', 'required' => true],
                ['name' => 'end_date', 'label' => 'Ngày kết thúc', 'type' => 'date', 'required' => true],
                ['name' => 'is_active', 'label' => 'Đang dùng', 'type' => 'checkbox'],
            ],
            'validation' => ['store' => ['name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean']], 'update' => ['name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean']]],
        ],
        'semesters' => [
            'module' => 'academic',
            'label' => 'Học kỳ',
            'model' => Semester::class,
            'permission' => 'academic.semesters',
            'columns' => ['name', 'school_year_id', 'start_date', 'end_date', 'is_active'],
            'fields' => [
                ['name' => 'school_year_id', 'label' => 'Năm học', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true],
                ['name' => 'name', 'label' => 'Tên học kỳ', 'type' => 'text', 'required' => true],
                ['name' => 'start_date', 'label' => 'Ngày bắt đầu', 'type' => 'date', 'required' => true],
                ['name' => 'end_date', 'label' => 'Ngày kết thúc', 'type' => 'date', 'required' => true],
                ['name' => 'is_active', 'label' => 'Đang dùng', 'type' => 'checkbox'],
            ],
            'validation' => ['store' => ['school_year_id' => ['required', 'exists:school_years,id'], 'name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean']], 'update' => ['school_year_id' => ['required', 'exists:school_years,id'], 'name' => ['required', 'string'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'is_active' => ['boolean']]],
        ],
        'classes' => [
            'module' => 'academic',
            'label' => 'Lớp học',
            'model' => SchoolClass::class,
            'permission' => 'academic.classes',
            'columns' => ['name', 'grade_id', 'school_year_id', 'homeroom_teacher_id', 'room'],
            'fields' => [
                ['name' => 'name', 'label' => 'Tên lớp', 'type' => 'text', 'required' => true],
                ['name' => 'grade_id', 'label' => 'Khối', 'type' => 'select', 'lookup' => ['model' => App\Models\Grade::class, 'value' => 'id', 'label' => ['name']], 'required' => true],
                ['name' => 'school_year_id', 'label' => 'Năm học', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true],
                ['name' => 'homeroom_teacher_id', 'label' => 'GVCN', 'type' => 'select', 'lookup' => $staffLookup],
                ['name' => 'room', 'label' => 'Phòng', 'type' => 'text'],
            ],
            'validation' => ['store' => ['name' => ['required', 'string'], 'grade_id' => ['required', 'exists:grades,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'homeroom_teacher_id' => ['nullable', 'exists:staff,id'], 'room' => ['nullable', 'string']], 'update' => ['name' => ['required', 'string'], 'grade_id' => ['required', 'exists:grades,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'homeroom_teacher_id' => ['nullable', 'exists:staff,id'], 'room' => ['nullable', 'string']]],
        ],
        'subjects' => [
            'module' => 'academic',
            'label' => 'Môn học',
            'model' => Subject::class,
            'permission' => 'academic.subjects',
            'columns' => ['code', 'name', 'department'],
            'fields' => [['name' => 'code', 'label' => 'Mã môn', 'type' => 'text', 'required' => true], ['name' => 'name', 'label' => 'Tên môn', 'type' => 'text', 'required' => true], ['name' => 'department', 'label' => 'Tổ chuyên môn', 'type' => 'text']],
            'validation' => ['store' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'department' => ['nullable', 'string']], 'update' => ['code' => ['required', 'string'], 'name' => ['required', 'string'], 'department' => ['nullable', 'string']]],
        ],
        'students' => [
            'module' => 'academic',
            'label' => 'Học sinh',
            'model' => Student::class,
            'permission' => 'academic.students',
            'columns' => ['student_code', 'full_name', 'gender', 'birth_date', 'status'],
            'search' => ['student_code', 'full_name'],
            'fields' => [
                ['name' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'lookup' => ['model' => User::class, 'value' => 'id', 'label' => ['name', 'email']]],
                ['name' => 'student_code', 'label' => 'Mã học sinh', 'type' => 'text', 'required' => true],
                ['name' => 'full_name', 'label' => 'Họ tên', 'type' => 'text', 'required' => true],
                ['name' => 'gender', 'label' => 'Giới tính', 'type' => 'select', 'options' => $genderOptions, 'required' => true],
                ['name' => 'birth_date', 'label' => 'Ngày sinh', 'type' => 'date'],
                ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => $statusOptions, 'required' => true],
            ],
            'validation' => ['store' => ['user_id' => ['nullable', 'exists:users,id'], 'student_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'gender' => ['required', 'string'], 'birth_date' => ['nullable', 'date'], 'status' => ['required', 'string']], 'update' => ['user_id' => ['nullable', 'exists:users,id'], 'student_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'gender' => ['required', 'string'], 'birth_date' => ['nullable', 'date'], 'status' => ['required', 'string']]],
        ],
        'guardians' => [
            'module' => 'academic',
            'label' => 'Phụ huynh',
            'model' => Guardian::class,
            'permission' => 'academic.guardians',
            'columns' => ['full_name', 'phone', 'email', 'relationship'],
            'fields' => [['name' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'lookup' => ['model' => User::class, 'value' => 'id', 'label' => ['name', 'email']]], ['name' => 'full_name', 'label' => 'Họ tên', 'type' => 'text', 'required' => true], ['name' => 'phone', 'label' => 'Điện thoại', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'relationship', 'label' => 'Quan hệ', 'type' => 'text']],
            'validation' => ['store' => ['user_id' => ['nullable', 'exists:users,id'], 'full_name' => ['required', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'relationship' => ['nullable', 'string']], 'update' => ['user_id' => ['nullable', 'exists:users,id'], 'full_name' => ['required', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'relationship' => ['nullable', 'string']]],
        ],
        'staff' => [
            'module' => 'academic',
            'label' => 'Giáo viên/Nhân sự',
            'model' => Staff::class,
            'permission' => 'academic.staff',
            'columns' => ['staff_code', 'full_name', 'position', 'department', 'status'],
            'search' => ['staff_code', 'full_name'],
            'fields' => [['name' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'lookup' => ['model' => User::class, 'value' => 'id', 'label' => ['name', 'email']]], ['name' => 'staff_code', 'label' => 'Mã nhân sự', 'type' => 'text', 'required' => true], ['name' => 'full_name', 'label' => 'Họ tên', 'type' => 'text', 'required' => true], ['name' => 'position', 'label' => 'Chức vụ', 'type' => 'text'], ['name' => 'department', 'label' => 'Tổ/Bộ phận', 'type' => 'text'], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => $statusOptions, 'required' => true]],
            'validation' => ['store' => ['user_id' => ['nullable', 'exists:users,id'], 'staff_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'position' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['user_id' => ['nullable', 'exists:users,id'], 'staff_code' => ['required', 'string'], 'full_name' => ['required', 'string'], 'position' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'status' => ['required', 'string']]],
        ],
        'teaching_assignments' => [
            'module' => 'academic',
            'label' => 'Phân công giảng dạy',
            'model' => TeachingAssignment::class,
            'permission' => 'academic.teaching_assignments',
            'columns' => ['teacher_id', 'class_id', 'subject_id', 'semester_id'],
            'fields' => [['name' => 'teacher_id', 'label' => 'Giáo viên', 'type' => 'select', 'lookup' => $staffLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lớp', 'type' => 'select', 'lookup' => $classLookup, 'required' => true], ['name' => 'subject_id', 'label' => 'Môn', 'type' => 'select', 'lookup' => $subjectLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Học kỳ', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true]],
            'validation' => ['store' => ['teacher_id' => ['required', 'exists:staff,id'], 'class_id' => ['required', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'semester_id' => ['required', 'exists:semesters,id']], 'update' => ['teacher_id' => ['required', 'exists:staff,id'], 'class_id' => ['required', 'exists:classes,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'semester_id' => ['required', 'exists:semesters,id']]],
        ],
        'class_enrollments' => [
            'module' => 'academic',
            'label' => 'Xếp lớp/Chuyển lớp',
            'model' => ClassEnrollment::class,
            'permission' => 'academic.class_enrollments',
            'columns' => ['student_id', 'class_id', 'school_year_id', 'status'],
            'fields' => [['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'class_id', 'label' => 'Lớp', 'type' => 'select', 'lookup' => $classLookup, 'required' => true], ['name' => 'school_year_id', 'label' => 'Năm học', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['active' => 'Đang học', 'transferred' => 'Đã chuyển', 'completed' => 'Hoàn thành'], 'required' => true]],
            'validation' => ['store' => ['student_id' => ['required', 'exists:students,id'], 'class_id' => ['required', 'exists:classes,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'status' => ['required', 'string']], 'update' => ['student_id' => ['required', 'exists:students,id'], 'class_id' => ['required', 'exists:classes,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'score_categories' => [
            'module' => 'assessment',
            'label' => 'Loại điểm',
            'model' => ScoreCategory::class,
            'permission' => 'assessment.score_categories',
            'columns' => ['name', 'code', 'weight'],
            'fields' => [['name' => 'name', 'label' => 'Tên loại điểm', 'type' => 'text', 'required' => true], ['name' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true], ['name' => 'weight', 'label' => 'Hệ số', 'type' => 'number', 'step' => '0.1', 'required' => true]],
            'validation' => ['store' => ['name' => ['required', 'string'], 'code' => ['required', 'string'], 'weight' => ['required', 'numeric', 'min:0']], 'update' => ['name' => ['required', 'string'], 'code' => ['required', 'string'], 'weight' => ['required', 'numeric', 'min:0']]],
        ],
        'score_entries' => [
            'module' => 'assessment',
            'label' => 'Điểm số',
            'model' => ScoreEntry::class,
            'permission' => 'assessment.score_entries',
            'columns' => ['student_id', 'subject_id', 'semester_id', 'score_category_id', 'score', 'status'],
            'fields' => [['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'subject_id', 'label' => 'Môn', 'type' => 'select', 'lookup' => $subjectLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Học kỳ', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'score_category_id', 'label' => 'Loại điểm', 'type' => 'select', 'lookup' => ['model' => ScoreCategory::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'score', 'label' => 'Điểm', 'type' => 'number', 'step' => '0.25', 'required' => true], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['draft' => 'Nháp', 'submitted' => 'Đã nộp', 'locked' => 'Đã khóa'], 'required' => true], ['name' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea']],
            'validation' => ['store' => ['student_id' => ['required', 'exists:students,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'score_category_id' => ['required', 'exists:score_categories,id'], 'score' => ['required', 'numeric', 'min:0', 'max:10'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']], 'update' => ['student_id' => ['required', 'exists:students,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'score_category_id' => ['required', 'exists:score_categories,id'], 'score' => ['required', 'numeric', 'min:0', 'max:10'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']]],
            'audit' => true,
            'revision' => ['model' => ScoreRevision::class, 'foreign_key' => 'score_entry_id'],
        ],
        'conduct_scores' => [
            'module' => 'conduct',
            'label' => 'Điểm rèn luyện',
            'model' => ConductScore::class,
            'permission' => 'conduct.conduct_scores',
            'columns' => ['student_id', 'semester_id', 'score', 'rating', 'status'],
            'fields' => [['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'semester_id', 'label' => 'Học kỳ', 'type' => 'select', 'lookup' => $semesterLookup, 'required' => true], ['name' => 'score', 'label' => 'Điểm', 'type' => 'number', 'required' => true], ['name' => 'rating', 'label' => 'Xếp loại', 'type' => 'select', 'options' => ['tot' => 'Tốt', 'kha' => 'Khá', 'dat' => 'Đạt', 'chua_dat' => 'Chưa đạt'], 'required' => true], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['draft' => 'Nháp', 'approved' => 'Đã duyệt'], 'required' => true], ['name' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea']],
            'validation' => ['store' => ['student_id' => ['required', 'exists:students,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'score' => ['required', 'integer', 'min:0', 'max:100'], 'rating' => ['required', 'string'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']], 'update' => ['student_id' => ['required', 'exists:students,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'score' => ['required', 'integer', 'min:0', 'max:100'], 'rating' => ['required', 'string'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string']]],
            'audit' => true,
            'revision' => ['model' => ConductRevision::class, 'foreign_key' => 'conduct_score_id'],
        ],
        'disciplinary_cases' => [
            'module' => 'conduct',
            'label' => 'Hồ sơ kỷ luật',
            'model' => DisciplinaryCase::class,
            'permission' => 'conduct.disciplinary_cases',
            'columns' => ['student_id', 'incident_date', 'severity', 'status'],
            'fields' => [['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'incident_date', 'label' => 'Ngày vi phạm', 'type' => 'date', 'required' => true], ['name' => 'severity', 'label' => 'Mức độ', 'type' => 'select', 'options' => ['low' => 'Nhẹ', 'medium' => 'Trung bình', 'high' => 'Nghiêm trọng'], 'required' => true], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['open' => 'Đang xử lý', 'closed' => 'Đã đóng'], 'required' => true], ['name' => 'description', 'label' => 'Mô tả', 'type' => 'textarea', 'required' => true]],
            'validation' => ['store' => ['student_id' => ['required', 'exists:students,id'], 'incident_date' => ['required', 'date'], 'severity' => ['required', 'string'], 'status' => ['required', 'string'], 'description' => ['required', 'string']], 'update' => ['student_id' => ['required', 'exists:students,id'], 'incident_date' => ['required', 'date'], 'severity' => ['required', 'string'], 'status' => ['required', 'string'], 'description' => ['required', 'string']]],
            'audit' => true,
        ],
        'disciplinary_actions' => [
            'module' => 'conduct',
            'label' => 'Biện pháp kỷ luật',
            'model' => DisciplinaryAction::class,
            'permission' => 'conduct.disciplinary_actions',
            'columns' => ['disciplinary_case_id', 'action_type', 'action_date', 'issued_by'],
            'fields' => [['name' => 'disciplinary_case_id', 'label' => 'Hồ sơ', 'type' => 'select', 'lookup' => ['model' => DisciplinaryCase::class, 'value' => 'id', 'label' => ['id', 'severity', 'status']], 'required' => true], ['name' => 'action_type', 'label' => 'Biện pháp', 'type' => 'text', 'required' => true], ['name' => 'action_date', 'label' => 'Ngày áp dụng', 'type' => 'date', 'required' => true], ['name' => 'issued_by', 'label' => 'Người ban hành', 'type' => 'select', 'lookup' => $staffLookup], ['name' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea']],
            'validation' => ['store' => ['disciplinary_case_id' => ['required', 'exists:disciplinary_cases,id'], 'action_type' => ['required', 'string'], 'action_date' => ['required', 'date'], 'issued_by' => ['nullable', 'exists:staff,id'], 'note' => ['nullable', 'string']], 'update' => ['disciplinary_case_id' => ['required', 'exists:disciplinary_cases,id'], 'action_type' => ['required', 'string'], 'action_date' => ['required', 'date'], 'issued_by' => ['nullable', 'exists:staff,id'], 'note' => ['nullable', 'string']]],
            'audit' => true,
        ],
        'commendations' => [
            'module' => 'conduct',
            'label' => 'Khen thưởng',
            'model' => Commendation::class,
            'permission' => 'conduct.commendations',
            'columns' => ['title', 'category', 'issued_date', 'issued_by'],
            'fields' => [['name' => 'title', 'label' => 'Tiêu đề', 'type' => 'text', 'required' => true], ['name' => 'category', 'label' => 'Loại', 'type' => 'text'], ['name' => 'issued_date', 'label' => 'Ngày khen thưởng', 'type' => 'date', 'required' => true], ['name' => 'issued_by', 'label' => 'Người ký', 'type' => 'select', 'lookup' => $staffLookup], ['name' => 'description', 'label' => 'Nội dung', 'type' => 'textarea']],
            'validation' => ['store' => ['title' => ['required', 'string'], 'category' => ['nullable', 'string'], 'issued_date' => ['required', 'date'], 'issued_by' => ['nullable', 'exists:staff,id'], 'description' => ['nullable', 'string']], 'update' => ['title' => ['required', 'string'], 'category' => ['nullable', 'string'], 'issued_date' => ['required', 'date'], 'issued_by' => ['nullable', 'exists:staff,id'], 'description' => ['nullable', 'string']]],
        ],
        'commendation_recipients' => [
            'module' => 'conduct',
            'label' => 'Người nhận khen thưởng',
            'model' => CommendationRecipient::class,
            'permission' => 'conduct.commendation_recipients',
            'columns' => ['commendation_id', 'student_id', 'staff_id', 'recipient_name'],
            'fields' => [['name' => 'commendation_id', 'label' => 'Khen thưởng', 'type' => 'select', 'lookup' => ['model' => Commendation::class, 'value' => 'id', 'label' => ['title']], 'required' => true], ['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup], ['name' => 'staff_id', 'label' => 'Nhân sự', 'type' => 'select', 'lookup' => $staffLookup], ['name' => 'recipient_name', 'label' => 'Tên ngoài hệ thống', 'type' => 'text']],
            'validation' => ['store' => ['commendation_id' => ['required', 'exists:commendations,id'], 'student_id' => ['nullable', 'exists:students,id'], 'staff_id' => ['nullable', 'exists:staff,id'], 'recipient_name' => ['nullable', 'string']], 'update' => ['commendation_id' => ['required', 'exists:commendations,id'], 'student_id' => ['nullable', 'exists:students,id'], 'staff_id' => ['nullable', 'exists:staff,id'], 'recipient_name' => ['nullable', 'string']]],
        ],
        'school_events' => [
            'module' => 'activities',
            'label' => 'Phong trào/Hội thi',
            'model' => SchoolEvent::class,
            'permission' => 'activities.school_events',
            'columns' => ['title', 'event_type', 'starts_at', 'status'],
            'fields' => [['name' => 'title', 'label' => 'Tên hoạt động', 'type' => 'text', 'required' => true], ['name' => 'event_type', 'label' => 'Loại', 'type' => 'select', 'options' => ['stem' => 'Ngày hội STEM', 'sports' => 'Hội thao', 'contest' => 'Hội thi', 'wall_newspaper' => 'Báo tường', 'union' => 'Hoạt động Đoàn', 'career' => 'Hướng nghiệp', 'movement' => 'Phong trào'], 'required' => true], ['name' => 'starts_at', 'label' => 'Bắt đầu', 'type' => 'datetime-local', 'required' => true], ['name' => 'ends_at', 'label' => 'Kết thúc', 'type' => 'datetime-local'], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['draft' => 'Nháp', 'open' => 'Đang mở', 'closed' => 'Đã kết thúc'], 'required' => true], ['name' => 'description', 'label' => 'Mô tả', 'type' => 'textarea']],
            'validation' => ['store' => ['title' => ['required', 'string'], 'event_type' => ['required', 'string'], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'description' => ['nullable', 'string']], 'update' => ['title' => ['required', 'string'], 'event_type' => ['required', 'string'], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'description' => ['nullable', 'string']]],
        ],
        'event_registrations' => [
            'module' => 'activities',
            'label' => 'Đăng ký hoạt động',
            'model' => EventRegistration::class,
            'permission' => 'activities.event_registrations',
            'columns' => ['school_event_id', 'student_id', 'team_name', 'status'],
            'fields' => [['name' => 'school_event_id', 'label' => 'Hoạt động', 'type' => 'select', 'lookup' => ['model' => SchoolEvent::class, 'value' => 'id', 'label' => ['title']], 'required' => true], ['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup], ['name' => 'team_name', 'label' => 'Đội/Nhóm', 'type' => 'text'], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['registered' => 'Đã đăng ký', 'approved' => 'Đã duyệt', 'cancelled' => 'Đã hủy'], 'required' => true]],
            'validation' => ['store' => ['school_event_id' => ['required', 'exists:school_events,id'], 'student_id' => ['nullable', 'exists:students,id'], 'team_name' => ['nullable', 'string'], 'status' => ['required', 'string']], 'update' => ['school_event_id' => ['required', 'exists:school_events,id'], 'student_id' => ['nullable', 'exists:students,id'], 'team_name' => ['nullable', 'string'], 'status' => ['required', 'string']]],
        ],
        'event_results' => [
            'module' => 'activities',
            'label' => 'Kết quả hội thi/hội thao',
            'model' => EventResult::class,
            'permission' => 'activities.event_results',
            'columns' => ['school_event_id', 'rank', 'award_title', 'score'],
            'fields' => [['name' => 'school_event_id', 'label' => 'Hoạt động', 'type' => 'select', 'lookup' => ['model' => SchoolEvent::class, 'value' => 'id', 'label' => ['title']], 'required' => true], ['name' => 'registration_id', 'label' => 'Đăng ký', 'type' => 'select', 'lookup' => ['model' => EventRegistration::class, 'value' => 'id', 'label' => ['id', 'team_name']]], ['name' => 'rank', 'label' => 'Thứ hạng', 'type' => 'number'], ['name' => 'award_title', 'label' => 'Giải thưởng', 'type' => 'text'], ['name' => 'score', 'label' => 'Điểm', 'type' => 'number', 'step' => '0.1']],
            'validation' => ['store' => ['school_event_id' => ['required', 'exists:school_events,id'], 'registration_id' => ['nullable', 'exists:event_registrations,id'], 'rank' => ['nullable', 'integer'], 'award_title' => ['nullable', 'string'], 'score' => ['nullable', 'numeric']], 'update' => ['school_event_id' => ['required', 'exists:school_events,id'], 'registration_id' => ['nullable', 'exists:event_registrations,id'], 'rank' => ['nullable', 'integer'], 'award_title' => ['nullable', 'string'], 'score' => ['nullable', 'numeric']]],
        ],
        'fee_categories' => [
            'module' => 'finance',
            'label' => 'Khoản thu',
            'model' => FeeCategory::class,
            'permission' => 'finance.fee_categories',
            'columns' => ['name', 'code', 'default_amount', 'is_required'],
            'fields' => [['name' => 'name', 'label' => 'Tên khoản thu', 'type' => 'text', 'required' => true], ['name' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true], ['name' => 'default_amount', 'label' => 'Số tiền mặc định', 'type' => 'number', 'required' => true], ['name' => 'is_required', 'label' => 'Bắt buộc', 'type' => 'checkbox']],
            'validation' => ['store' => ['name' => ['required', 'string'], 'code' => ['required', 'string'], 'default_amount' => ['required', 'numeric', 'min:0'], 'is_required' => ['boolean']], 'update' => ['name' => ['required', 'string'], 'code' => ['required', 'string'], 'default_amount' => ['required', 'numeric', 'min:0'], 'is_required' => ['boolean']]],
        ],
        'fee_plans' => [
            'module' => 'finance',
            'label' => 'Kế hoạch thu',
            'model' => FeePlan::class,
            'permission' => 'finance.fee_plans',
            'columns' => ['fee_category_id', 'school_year_id', 'amount', 'applies_to'],
            'fields' => [['name' => 'fee_category_id', 'label' => 'Khoản thu', 'type' => 'select', 'lookup' => ['model' => FeeCategory::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'school_year_id', 'label' => 'Năm học', 'type' => 'select', 'lookup' => $yearLookup, 'required' => true], ['name' => 'amount', 'label' => 'Số tiền', 'type' => 'number', 'required' => true], ['name' => 'applies_to', 'label' => 'Áp dụng cho', 'type' => 'text']],
            'validation' => ['store' => ['fee_category_id' => ['required', 'exists:fee_categories,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'amount' => ['required', 'numeric', 'min:0'], 'applies_to' => ['nullable', 'string']], 'update' => ['fee_category_id' => ['required', 'exists:fee_categories,id'], 'school_year_id' => ['required', 'exists:school_years,id'], 'amount' => ['required', 'numeric', 'min:0'], 'applies_to' => ['nullable', 'string']]],
        ],
        'fee_invoices' => [
            'module' => 'finance',
            'label' => 'Phiếu thu học phí',
            'model' => FeeInvoice::class,
            'permission' => 'finance.fee_invoices',
            'columns' => ['invoice_no', 'student_id', 'total_amount', 'paid_amount', 'status'],
            'fields' => [['name' => 'invoice_no', 'label' => 'Số phiếu', 'type' => 'text', 'required' => true], ['name' => 'student_id', 'label' => 'Học sinh', 'type' => 'select', 'lookup' => $studentLookup, 'required' => true], ['name' => 'due_date', 'label' => 'Hạn thu', 'type' => 'date'], ['name' => 'total_amount', 'label' => 'Tổng tiền', 'type' => 'number', 'required' => true], ['name' => 'paid_amount', 'label' => 'Đã thu', 'type' => 'number', 'required' => true], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['unpaid' => 'Chưa thu', 'partial' => 'Thu một phần', 'paid' => 'Đã thu'], 'required' => true]],
            'validation' => ['store' => ['invoice_no' => ['required', 'string'], 'student_id' => ['required', 'exists:students,id'], 'due_date' => ['nullable', 'date'], 'total_amount' => ['required', 'numeric', 'min:0'], 'paid_amount' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'string']], 'update' => ['invoice_no' => ['required', 'string'], 'student_id' => ['required', 'exists:students,id'], 'due_date' => ['nullable', 'date'], 'total_amount' => ['required', 'numeric', 'min:0'], 'paid_amount' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'string']]],
            'audit' => true,
        ],
        'fee_invoice_items' => [
            'module' => 'finance',
            'label' => 'Chi tiết phiếu thu',
            'model' => FeeInvoiceItem::class,
            'permission' => 'finance.fee_invoice_items',
            'columns' => ['fee_invoice_id', 'fee_category_id', 'amount'],
            'fields' => [['name' => 'fee_invoice_id', 'label' => 'Phiếu thu', 'type' => 'select', 'lookup' => ['model' => FeeInvoice::class, 'value' => 'id', 'label' => ['invoice_no']], 'required' => true], ['name' => 'fee_category_id', 'label' => 'Khoản thu', 'type' => 'select', 'lookup' => ['model' => FeeCategory::class, 'value' => 'id', 'label' => ['name']], 'required' => true], ['name' => 'amount', 'label' => 'Số tiền', 'type' => 'number', 'required' => true], ['name' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea']],
            'validation' => ['store' => ['fee_invoice_id' => ['required', 'exists:fee_invoices,id'], 'fee_category_id' => ['required', 'exists:fee_categories,id'], 'amount' => ['required', 'numeric', 'min:0'], 'note' => ['nullable', 'string']], 'update' => ['fee_invoice_id' => ['required', 'exists:fee_invoices,id'], 'fee_category_id' => ['required', 'exists:fee_categories,id'], 'amount' => ['required', 'numeric', 'min:0'], 'note' => ['nullable', 'string']]],
            'audit' => true,
        ],
        'payments' => [
            'module' => 'finance',
            'label' => 'Giao dịch thu phí',
            'model' => Payment::class,
            'permission' => 'finance.payments',
            'columns' => ['receipt_no', 'fee_invoice_id', 'amount', 'method', 'paid_at'],
            'fields' => [['name' => 'receipt_no', 'label' => 'Số biên nhận', 'type' => 'text', 'required' => true], ['name' => 'fee_invoice_id', 'label' => 'Phiếu thu', 'type' => 'select', 'lookup' => ['model' => FeeInvoice::class, 'value' => 'id', 'label' => ['invoice_no']], 'required' => true], ['name' => 'amount', 'label' => 'Số tiền', 'type' => 'number', 'required' => true], ['name' => 'method', 'label' => 'Phương thức', 'type' => 'select', 'options' => ['cash' => 'Tiền mặt', 'transfer' => 'Chuyển khoản'], 'required' => true], ['name' => 'paid_at', 'label' => 'Thời điểm thu', 'type' => 'datetime-local', 'required' => true], ['name' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea']],
            'validation' => ['store' => ['receipt_no' => ['required', 'string'], 'fee_invoice_id' => ['required', 'exists:fee_invoices,id'], 'amount' => ['required', 'numeric', 'min:0'], 'method' => ['required', 'string'], 'paid_at' => ['required', 'date'], 'note' => ['nullable', 'string']], 'update' => ['receipt_no' => ['required', 'string'], 'fee_invoice_id' => ['required', 'exists:fee_invoices,id'], 'amount' => ['required', 'numeric', 'min:0'], 'method' => ['required', 'string'], 'paid_at' => ['required', 'date'], 'note' => ['nullable', 'string']]],
            'audit' => true,
        ],
        'announcements' => [
            'module' => 'communication',
            'label' => 'Thông báo',
            'model' => Announcement::class,
            'permission' => 'communication.announcements',
            'columns' => ['title', 'audience', 'published_at', 'status'],
            'fields' => [['name' => 'title', 'label' => 'Tiêu đề', 'type' => 'text', 'required' => true], ['name' => 'audience', 'label' => 'Đối tượng', 'type' => 'select', 'options' => ['all' => 'Toàn trường', 'staff' => 'Cán bộ/Giáo viên', 'students' => 'Học sinh', 'guardians' => 'Phụ huynh'], 'required' => true], ['name' => 'published_at', 'label' => 'Ngày đăng', 'type' => 'datetime-local'], ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['draft' => 'Nháp', 'published' => 'Đã đăng'], 'required' => true], ['name' => 'body', 'label' => 'Nội dung', 'type' => 'textarea', 'required' => true]],
            'validation' => ['store' => ['title' => ['required', 'string'], 'audience' => ['required', 'string'], 'published_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'body' => ['required', 'string']], 'update' => ['title' => ['required', 'string'], 'audience' => ['required', 'string'], 'published_at' => ['nullable', 'date'], 'status' => ['required', 'string'], 'body' => ['required', 'string']]],
        ],
    ],
];
