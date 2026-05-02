<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Commendation;
use App\Models\ConductScore;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\EventRegistration;
use App\Models\EventResult;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePlan;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolYear;
use App\Models\ScoreCategory;
use App\Models\ScoreEntry;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = $this->seedPermissions();
            $roles = $this->seedRoles($permissions);
            $users = $this->seedUsers($roles);
            [$year, $semesterOne, $semesterTwo] = $this->seedAcademicCalendar();
            [$classes, $subjects, $staff] = $this->seedAcademicCore($users, $year, $semesterOne, $semesterTwo);
            [$students, $guardians] = $this->seedStudentsAndGuardians($users, $classes, $year);
            $this->seedAssessmentAndConduct($students, $subjects, $semesterOne, $users);
            $this->seedFinance($students, $year, $users);
            $this->seedActivities($students, $staff, $users);
            $this->seedAnnouncements($users);
            $this->seedAudit($users);
        });
    }

    private function seedPermissions(): array
    {
        $keys = collect(['dashboard.view', 'portal.view', 'reports.view', 'audit.view']);

        foreach (config('school.resources') as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $keys->push($resource['permission'].'.'.$action);
            }
        }

        return $keys
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $key): array => [
                $key => Permission::updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => Str::headline(str_replace('.', ' ', $key)),
                        'module' => Str::before($key, '.'),
                    ]
                ),
            ])
            ->all();
    }

    private function seedRoles(array $permissions): array
    {
        $roles = [];

        foreach (config('school.roles') as $slug => $name) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name]);
            $patterns = config('school.role_permissions.'.$slug, []);
            $permissionIds = $this->expandPermissionPatterns($patterns, array_keys($permissions), $permissions);
            $role->permissions()->sync($permissionIds);
            $roles[$slug] = $role;
        }

        return $roles;
    }

    private function expandPermissionPatterns(array $patterns, array $keys, array $permissions): array
    {
        return collect($patterns)
            ->flatMap(function (string $pattern) use ($keys, $permissions) {
                if ($pattern === '*') {
                    return collect($permissions)->pluck('id');
                }

                if (Str::endsWith($pattern, '.*')) {
                    $prefix = Str::beforeLast($pattern, '.*').'.';

                    return collect($keys)
                        ->filter(fn (string $key): bool => Str::startsWith($key, $prefix))
                        ->map(fn (string $key): int => $permissions[$key]->id);
                }

                return isset($permissions[$pattern]) ? [$permissions[$pattern]->id] : [];
            })
            ->unique()
            ->values()
            ->all();
    }

    private function seedUsers(array $roles): array
    {
        $accounts = [
            'admin' => ['Admin Võ Văn Kiệt', 'admin@vvk.local', 'admin'],
            'bgh' => ['BGH Demo', 'bgh@vvk.local', 'bgh'],
            'giao_vu' => ['Giáo vụ Demo', 'giaovu@vvk.local', 'giao_vu'],
            'gvcn' => ['GVCN Demo', 'gvcn@vvk.local', 'gvcn'],
            'giao_vien_bo_mon' => ['Giáo viên bộ môn Demo', 'giaovien@vvk.local', 'giao_vien_bo_mon'],
            'doan_truong' => ['Đoàn trường Demo', 'doantruong@vvk.local', 'doan_truong'],
            'giam_thi' => ['Giám thị Demo', 'giamthi@vvk.local', 'giam_thi'],
            'ke_toan' => ['Kế toán Demo', 'ketoan@vvk.local', 'ke_toan'],
            'phu_huynh' => ['Phụ huynh Demo', 'phuhuynh@vvk.local', 'phu_huynh'],
            'hoc_sinh' => ['Học sinh Demo', 'hocsinh@vvk.local', 'hoc_sinh'],
        ];

        $users = [];

        foreach ($accounts as $key => [$name, $email, $roleSlug]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'status' => 'active']
            );
            $user->roles()->sync([$roles[$roleSlug]->id]);
            $users[$key] = $user;
        }

        return $users;
    }

    private function seedAcademicCalendar(): array
    {
        $year = SchoolYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-08-15',
            'end_date' => '2026-05-31',
            'is_active' => true,
        ]);

        $semesterOne = Semester::create([
            'school_year_id' => $year->id,
            'name' => 'Học kỳ I',
            'start_date' => '2025-08-15',
            'end_date' => '2025-12-31',
            'is_active' => false,
        ]);

        $semesterTwo = Semester::create([
            'school_year_id' => $year->id,
            'name' => 'Học kỳ II',
            'start_date' => '2026-01-05',
            'end_date' => '2026-05-31',
            'is_active' => true,
        ]);

        foreach ([10, 11, 12] as $level) {
            Grade::create(['level' => $level, 'name' => 'Khối '.$level]);
        }

        return [$year, $semesterOne, $semesterTwo];
    }

    private function seedAcademicCore(array $users, SchoolYear $year, Semester $semesterOne, Semester $semesterTwo): array
    {
        $subjects = collect([
            ['TOAN', 'Toán', 'Tự nhiên'],
            ['VAN', 'Ngữ văn', 'Xã hội'],
            ['ANH', 'Tiếng Anh', 'Ngoại ngữ'],
            ['LY', 'Vật lý', 'Tự nhiên'],
            ['HOA', 'Hóa học', 'Tự nhiên'],
            ['TIN', 'Tin học', 'STEM'],
        ])->map(fn (array $subject): Subject => Subject::create([
            'code' => $subject[0],
            'name' => $subject[1],
            'department' => $subject[2],
        ]));

        $staffRows = [
            ['bgh', 'CB001', 'Nguyễn Văn Minh Demo', 'Phó hiệu trưởng', 'Ban giám hiệu'],
            ['giao_vu', 'CB002', 'Trần Thị Hoa Demo', 'Giáo vụ', 'Văn phòng'],
            ['gvcn', 'GV001', 'Lê Minh Châu Demo', 'Giáo viên chủ nhiệm', 'Tổ Toán - Tin'],
            ['giao_vien_bo_mon', 'GV002', 'Phạm Quốc Huy Demo', 'Giáo viên bộ môn', 'Tổ Tự nhiên'],
            ['doan_truong', 'CB003', 'Võ Thị Lan Demo', 'Bí thư Đoàn', 'Đoàn trường'],
            ['giam_thi', 'CB004', 'Đặng Nhật Nam Demo', 'Giám thị', 'Nề nếp'],
            ['ke_toan', 'CB005', 'Huỳnh Thanh Mai Demo', 'Kế toán', 'Tài vụ'],
        ];

        $staff = collect($staffRows)->mapWithKeys(function (array $row) use ($users): array {
            [$userKey, $code, $name, $position, $department] = $row;
            $record = Staff::create([
                'user_id' => $users[$userKey]->id,
                'staff_code' => $code,
                'full_name' => $name,
                'position' => $position,
                'department' => $department,
                'hire_date' => '2021-08-01',
                'email' => $users[$userKey]->email,
                'status' => 'active',
            ]);

            TeacherProfile::create([
                'staff_id' => $record->id,
                'specialization' => $department,
                'qualification' => 'Đại học sư phạm',
                'years_experience' => 8,
            ]);

            return [$userKey => $record];
        });

        $classes = collect([
            ['10A1', 10, $staff['gvcn']->id, 'P.101'],
            ['10A2', 10, null, 'P.102'],
            ['11A1', 11, null, 'P.201'],
            ['12A1', 12, null, 'P.301'],
        ])->map(function (array $row) use ($year): SchoolClass {
            [$name, $level, $homeroomTeacherId, $room] = $row;

            return SchoolClass::create([
                'name' => $name,
                'grade_id' => Grade::where('level', $level)->value('id'),
                'school_year_id' => $year->id,
                'homeroom_teacher_id' => $homeroomTeacherId,
                'room' => $room,
            ]);
        });

        TeachingAssignment::create([
            'teacher_id' => $staff['giao_vien_bo_mon']->id,
            'class_id' => $classes[0]->id,
            'subject_id' => $subjects->firstWhere('code', 'LY')->id,
            'semester_id' => $semesterTwo->id,
        ]);

        TeachingAssignment::create([
            'teacher_id' => $staff['gvcn']->id,
            'class_id' => $classes[0]->id,
            'subject_id' => $subjects->firstWhere('code', 'TOAN')->id,
            'semester_id' => $semesterTwo->id,
        ]);

        return [$classes, $subjects, $staff];
    }

    private function seedStudentsAndGuardians(array $users, $classes, SchoolYear $year): array
    {
        $names = [
            'An Nhiên Demo',
            'Bảo Nam Demo',
            'Cát Tường Demo',
            'Duy Khang Demo',
            'Gia Hân Demo',
            'Hoàng Phúc Demo',
            'Khánh Linh Demo',
            'Minh Quân Demo',
            'Ngọc Anh Demo',
            'Phương Uyên Demo',
            'Quang Hưng Demo',
            'Thanh Trúc Demo',
        ];

        $students = collect($names)->map(function (string $name, int $index) use ($users, $classes, $year): Student {
            $student = Student::create([
                'user_id' => $index === 0 ? $users['hoc_sinh']->id : null,
                'student_code' => 'DEMO'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'full_name' => $name,
                'gender' => $index % 2 === 0 ? 'female' : 'male',
                'birth_date' => Carbon::create(2008, 1, 1)->addDays($index * 37)->toDateString(),
                'address' => 'Địa chỉ demo tại Cà Mau',
                'status' => 'active',
            ]);

            ClassEnrollment::create([
                'student_id' => $student->id,
                'class_id' => $classes[$index % $classes->count()]->id,
                'school_year_id' => $year->id,
                'status' => 'active',
            ]);

            return $student;
        });

        $guardians = $students->take(4)->map(function (Student $student, int $index) use ($users): Guardian {
            $guardian = Guardian::create([
                'user_id' => $index === 0 ? $users['phu_huynh']->id : null,
                'full_name' => 'Phụ huynh '.$student->full_name,
                'phone' => '09000000'.($index + 10),
                'email' => 'phuhuynh'.$index.'@example.test',
                'relationship' => $index % 2 === 0 ? 'Cha/Mẹ' : 'Người giám hộ',
                'address' => 'Địa chỉ phụ huynh demo',
            ]);

            $guardian->students()->attach($student->id, ['relationship' => $guardian->relationship]);

            return $guardian;
        });

        return [$students, $guardians];
    }

    private function seedAssessmentAndConduct($students, $subjects, Semester $semester, array $users): void
    {
        $categories = collect([
            ['Miệng', 'MIENG', 1],
            ['15 phút', '15P', 1],
            ['Giữa kỳ', 'GK', 2],
            ['Cuối kỳ', 'CK', 3],
        ])->map(fn (array $row): ScoreCategory => ScoreCategory::create([
            'name' => $row[0],
            'code' => $row[1],
            'weight' => $row[2],
        ]));

        foreach ($students->take(8) as $index => $student) {
            ScoreEntry::create([
                'student_id' => $student->id,
                'subject_id' => $subjects[$index % $subjects->count()]->id,
                'semester_id' => $semester->id,
                'score_category_id' => $categories[$index % $categories->count()]->id,
                'entered_by' => $users['giao_vien_bo_mon']->id,
                'score' => 6.5 + ($index % 4),
                'status' => 'submitted',
                'note' => 'Điểm demo không phải dữ liệu thật.',
            ]);

            ConductScore::create([
                'student_id' => $student->id,
                'semester_id' => $semester->id,
                'score' => 75 + $index,
                'rating' => $index % 3 === 0 ? 'tot' : 'kha',
                'status' => 'approved',
                'note' => 'Điểm rèn luyện demo.',
            ]);
        }

        $case = DisciplinaryCase::create([
            'student_id' => $students[1]->id,
            'incident_date' => now()->subDays(12)->toDateString(),
            'severity' => 'low',
            'status' => 'closed',
            'description' => 'Vi phạm nội quy demo trong mô hình trường học không điện thoại.',
            'created_by' => $users['giam_thi']->id,
        ]);

        DisciplinaryAction::create([
            'disciplinary_case_id' => $case->id,
            'action_type' => 'Nhắc nhở và cam kết',
            'action_date' => now()->subDays(10)->toDateString(),
            'note' => 'Biện pháp demo.',
        ]);

        Commendation::create([
            'title' => 'Khen thưởng đội STEM demo',
            'category' => 'STEM',
            'issued_date' => now()->subDays(20)->toDateString(),
            'description' => 'Dữ liệu khen thưởng minh họa cho ngày hội STEM.',
        ]);
    }

    private function seedFinance($students, SchoolYear $year, array $users): void
    {
        $tuition = FeeCategory::create(['name' => 'Học phí demo', 'code' => 'HP-DEMO', 'default_amount' => 350000, 'is_required' => true]);
        $stem = FeeCategory::create(['name' => 'Hoạt động STEM demo', 'code' => 'STEM-DEMO', 'default_amount' => 120000, 'is_required' => false]);

        FeePlan::create(['fee_category_id' => $tuition->id, 'school_year_id' => $year->id, 'amount' => 350000, 'applies_to' => 'Toàn trường']);
        FeePlan::create(['fee_category_id' => $stem->id, 'school_year_id' => $year->id, 'amount' => 120000, 'applies_to' => 'Tự nguyện']);

        foreach ($students->take(5) as $index => $student) {
            $invoice = FeeInvoice::create([
                'invoice_no' => 'INV-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'student_id' => $student->id,
                'due_date' => now()->addDays(20)->toDateString(),
                'total_amount' => 470000,
                'paid_amount' => $index < 2 ? 470000 : 0,
                'status' => $index < 2 ? 'paid' : 'unpaid',
            ]);

            FeeInvoiceItem::create(['fee_invoice_id' => $invoice->id, 'fee_category_id' => $tuition->id, 'amount' => 350000, 'note' => 'Khoản thu demo']);
            FeeInvoiceItem::create(['fee_invoice_id' => $invoice->id, 'fee_category_id' => $stem->id, 'amount' => 120000, 'note' => 'Khoản thu demo']);

            if ($index < 2) {
                Payment::create([
                    'receipt_no' => 'RCT-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'fee_invoice_id' => $invoice->id,
                    'amount' => 470000,
                    'method' => 'cash',
                    'paid_at' => now()->subDays(3),
                    'collected_by' => $users['ke_toan']->id,
                    'note' => 'Giao dịch demo.',
                ]);
            }
        }
    }

    private function seedActivities($students, $staff, array $users): void
    {
        $stem = SchoolEvent::create([
            'title' => 'Ngày hội STEM demo',
            'event_type' => 'stem',
            'description' => 'Hoạt động demo về phòng học STEM và dự án khoa học.',
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(4),
            'status' => 'open',
            'created_by' => $users['doan_truong']->id,
        ]);

        $sports = SchoolEvent::create([
            'title' => 'Hội thao cấp trường demo',
            'event_type' => 'sports',
            'description' => 'Hoạt động thể thao demo.',
            'starts_at' => now()->addMonth(),
            'status' => 'draft',
            'created_by' => $users['doan_truong']->id,
        ]);

        $registration = EventRegistration::create([
            'school_event_id' => $stem->id,
            'student_id' => $students[0]->id,
            'team_name' => 'Nhóm STEM Demo 01',
            'status' => 'approved',
        ]);

        EventResult::create([
            'school_event_id' => $stem->id,
            'registration_id' => $registration->id,
            'rank' => 1,
            'award_title' => 'Giải ý tưởng sáng tạo demo',
            'score' => 9.2,
        ]);
    }

    private function seedAnnouncements(array $users): void
    {
        Announcement::create([
            'title' => 'Thông báo mô hình trường học không điện thoại',
            'body' => 'Thông báo demo phục vụ kiểm thử cổng phụ huynh/học sinh.',
            'audience' => 'all',
            'published_at' => now()->subDay(),
            'status' => 'published',
            'created_by' => $users['giao_vu']->id,
        ]);

        Announcement::create([
            'title' => 'Lịch tư vấn hướng nghiệp demo',
            'body' => 'Nội dung demo về hoạt động tư vấn hướng nghiệp.',
            'audience' => 'students',
            'published_at' => now(),
            'status' => 'published',
            'created_by' => $users['doan_truong']->id,
        ]);
    }

    private function seedAudit(array $users): void
    {
        AuditLog::create([
            'actor_id' => $users['admin']->id,
            'action' => 'system.seeded',
            'subject_type' => null,
            'subject_id' => null,
            'before_values' => null,
            'after_values' => ['note' => 'Seed dữ liệu demo giả, không dùng dữ liệu học sinh thật.'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DatabaseSeeder',
            'metadata' => ['school' => config('school.school.name')],
        ]);
    }
}

