<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private Carbon $now;

    public function run(): void
    {
        $this->now = now();

        DB::transaction(function (): void {
            $permissions = $this->seedPermissions();
            $roles = $this->seedRoles($permissions);
            $users = $this->seedUsers($roles);
            [$yearId, $semesterOneId, $semesterTwoId] = $this->seedCalendar();
            [$gradeIds, $classIds] = $this->seedGradesAndClasses($yearId);
            $subjectIds = $this->seedSubjects();
            $teacherIds = $this->seedTeachers($users);
            $this->assignHomeroomTeachers($classIds, $teacherIds);
            $studentIds = $this->seedStudents($users, $classIds, $yearId, $semesterOneId, $semesterTwoId);
            $this->seedTeachingAssignments($yearId, $semesterOneId, $semesterTwoId, $classIds, $subjectIds, $teacherIds);
            $scoreTypeIds = $this->seedScoreSetup($yearId, $semesterOneId, $semesterTwoId, $subjectIds);
            $this->seedScoresAndResults($yearId, $semesterOneId, $semesterTwoId, $classIds, $subjectIds, $scoreTypeIds, $studentIds, $users);
            $this->seedConduct($yearId, $semesterOneId, $semesterTwoId, $classIds, $studentIds, $users);
            $this->seedAttendance($yearId, $semesterTwoId, $classIds, $subjectIds, $teacherIds, $studentIds, $users);
            $this->seedCampaigns($yearId, $semesterTwoId, $classIds, $studentIds, $users);
            $this->seedSportsEvent($yearId, $semesterTwoId, $classIds, $studentIds, $teacherIds, $users);
            $this->seedRewardsAndDiscipline($yearId, $semesterTwoId, $classIds, $studentIds, $teacherIds, $users);
            $this->seedFees($yearId, $semesterTwoId, $classIds, $studentIds, $users);
            $this->seedAnnouncements($yearId, $semesterTwoId, $classIds, $studentIds, $users);
            $this->seedLogs($users);
        });
    }

    private function seedPermissions(): array
    {
        $keys = collect([
            'dashboard.view',
            'portal.view',
            'reports.view',
            'audit.view',
        ]);

        foreach (config('school.resources', []) as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $keys->push($resource['permission'].'.'.$action);
            }
        }

        foreach ([
            'identity.users',
            'identity.roles',
            'identity.permissions',
            'academic.school_years',
            'academic.semesters',
            'academic.grades',
            'academic.classes',
            'academic.subjects',
            'academic.students',
            'academic.teachers',
            'academic.guardians',
            'academic.student_documents',
            'academic.student_class_enrollments',
            'academic.teaching_assignments',
            'assessment.score_types',
            'assessment.score_columns',
            'assessment.student_scores',
            'assessment.academic_results',
            'assessment.teacher_comments',
            'conduct.conduct_rules',
            'conduct.conduct_records',
            'conduct.conduct_scores',
            'conduct.conduct_score_summaries',
            'conduct.conduct_rating_rules',
            'conduct.conduct_adjustments',
            'conduct.discipline_types',
            'conduct.discipline_cases',
            'conduct.discipline_actions',
            'conduct.reward_types',
            'conduct.rewards',
            'attendance.attendance_sessions',
            'attendance.attendance_records',
            'activities.campaigns',
            'activities.campaign_criteria',
            'activities.campaign_participants',
            'activities.campaign_results',
            'activities.campaign_class_scores',
            'activities.events',
            'activities.event_categories',
            'activities.event_registrations',
            'activities.event_teams',
            'activities.event_schedules',
            'activities.event_results',
            'activities.event_awards',
            'finance.fee_types',
            'finance.fee_plans',
            'finance.student_fees',
            'finance.payments',
            'finance.receipts',
            'finance.fee_exemptions',
            'communication.announcements',
            'communication.announcement_recipients',
            'communication.notification_reads',
        ] as $resourceKey) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $keys->push($resourceKey.'.'.$action);
            }
        }

        return $keys
            ->unique()
            ->values()
            ->mapWithKeys(function (string $key): array {
                $id = DB::table('permissions')->insertGetId([
                    'key' => $key,
                    'name' => Str::headline(str_replace('.', ' ', $key)),
                    'module' => Str::before($key, '.'),
                    'description' => 'Demo permission for '.$key,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                return [$key => $id];
            })
            ->all();
    }

    private function seedRoles(array $permissions): array
    {
        $roleNames = [
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
        ];

        $roles = [];

        foreach ($roleNames as $slug => $name) {
            $roleId = DB::table('roles')->insertGetId([
                'slug' => $slug,
                'name' => $name,
                'description' => 'Demo role '.$name,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $patterns = config('school.role_permissions.'.$slug, []);
            $permissionIds = $this->expandPermissionPatterns($patterns, $permissions);

            if ($slug === 'admin') {
                $permissionIds = array_values($permissions);
            }

            foreach (array_unique($permissionIds) as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            $roles[$slug] = $roleId;
        }

        return $roles;
    }

    private function expandPermissionPatterns(array $patterns, array $permissions): array
    {
        $ids = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return array_values($permissions);
            }

            if (Str::endsWith($pattern, '.*')) {
                $prefix = Str::beforeLast($pattern, '.*').'.';
                foreach ($permissions as $key => $id) {
                    if (Str::startsWith($key, $prefix)) {
                        $ids[] = $id;
                    }
                }
                continue;
            }

            if (isset($permissions[$pattern])) {
                $ids[] = $permissions[$pattern];
            }
        }

        return $ids;
    }

    private function seedUsers(array $roles): array
    {
        $accounts = [
            'admin' => ['Admin VVK Demo', 'admin', 'admin@vvk.local', 'admin'],
            'bgh' => ['BGH Demo', 'bgh', 'bgh@vvk.local', 'bgh'],
            'giao_vu' => ['Giao Vu Demo', 'giaovu', 'giaovu@vvk.local', 'giao_vu'],
            'gvcn' => ['GVCN Demo', 'gvcn', 'gvcn@vvk.local', 'gvcn'],
            'giao_vien_bo_mon' => ['Giao Vien Bo Mon Demo', 'giaovien', 'giaovien@vvk.local', 'giao_vien_bo_mon'],
            'doan_truong' => ['Doan Truong Demo', 'doantruong', 'doantruong@vvk.local', 'doan_truong'],
            'giam_thi' => ['Giam Thi Demo', 'giamthi', 'giamthi@vvk.local', 'giam_thi'],
            'ke_toan' => ['Ke Toan Demo', 'ketoan', 'ketoan@vvk.local', 'ke_toan'],
            'phu_huynh' => ['Phu Huynh Demo', 'phuhuynh', 'phuhuynh@vvk.local', 'phu_huynh'],
            'hoc_sinh' => ['Hoc Sinh Demo', 'hocsinh', 'hocsinh@vvk.local', 'hoc_sinh'],
        ];

        $users = [];

        foreach ($accounts as $key => [$name, $username, $email, $roleSlug]) {
            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roles[$roleSlug],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $users[$key] = $userId;
        }

        return $users;
    }

    private function seedCalendar(): array
    {
        $yearId = DB::table('school_years')->insertGetId([
            'name' => '2025-2026',
            'start_date' => '2025-08-15',
            'end_date' => '2026-05-31',
            'is_active' => true,
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $semesterOneId = DB::table('semesters')->insertGetId([
            'school_year_id' => $yearId,
            'name' => 'Hoc ky I',
            'term_number' => 1,
            'start_date' => '2025-08-15',
            'end_date' => '2025-12-31',
            'is_active' => false,
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $semesterTwoId = DB::table('semesters')->insertGetId([
            'school_year_id' => $yearId,
            'name' => 'Hoc ky II',
            'term_number' => 2,
            'start_date' => '2026-01-05',
            'end_date' => '2026-05-31',
            'is_active' => true,
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        return [$yearId, $semesterOneId, $semesterTwoId];
    }

    private function seedGradesAndClasses(int $yearId): array
    {
        $gradeIds = [];
        foreach ([10, 11, 12] as $level) {
            $gradeIds[$level] = DB::table('grades')->insertGetId([
                'level' => $level,
                'name' => 'Khoi '.$level,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $classes = [
            ['10A1', 10, 'P101'],
            ['10A2', 10, 'P102'],
            ['11A1', 11, 'P201'],
            ['11A2', 11, 'P202'],
            ['12A1', 12, 'P301'],
            ['12A2', 12, 'P302'],
        ];

        $classIds = [];
        foreach ($classes as [$name, $level, $room]) {
            $classIds[] = DB::table('classes')->insertGetId([
                'school_year_id' => $yearId,
                'grade_id' => $gradeIds[$level],
                'name' => $name,
                'code' => 'DEMO-'.$name,
                'room' => $room,
                'capacity' => 45,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        return [$gradeIds, $classIds];
    }

    private function seedSubjects(): array
    {
        $subjects = [
            ['TOAN', 'Toan', 'To Toan - Tin'],
            ['VAN', 'Ngu van', 'To Xa hoi'],
            ['ANH', 'Tieng Anh', 'To Ngoai ngu'],
            ['LY', 'Vat ly', 'To Tu nhien'],
            ['HOA', 'Hoa hoc', 'To Tu nhien'],
            ['SINH', 'Sinh hoc', 'To Tu nhien'],
            ['SU', 'Lich su', 'To Xa hoi'],
            ['DIA', 'Dia ly', 'To Xa hoi'],
            ['GDCD', 'Giao duc cong dan', 'To Xa hoi'],
            ['TIN', 'Tin hoc', 'To Toan - Tin'],
        ];

        $ids = [];
        foreach ($subjects as [$code, $name, $department]) {
            $ids[$code] = DB::table('subjects')->insertGetId([
                'code' => $code,
                'name' => $name,
                'department' => $department,
                'default_credit' => 1,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        return $ids;
    }

    private function seedTeachers(array $users): array
    {
        $teacherRows = [
            ['TDEMO001', 'Nguyen Minh Demo', 'Ban giam hieu', 'Hieu pho', $users['bgh'] ?? null],
            ['TDEMO002', 'Tran Hoa Demo', 'Van phong', 'Giao vu', $users['giao_vu'] ?? null],
            ['TDEMO003', 'Le Chau Demo', 'To Toan - Tin', 'GVCN', $users['gvcn'] ?? null],
            ['TDEMO004', 'Pham Huy Demo', 'To Tu nhien', 'Giao vien', $users['giao_vien_bo_mon'] ?? null],
            ['TDEMO005', 'Vo Lan Demo', 'Doan truong', 'Bi thu Doan', $users['doan_truong'] ?? null],
            ['TDEMO006', 'Dang Nam Demo', 'Ne nep', 'Giam thi', $users['giam_thi'] ?? null],
            ['TDEMO007', 'Huynh Mai Demo', 'Tai vu', 'Ke toan', $users['ke_toan'] ?? null],
            ['TDEMO008', 'Bui Khoa Demo', 'To Ngoai ngu', 'Giao vien', null],
            ['TDEMO009', 'Do My Demo', 'To Xa hoi', 'Giao vien', null],
            ['TDEMO010', 'Ngo Thanh Demo', 'To Tu nhien', 'Giao vien', null],
        ];

        $ids = [];
        foreach ($teacherRows as [$code, $name, $department, $position, $userId]) {
            $ids[] = DB::table('teachers')->insertGetId([
                'user_id' => $userId,
                'teacher_code' => $code,
                'staff_code' => $code,
                'full_name' => $name,
                'gender' => Str::contains($name, ['Hoa', 'Lan', 'Mai', 'My']) ? 'female' : 'male',
                'position' => $position,
                'department' => $department,
                'specialization' => $department,
                'qualification' => 'Dai hoc su pham',
                'hire_date' => '2021-08-01',
                'email' => strtolower($code).'@example.test',
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        return $ids;
    }

    private function assignHomeroomTeachers(array $classIds, array $teacherIds): void
    {
        foreach ($classIds as $index => $classId) {
            DB::table('classes')->where('id', $classId)->update([
                'homeroom_teacher_id' => $teacherIds[($index + 2) % count($teacherIds)],
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedStudents(array $users, array $classIds, int $yearId, int $semesterOneId, int $semesterTwoId): array
    {
        $studentIds = [];

        for ($i = 1; $i <= 30; $i++) {
            $studentId = DB::table('students')->insertGetId([
                'user_id' => $i === 1 ? $users['hoc_sinh'] : null,
                'student_code' => 'DEMO'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'full_name' => 'Hoc Sinh Demo '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'birth_date' => Carbon::create(2008, 1, 1)->addDays($i * 21)->toDateString(),
                'address' => 'Dia chi demo tai Ca Mau',
                'email' => 'student'.$i.'@example.test',
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $studentIds[] = $studentId;
            $classId = $classIds[($i - 1) % count($classIds)];

            foreach ([$semesterOneId, $semesterTwoId] as $semesterId) {
                DB::table('student_class_enrollments')->insert([
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'school_year_id' => $yearId,
                    'semester_id' => $semesterId,
                    'enrolled_at' => '2025-08-15',
                    'status' => 'active',
                    'note' => 'Demo enrollment only',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            if ($i <= 10) {
                $guardianUserId = $i === 1 ? $users['phu_huynh'] : null;
                $guardianId = DB::table('guardians')->insertGetId([
                    'user_id' => $guardianUserId,
                    'full_name' => 'Phu Huynh Demo '.$i,
                    'phone' => '090000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'email' => 'guardian'.$i.'@example.test',
                    'relationship' => 'Cha/Me',
                    'address' => 'Dia chi phu huynh demo',
                    'status' => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                DB::table('student_guardians')->insert([
                    'student_id' => $studentId,
                    'guardian_id' => $guardianId,
                    'relationship' => 'Cha/Me',
                    'is_primary' => true,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }

        return $studentIds;
    }

    private function seedTeachingAssignments(int $yearId, int $semesterOneId, int $semesterTwoId, array $classIds, array $subjectIds, array $teacherIds): void
    {
        foreach ([$semesterOneId, $semesterTwoId] as $semesterId) {
            foreach ($classIds as $classIndex => $classId) {
                $subjectIndex = 0;
                foreach ($subjectIds as $subjectId) {
                    DB::table('teaching_assignments')->insert([
                        'school_year_id' => $yearId,
                        'semester_id' => $semesterId,
                        'class_id' => $classId,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherIds[($classIndex + $subjectIndex) % count($teacherIds)],
                        'status' => 'active',
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ]);
                    $subjectIndex++;
                }
            }
        }
    }

    private function seedScoreSetup(int $yearId, int $semesterOneId, int $semesterTwoId, array $subjectIds): array
    {
        $types = [
            ['MIENG', 'Diem mieng', 1],
            ['TX', 'Diem thuong xuyen', 1],
            ['GK', 'Diem giua ky', 2],
            ['CK', 'Diem cuoi ky', 3],
        ];

        $scoreTypeIds = [];
        foreach ($types as [$code, $name, $weight]) {
            $scoreTypeIds[$code] = DB::table('score_types')->insertGetId([
                'code' => $code,
                'name' => $name,
                'weight' => $weight,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        foreach ([$semesterOneId, $semesterTwoId] as $semesterId) {
            foreach ($subjectIds as $subjectId) {
                foreach ($scoreTypeIds as $code => $typeId) {
                    DB::table('score_columns')->insert([
                        'school_year_id' => $yearId,
                        'semester_id' => $semesterId,
                        'subject_id' => $subjectId,
                        'score_type_id' => $typeId,
                        'name' => 'Cot '.$code,
                        'order_index' => count($scoreTypeIds),
                        'max_score' => 10,
                        'status' => 'active',
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ]);
                }
            }
        }

        return $scoreTypeIds;
    }

    private function seedScoresAndResults(int $yearId, int $semesterOneId, int $semesterTwoId, array $classIds, array $subjectIds, array $scoreTypeIds, array $studentIds, array $users): void
    {
        foreach (array_slice($studentIds, 0, 12) as $index => $studentId) {
            $classId = $classIds[$index % count($classIds)];
            $subjectId = array_values($subjectIds)[$index % count($subjectIds)];
            $scoreTypeId = array_values($scoreTypeIds)[$index % count($scoreTypeIds)];

            $scoreId = DB::table('student_scores')->insertGetId([
                'school_year_id' => $yearId,
                'semester_id' => $semesterTwoId,
                'class_id' => $classId,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'score_type_id' => $scoreTypeId,
                'score' => 6 + ($index % 5),
                'status' => 'submitted',
                'note' => 'Demo score only',
                'entered_by' => $users['giao_vien_bo_mon'],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('score_change_logs')->insert([
                'student_score_id' => $scoreId,
                'before_values' => null,
                'after_values' => json_encode(['score' => 6 + ($index % 5)]),
                'changed_by' => $users['giao_vien_bo_mon'],
                'reason' => 'Initial demo score',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        foreach ($studentIds as $index => $studentId) {
            DB::table('academic_results')->insert([
                'school_year_id' => $yearId,
                'semester_id' => $semesterTwoId,
                'class_id' => $classIds[$index % count($classIds)],
                'student_id' => $studentId,
                'average_score' => 6.5 + (($index % 5) * 0.5),
                'academic_rank' => $index % 3 === 0 ? 'Gioi' : 'Kha',
                'status' => 'draft',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedConduct(int $yearId, int $semesterOneId, int $semesterTwoId, array $classIds, array $studentIds, array $users): void
    {
        $rules = [
            ['PHONE_FREE', 'Thuc hien truong hoc khong dien thoai', 10, 'bonus'],
            ['STEM_ACTIVE', 'Tham gia ngay hoi STEM', 5, 'bonus'],
            ['LATE', 'Di tre khong ly do', -3, 'deduction'],
            ['UNIFORM', 'Vi pham dong phuc', -2, 'deduction'],
        ];

        $ruleIds = [];
        foreach ($rules as [$code, $name, $points, $type]) {
            $ruleIds[] = DB::table('conduct_rules')->insertGetId([
                'code' => $code,
                'name' => $name,
                'points' => $points,
                'rule_type' => $type,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        foreach ([['Tot', 90, 100], ['Kha', 75, 89], ['Dat', 50, 74], ['Chua dat', 0, 49]] as [$rating, $min, $max]) {
            DB::table('conduct_rating_rules')->insert([
                'rating' => $rating,
                'min_score' => $min,
                'max_score' => $max,
                'description' => 'Demo conduct rating',
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        foreach ($studentIds as $index => $studentId) {
            $summaryId = DB::table('conduct_score_summaries')->insertGetId([
                'school_year_id' => $yearId,
                'semester_id' => $semesterTwoId,
                'class_id' => $classIds[$index % count($classIds)],
                'student_id' => $studentId,
                'score' => 80 + ($index % 15),
                'rating' => $index % 4 === 0 ? 'Tot' : 'Kha',
                'status' => 'approved',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('conduct_records')->insert([
                'school_year_id' => $yearId,
                'semester_id' => $semesterTwoId,
                'class_id' => $classIds[$index % count($classIds)],
                'student_id' => $studentId,
                'conduct_rule_id' => $ruleIds[$index % count($ruleIds)],
                'points' => $index % 2 === 0 ? 5 : -2,
                'recorded_date' => '2026-03-01',
                'note' => 'Demo conduct record',
                'status' => 'approved',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('conduct_approval_logs')->insert([
                'conduct_score_summary_id' => $summaryId,
                'approved_by' => $users['gvcn'],
                'status' => 'approved',
                'note' => 'Approved demo conduct summary',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedAttendance(int $yearId, int $semesterId, array $classIds, array $subjectIds, array $teacherIds, array $studentIds, array $users): void
    {
        $sessionId = DB::table('attendance_sessions')->insertGetId([
            'school_year_id' => $yearId,
            'semester_id' => $semesterId,
            'class_id' => $classIds[0],
            'subject_id' => array_values($subjectIds)[0],
            'teacher_id' => $teacherIds[0],
            'session_date' => '2026-03-15',
            'session_period' => 'Tiet 1',
            'status' => 'closed',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        foreach (array_slice($studentIds, 0, 5) as $index => $studentId) {
            DB::table('attendance_records')->insert([
                'attendance_session_id' => $sessionId,
                'school_year_id' => $yearId,
                'semester_id' => $semesterId,
                'class_id' => $classIds[0],
                'student_id' => $studentId,
                'status' => $index === 0 ? 'absent_excused' : 'present',
                'reason' => $index === 0 ? 'Demo excused absence' : null,
                'is_excused' => $index === 0,
                'recorded_by' => $users['giao_vien_bo_mon'],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedCampaigns(int $yearId, int $semesterId, array $classIds, array $studentIds, array $users): void
    {
        $campaigns = [
            ['Mo hinh truong hoc khong dien thoai', 'discipline'],
            ['Ngay hoi STEM demo', 'stem'],
            ['Bao tuong demo', 'movement'],
        ];

        foreach ($campaigns as $index => [$title, $type]) {
            $campaignId = DB::table('campaigns')->insertGetId([
                'school_year_id' => $yearId,
                'semester_id' => $semesterId,
                'title' => $title,
                'campaign_type' => $type,
                'start_date' => '2026-03-01',
                'end_date' => '2026-04-30',
                'description' => 'Demo campaign only',
                'status' => 'open',
                'created_by' => $users['doan_truong'],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $criteriaId = DB::table('campaign_criteria')->insertGetId([
                'campaign_id' => $campaignId,
                'name' => 'Tieu chi demo',
                'max_score' => 10,
                'order_index' => 1,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('campaign_participants')->insert([
                'campaign_id' => $campaignId,
                'class_id' => $classIds[$index % count($classIds)],
                'student_id' => $studentIds[$index],
                'participant_name' => 'Nhom demo '.$index,
                'status' => 'approved',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('campaign_class_scores')->insert([
                'campaign_id' => $campaignId,
                'campaign_criteria_id' => $criteriaId,
                'class_id' => $classIds[$index % count($classIds)],
                'score' => 8 + $index,
                'note' => 'Demo class score',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedSportsEvent(int $yearId, int $semesterId, array $classIds, array $studentIds, array $teacherIds, array $users): void
    {
        $eventId = DB::table('events')->insertGetId([
            'school_year_id' => $yearId,
            'semester_id' => $semesterId,
            'title' => 'Hoi thao cap truong demo',
            'event_type' => 'sports',
            'description' => 'Demo sports event only',
            'starts_at' => '2026-04-10 07:30:00',
            'ends_at' => '2026-04-10 16:30:00',
            'status' => 'open',
            'created_by' => $users['doan_truong'],
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $categoryId = DB::table('event_categories')->insertGetId([
            'event_id' => $eventId,
            'name' => 'Bong da nam demo',
            'category_type' => 'football',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('event_organizers')->insert([
            'event_id' => $eventId,
            'teacher_id' => $teacherIds[4],
            'role' => 'Organizer',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $teamIds = [];
        foreach ([0, 1] as $teamIndex) {
            $teamIds[$teamIndex] = DB::table('event_teams')->insertGetId([
                'event_id' => $eventId,
                'event_category_id' => $categoryId,
                'class_id' => $classIds[$teamIndex],
                'name' => 'Doi '.($teamIndex + 1).' Demo',
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            foreach (array_slice($studentIds, $teamIndex * 5, 5) as $studentId) {
                DB::table('event_team_members')->insert([
                    'event_team_id' => $teamIds[$teamIndex],
                    'student_id' => $studentId,
                    'role' => 'Member',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }

        $scheduleId = DB::table('event_schedules')->insertGetId([
            'event_id' => $eventId,
            'event_category_id' => $categoryId,
            'starts_at' => '2026-04-10 08:00:00',
            'ends_at' => '2026-04-10 09:00:00',
            'location' => 'San truong demo',
            'status' => 'scheduled',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $matchId = DB::table('event_matches')->insertGetId([
            'event_id' => $eventId,
            'event_schedule_id' => $scheduleId,
            'home_team_id' => $teamIds[0],
            'away_team_id' => $teamIds[1],
            'round' => 'Final demo',
            'status' => 'completed',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        foreach ([[$teamIds[0], 2], [$teamIds[1], 1]] as [$teamId, $score]) {
            DB::table('event_scores')->insert([
                'event_match_id' => $matchId,
                'event_team_id' => $teamId,
                'score' => $score,
                'note' => 'Demo score',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        DB::table('event_judges')->insert([
            'event_id' => $eventId,
            'teacher_id' => $teacherIds[5],
            'role' => 'Judge',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $resultId = DB::table('event_results')->insertGetId([
            'event_id' => $eventId,
            'event_category_id' => $categoryId,
            'event_team_id' => $teamIds[0],
            'rank' => 1,
            'score' => 2,
            'award_title' => 'Giai nhat demo',
            'status' => 'published',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('event_awards')->insert([
            'event_id' => $eventId,
            'event_result_id' => $resultId,
            'title' => 'Cup hoi thao demo',
            'description' => 'Demo award only',
            'awarded_date' => '2026-04-10',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function seedRewardsAndDiscipline(int $yearId, int $semesterId, array $classIds, array $studentIds, array $teacherIds, array $users): void
    {
        $rewardTypeId = DB::table('reward_types')->insertGetId([
            'code' => 'STEM_DEMO',
            'name' => 'Khen thuong STEM demo',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('rewards')->insert([
            'school_year_id' => $yearId,
            'semester_id' => $semesterId,
            'reward_type_id' => $rewardTypeId,
            'student_id' => $studentIds[0],
            'title' => 'Khen thuong hoc sinh demo',
            'issued_date' => '2026-04-15',
            'description' => 'Demo reward only',
            'status' => 'approved',
            'issued_by' => $users['bgh'],
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $disciplineTypeId = DB::table('discipline_types')->insertGetId([
            'code' => 'PHONE_DEMO',
            'name' => 'Vi pham quy dinh dien thoai demo',
            'severity' => 'low',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $caseId = DB::table('discipline_cases')->insertGetId([
            'school_year_id' => $yearId,
            'semester_id' => $semesterId,
            'class_id' => $classIds[0],
            'student_id' => $studentIds[1],
            'discipline_type_id' => $disciplineTypeId,
            'incident_date' => '2026-03-20',
            'severity' => 'low',
            'status' => 'closed',
            'description' => 'Demo discipline case only',
            'created_by' => $users['giam_thi'],
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('discipline_actions')->insert([
            'discipline_case_id' => $caseId,
            'action_type' => 'Nhac nho demo',
            'action_date' => '2026-03-21',
            'issued_by' => $teacherIds[5],
            'note' => 'Demo action only',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function seedFees(int $yearId, int $semesterId, array $classIds, array $studentIds, array $users): void
    {
        $feeTypeId = DB::table('fee_types')->insertGetId([
            'code' => 'HP_DEMO',
            'name' => 'Hoc phi demo',
            'default_amount' => 350000,
            'is_required' => true,
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $feePlanId = DB::table('fee_plans')->insertGetId([
            'school_year_id' => $yearId,
            'semester_id' => $semesterId,
            'fee_type_id' => $feeTypeId,
            'amount' => 350000,
            'applies_to' => 'Toan truong demo',
            'due_date' => '2026-04-30',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        foreach (array_slice($studentIds, 0, 8) as $index => $studentId) {
            $studentFeeId = DB::table('student_fees')->insertGetId([
                'school_year_id' => $yearId,
                'semester_id' => $semesterId,
                'student_id' => $studentId,
                'class_id' => $classIds[$index % count($classIds)],
                'fee_plan_id' => $feePlanId,
                'invoice_no' => 'INV-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'total_amount' => 350000,
                'paid_amount' => $index < 3 ? 350000 : 0,
                'due_date' => '2026-04-30',
                'status' => $index < 3 ? 'paid' : 'unpaid',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            if ($index < 3) {
                $paymentId = DB::table('payments')->insertGetId([
                    'student_fee_id' => $studentFeeId,
                    'amount' => 350000,
                    'method' => 'cash',
                    'paid_at' => '2026-04-01 09:00:00',
                    'status' => 'completed',
                    'collected_by' => $users['ke_toan'],
                    'note' => 'Demo payment only',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                DB::table('receipts')->insert([
                    'payment_id' => $paymentId,
                    'receipt_no' => 'RCT-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'issued_at' => '2026-04-01 09:05:00',
                    'status' => 'issued',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }
    }

    private function seedAnnouncements(int $yearId, int $semesterId, array $classIds, array $studentIds, array $users): void
    {
        $announcementId = DB::table('announcements')->insertGetId([
            'school_year_id' => $yearId,
            'semester_id' => $semesterId,
            'title' => 'Thong bao demo truong hoc khong dien thoai',
            'body' => 'Noi dung thong bao demo, khong phai du lieu that.',
            'audience' => 'all',
            'published_at' => $this->now,
            'status' => 'published',
            'created_by' => $users['giao_vu'],
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('announcement_recipients')->insert([
            'announcement_id' => $announcementId,
            'student_id' => $studentIds[0],
            'class_id' => $classIds[0],
            'recipient_type' => 'student',
            'status' => 'sent',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('notification_reads')->insert([
            'announcement_id' => $announcementId,
            'user_id' => $users['hoc_sinh'],
            'read_at' => $this->now,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function seedLogs(array $users): void
    {
        DB::table('login_logs')->insert([
            'user_id' => $users['admin'],
            'email' => 'admin@vvk.local',
            'status' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DatabaseSeeder',
            'logged_at' => $this->now,
            'metadata' => json_encode(['seed' => true]),
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('audit_logs')->insert([
            'actor_id' => $users['admin'],
            'action' => 'system.seeded',
            'subject_type' => null,
            'subject_id' => null,
            'before_values' => null,
            'after_values' => json_encode(['note' => 'Fake demo data only. No real student data.']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DatabaseSeeder',
            'metadata' => json_encode(['phase' => 'database-schema']),
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }
}
