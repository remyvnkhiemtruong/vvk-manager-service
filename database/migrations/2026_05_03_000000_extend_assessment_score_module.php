<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'assessment_mode')) {
                $table->string('assessment_mode')->default('numeric')->index()->after('default_credit');
            }
        });

        Schema::table('score_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('score_types', 'input_type')) {
                $table->string('input_type')->default('numeric')->index()->after('weight');
            }

            if (! Schema::hasColumn('score_types', 'counts_toward_average')) {
                $table->boolean('counts_toward_average')->default(true)->index()->after('input_type');
            }
        });

        Schema::table('score_columns', function (Blueprint $table): void {
            if (! Schema::hasColumn('score_columns', 'class_id')) {
                $table->foreignId('class_id')->nullable()->after('semester_id')->constrained('classes')->nullOnDelete();
            }

            if (! Schema::hasColumn('score_columns', 'code')) {
                $table->string('code', 64)->nullable()->after('score_type_id');
            }

            if (! Schema::hasColumn('score_columns', 'lock_status')) {
                $table->string('lock_status')->default('open')->index()->after('status');
            }

            if (! Schema::hasColumn('score_columns', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('lock_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('score_columns', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('locked_by');
            }

            if (! Schema::hasColumn('score_columns', 'unlock_requested_by')) {
                $table->foreignId('unlock_requested_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('score_columns', 'unlock_requested_at')) {
                $table->timestamp('unlock_requested_at')->nullable()->after('unlock_requested_by');
            }

            if (! Schema::hasColumn('score_columns', 'unlock_reason')) {
                $table->text('unlock_reason')->nullable()->after('unlock_requested_at');
            }

            $table->index(['school_year_id', 'semester_id', 'class_id'], 'score_columns_period_class_index');
            $table->index(['subject_id', 'score_type_id'], 'score_columns_subject_type_index');
        });

        Schema::table('student_scores', function (Blueprint $table): void {
            if (Schema::hasColumn('student_scores', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->change();
            }

            if (! Schema::hasColumn('student_scores', 'comment')) {
                $table->text('comment')->nullable()->after('score');
            }

            $table->unique(['student_id', 'score_column_id'], 'student_scores_student_column_unique');
            $table->index(['subject_id', 'score_type_id'], 'student_scores_subject_type_index');
        });

        Schema::create('score_lock_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('score_column_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('reason');
            $table->text('resolution_note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['score_column_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_lock_requests');

        Schema::table('student_scores', function (Blueprint $table): void {
            $table->dropUnique('student_scores_student_column_unique');
            $table->dropIndex('student_scores_subject_type_index');

            if (Schema::hasColumn('student_scores', 'comment')) {
                $table->dropColumn('comment');
            }

            if (Schema::hasColumn('student_scores', 'score')) {
                $table->decimal('score', 5, 2)->nullable(false)->change();
            }
        });

        Schema::table('score_columns', function (Blueprint $table): void {
            $table->dropIndex('score_columns_period_class_index');
            $table->dropIndex('score_columns_subject_type_index');
            $table->dropConstrainedForeignId('class_id');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('unlock_requested_by');
            $table->dropColumn(['code', 'lock_status', 'locked_at', 'unlock_requested_at', 'unlock_reason']);
        });

        Schema::table('score_types', function (Blueprint $table): void {
            $table->dropColumn(['input_type', 'counts_toward_average']);
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('assessment_mode');
        });
    }
};
