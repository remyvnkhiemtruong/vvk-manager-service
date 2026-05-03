<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conduct_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('conduct_rules', 'severity')) {
                $table->string('severity')->default('minor')->index()->after('rule_type');
            }

            if (! Schema::hasColumn('conduct_rules', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false)->index()->after('severity');
            }

            if (! Schema::hasColumn('conduct_rules', 'description')) {
                $table->text('description')->nullable()->after('requires_approval');
            }

            if (! Schema::hasColumn('conduct_rules', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(1)->index()->after('description');
            }
        });

        Schema::table('conduct_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('conduct_records', 'description')) {
                $table->text('description')->nullable()->after('note');
            }

            if (! Schema::hasColumn('conduct_records', 'recorded_by')) {
                $table->foreignId('recorded_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_records', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_records', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('conduct_records', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_records', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn('conduct_records', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('conduct_records', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_records', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }

            if (! Schema::hasColumn('conduct_records', 'metadata')) {
                $table->json('metadata')->nullable()->after('cancelled_at');
            }

            $table->index(['status', 'recorded_date'], 'conduct_records_status_date_index');
            $table->index(['student_id', 'status'], 'conduct_records_student_status_index');
        });

        Schema::create('conduct_record_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conduct_record_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['conduct_record_id', 'created_at'], 'conduct_evidences_record_created_index');
        });

        Schema::table('conduct_score_summaries', function (Blueprint $table): void {
            if (! Schema::hasColumn('conduct_score_summaries', 'base_score')) {
                $table->integer('base_score')->default(100)->after('student_id');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'bonus_points')) {
                $table->integer('bonus_points')->default(0)->after('base_score');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'minus_points')) {
                $table->integer('minus_points')->default(0)->after('bonus_points');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'adjustment_points')) {
                $table->integer('adjustment_points')->default(0)->after('minus_points');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'lock_status')) {
                $table->string('lock_status')->default('open')->index()->after('status');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('lock_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('locked_by');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'unlocked_by')) {
                $table->foreignId('unlocked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'unlocked_at')) {
                $table->timestamp('unlocked_at')->nullable()->after('unlocked_by');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'homeroom_comment')) {
                $table->text('homeroom_comment')->nullable()->after('note');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'commented_by')) {
                $table->foreignId('commented_by')->nullable()->after('homeroom_comment')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'commented_at')) {
                $table->timestamp('commented_at')->nullable()->after('commented_by');
            }

            if (! Schema::hasColumn('conduct_score_summaries', 'last_recalculated_at')) {
                $table->timestamp('last_recalculated_at')->nullable()->after('commented_at');
            }

            $table->index(['lock_status', 'status'], 'conduct_summaries_lock_status_index');
            $table->index(['rating', 'score'], 'conduct_summaries_rating_score_index');
        });

        Schema::table('conduct_adjustments', function (Blueprint $table): void {
            if (! Schema::hasColumn('conduct_adjustments', 'action')) {
                $table->string('action')->nullable()->index()->after('points_delta');
            }
        });

        Schema::table('conduct_approval_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('conduct_approval_logs', 'conduct_record_id')) {
                $table->foreignId('conduct_record_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('conduct_approval_logs', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conduct_approval_logs', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            }

            $table->index(['conduct_record_id', 'status'], 'conduct_approval_record_status_index');
        });

        DB::table('conduct_score_summaries')
            ->whereNull('lock_status')
            ->update([
                'lock_status' => 'open',
                'base_score' => DB::raw('score'),
                'last_recalculated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('conduct_approval_logs', function (Blueprint $table): void {
            $table->dropIndex('conduct_approval_record_status_index');
            $table->dropConstrainedForeignId('conduct_record_id');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn('resolved_at');
        });

        Schema::table('conduct_adjustments', function (Blueprint $table): void {
            $table->dropColumn('action');
        });

        Schema::table('conduct_score_summaries', function (Blueprint $table): void {
            $table->dropIndex('conduct_summaries_lock_status_index');
            $table->dropIndex('conduct_summaries_rating_score_index');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropConstrainedForeignId('commented_by');
            $table->dropColumn([
                'base_score',
                'bonus_points',
                'minus_points',
                'adjustment_points',
                'lock_status',
                'locked_at',
                'unlocked_at',
                'homeroom_comment',
                'commented_at',
                'last_recalculated_at',
            ]);
        });

        Schema::dropIfExists('conduct_record_evidences');

        Schema::table('conduct_records', function (Blueprint $table): void {
            $table->dropIndex('conduct_records_status_date_index');
            $table->dropIndex('conduct_records_student_status_index');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'description',
                'approved_at',
                'rejected_at',
                'rejection_reason',
                'cancelled_at',
                'metadata',
            ]);
        });

        Schema::table('conduct_rules', function (Blueprint $table): void {
            $table->dropColumn(['severity', 'requires_approval', 'description', 'sort_order']);
        });
    }
};
