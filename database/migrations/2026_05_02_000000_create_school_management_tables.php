<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('module')->index();
            $table->timestamps();
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
        });

        Schema::create('school_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('department')->nullable();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('staff_code')->unique();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('teacher_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('staff')->cascadeOnDelete();
            $table->string('specialization')->nullable();
            $table->string('qualification')->nullable();
            $table->unsignedSmallInteger('years_experience')->default(0);
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('name');
            $table->string('room')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'name']);
        });

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
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('relationship')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('student_guardians', function (Blueprint $table): void {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->nullable();
            $table->timestamps();
            $table->primary(['student_id', 'guardian_id']);
        });

        Schema::create('class_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['student_id', 'school_year_id']);
        });

        Schema::create('teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'class_id', 'subject_id', 'semester_id'], 'teaching_assignment_unique');
        });

        Schema::create('score_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('weight', 5, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('score_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score', 4, 2);
            $table->string('status')->default('draft');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'subject_id', 'semester_id']);
        });

        Schema::create('score_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('score_entry_id')->constrained()->cascadeOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('conduct_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('rating');
            $table->string('status')->default('draft');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'semester_id']);
        });

        Schema::create('conduct_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conduct_score_id')->constrained()->cascadeOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('disciplinary_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('incident_date');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->text('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('disciplinary_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->date('action_date');
            $table->foreignId('issued_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('commendations', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->date('issued_date');
            $table->foreignId('issued_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('commendation_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->string('recipient_name')->nullable();
            $table->timestamps();
        });

        Schema::create('school_events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('event_type')->index();
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('team_name')->nullable();
            $table->string('status')->default('registered');
            $table->timestamps();
        });

        Schema::create('event_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('event_registrations')->nullOnDelete();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->string('award_title')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('fee_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('applies_to')->nullable();
            $table->timestamps();
        });

        Schema::create('fee_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('fee_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method');
            $table->timestamp('paid_at');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->longText('body');
            $table->string('audience')->default('all')->index();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('target');
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        collect([
            'attachments',
            'announcement_reads',
            'announcement_targets',
            'announcements',
            'payments',
            'fee_invoice_items',
            'fee_invoices',
            'fee_plans',
            'fee_categories',
            'event_results',
            'event_registrations',
            'school_events',
            'commendation_recipients',
            'commendations',
            'disciplinary_actions',
            'disciplinary_cases',
            'conduct_revisions',
            'conduct_scores',
            'score_revisions',
            'score_entries',
            'score_categories',
            'teaching_assignments',
            'class_enrollments',
            'student_guardians',
            'guardians',
            'students',
            'classes',
            'teacher_profiles',
            'staff',
            'subjects',
            'grades',
            'semesters',
            'school_years',
            'audit_logs',
            'user_roles',
            'role_permissions',
            'permissions',
            'roles',
        ])->each(fn (string $table) => Schema::dropIfExists($table));
    }
};
