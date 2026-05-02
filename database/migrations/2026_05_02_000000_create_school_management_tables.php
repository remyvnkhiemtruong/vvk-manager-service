<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createSystemTables();
        $this->createSchoolTables();
        $this->createStudentTables();
        $this->createAcademicScoreTables();
        $this->createConductTables();
        $this->createAttendanceTables();
        $this->createCampaignTables();
        $this->createEventTables();
        $this->createRewardAndDisciplineTables();
        $this->createFeeTables();
        $this->createAnnouncementTables();
    }

    public function down(): void
    {
        collect([
            'notification_reads',
            'announcement_recipients',
            'announcements',
            'fee_exemptions',
            'receipts',
            'payments',
            'student_fees',
            'fee_plans',
            'fee_types',
            'discipline_actions',
            'discipline_cases',
            'discipline_types',
            'rewards',
            'reward_types',
            'event_awards',
            'event_results',
            'event_judges',
            'event_scores',
            'event_matches',
            'event_schedules',
            'event_team_members',
            'event_teams',
            'event_registrations',
            'event_organizers',
            'event_categories',
            'events',
            'campaign_class_scores',
            'campaign_results',
            'campaign_participants',
            'campaign_criteria',
            'campaigns',
            'attendance_records',
            'attendance_sessions',
            'conduct_approval_logs',
            'conduct_adjustments',
            'conduct_rating_rules',
            'conduct_score_summaries',
            'conduct_records',
            'conduct_rules',
            'teacher_comments',
            'academic_results',
            'score_change_logs',
            'student_scores',
            'score_columns',
            'score_types',
            'student_class_enrollments',
            'student_documents',
            'student_guardians',
            'guardians',
            'students',
            'teaching_assignments',
            'teachers',
            'subjects',
            'classes',
            'grades',
            'semesters',
            'school_years',
            'login_logs',
            'audit_logs',
            'user_roles',
            'role_permissions',
            'permissions',
            'roles',
        ])->each(fn (string $table) => Schema::dropIfExists($table));
    }

    private function createSystemTables(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('module')->index();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('status')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    private function createSchoolTables(): void
    {
        Schema::create('school_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('term_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_year_id', 'term_number']);
            $table->index(['school_year_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('homeroom_teacher_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('room')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_year_id', 'name']);
            $table->index(['school_year_id', 'grade_id']);
            $table->index('homeroom_teacher_id');
            $table->index('created_at');
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('department')->nullable();
            $table->decimal('default_credit', 5, 2)->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('teachers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('teacher_code')->unique();
            $table->string('staff_code')->nullable()->unique();
            $table->string('full_name');
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('specialization')->nullable();
            $table->string('qualification')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::table('classes', function (Blueprint $table): void {
            $table->foreign('homeroom_teacher_id')->references('id')->on('teachers')->nullOnDelete();
        });

        Schema::create('teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['teacher_id', 'class_id', 'subject_id', 'semester_id'], 'teaching_assignments_unique');
            $table->index(['school_year_id', 'semester_id']);
            $table->index(['class_id', 'subject_id']);
            $table->index('teacher_id');
            $table->index('created_at');
        });
    }

    private function createStudentTables(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('student_code')->unique();
            $table->string('full_name');
            $table->string('gender');
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('relationship')->nullable();
            $table->string('address')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('student_guardians', function (Blueprint $table): void {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['student_id', 'guardian_id']);
        });

        Schema::create('student_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('title');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('status')->default('active')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('student_class_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->date('enrolled_at')->nullable();
            $table->date('left_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_id', 'semester_id'], 'student_enrollment_semester_unique');
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });
    }

    private function createAcademicScoreTables(): void
    {
        Schema::create('score_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('weight', 5, 2)->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('score_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('order_index')->default(1);
            $table->decimal('max_score', 5, 2)->default(10);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('created_at');
        });

        Schema::create('student_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_column_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('status')->default('draft')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('score_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_score_id')->constrained()->cascadeOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index('student_score_id');
            $table->index('created_at');
        });

        Schema::create('academic_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('average_score', 5, 2)->nullable();
            $table->string('academic_rank')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_id', 'semester_id']);
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('created_at');
        });

        Schema::create('teacher_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment');
            $table->string('status')->default('published')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('created_at');
        });
    }

    private function createConductTables(): void
    {
        Schema::create('conduct_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('points')->default(0);
            $table->string('rule_type')->default('bonus');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('conduct_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conduct_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points')->default(0);
            $table->date('recorded_date')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('conduct_score_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(100);
            $table->string('rating')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_id', 'semester_id']);
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('created_at');
        });

        Schema::create('conduct_rating_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rating')->unique();
            $table->unsignedSmallInteger('min_score');
            $table->unsignedSmallInteger('max_score');
            $table->string('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('conduct_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conduct_record_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('conduct_score_summary_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->integer('points_delta')->default(0);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('conduct_approval_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conduct_score_summary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    private function createAttendanceTables(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->date('session_date');
            $table->string('session_period')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('teacher_id');
            $table->index('created_at');
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('present')->index();
            $table->string('reason')->nullable();
            $table->boolean('is_excused')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['attendance_session_id', 'student_id'], 'attendance_session_student_unique');
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });
    }

    private function createCampaignTables(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('campaign_type')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('created_at');
        });

        Schema::create('campaign_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('max_score', 8, 2)->default(10);
            $table->unsignedTinyInteger('order_index')->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('campaign_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('participant_name')->nullable();
            $table->string('status')->default('registered')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('campaign_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_participant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->string('award_title')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('campaign_class_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_criteria_id')->nullable()->constrained('campaign_criteria')->nullOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['campaign_id', 'campaign_criteria_id', 'class_id'], 'campaign_class_criteria_unique');
            $table->index('class_id');
            $table->index('created_at');
        });
    }

    private function createEventTables(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('event_type')->index();
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('created_at');
        });

        Schema::create('event_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category_type')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('event_organizers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('organizer_name')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('teacher_id');
            $table->index('created_at');
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('registration_type')->default('individual');
            $table->string('status')->default('registered')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_id');
            $table->index('class_id');
            $table->index('created_at');
        });

        Schema::create('event_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('class_id');
            $table->index('created_at');
        });

        Schema::create('event_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->unique(['event_team_id', 'student_id']);
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('event_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('event_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('event_teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('event_teams')->nullOnDelete();
            $table->string('round')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('event_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_team_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('event_judges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('judge_name')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('teacher_id');
            $table->index('created_at');
        });

        Schema::create('event_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('award_title')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('event_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_result_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('awarded_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });
    }

    private function createRewardAndDisciplineTables(): void
    {
        Schema::create('reward_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reward_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->cascadeOnDelete();
            $table->string('title');
            $table->date('issued_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('approved')->index();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('created_at');
        });

        Schema::create('discipline_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('severity')->default('low')->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('discipline_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('incident_date');
            $table->string('severity')->default('low')->index();
            $table->string('status')->default('open')->index();
            $table->text('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('discipline_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_case_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->date('action_date');
            $table->foreignId('issued_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });
    }

    private function createFeeTables(): void
    {
        Schema::create('fee_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('fee_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('applies_to')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('created_at');
        });

        Schema::create('student_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_no')->unique();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('class_id');
            $table->index('student_id');
            $table->index('created_at');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_fee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('cash');
            $table->timestamp('paid_at');
            $table->string('status')->default('completed')->index();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_fee_id');
            $table->index('created_at');
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_no')->unique();
            $table->timestamp('issued_at')->nullable();
            $table->string('status')->default('issued')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        Schema::create('fee_exemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('status')->default('approved')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('student_id');
            $table->index('created_at');
        });
    }

    private function createAnnouncementTables(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('audience')->default('all')->index();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_year_id', 'semester_id']);
            $table->index('created_at');
        });

        Schema::create('announcement_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->string('recipient_type')->nullable();
            $table->string('status')->default('sent')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_id');
            $table->index('class_id');
            $table->index('created_at');
        });

        Schema::create('notification_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
            $table->index('created_at');
        });
    }
};
