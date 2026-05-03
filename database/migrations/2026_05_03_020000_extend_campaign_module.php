<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaigns', 'organizer_unit')) {
                $table->string('organizer_unit')->nullable()->after('campaign_type');
            }

            if (! Schema::hasColumn('campaigns', 'target_audience')) {
                $table->string('target_audience')->default('all_students')->index()->after('organizer_unit');
            }

            if (! Schema::hasColumn('campaigns', 'registration_modes')) {
                $table->json('registration_modes')->nullable()->after('target_audience');
            }

            if (! Schema::hasColumn('campaigns', 'conduct_points_per_student')) {
                $table->integer('conduct_points_per_student')->default(0)->after('registration_modes');
            }

            if (! Schema::hasColumn('campaigns', 'class_competition_points')) {
                $table->decimal('class_competition_points', 8, 2)->default(0)->after('conduct_points_per_student');
            }

            if (! Schema::hasColumn('campaigns', 'summary_report')) {
                $table->longText('summary_report')->nullable()->after('description');
            }

            if (! Schema::hasColumn('campaigns', 'summarized_by')) {
                $table->foreignId('summarized_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaigns', 'summarized_at')) {
                $table->timestamp('summarized_at')->nullable()->after('summarized_by');
            }

            $table->index(['campaign_type', 'status'], 'campaigns_type_status_index');
            $table->index(['start_date', 'end_date'], 'campaigns_date_range_index');
        });

        Schema::table('campaign_criteria', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaign_criteria', 'code')) {
                $table->string('code')->nullable()->after('campaign_id');
            }

            if (! Schema::hasColumn('campaign_criteria', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('campaign_criteria', 'weight')) {
                $table->decimal('weight', 6, 2)->default(1)->after('max_score');
            }

            $table->index(['campaign_id', 'status'], 'campaign_criteria_campaign_status_index');
        });

        Schema::table('campaign_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaign_participants', 'participant_type')) {
                $table->string('participant_type')->default('individual')->index()->after('campaign_id');
            }

            if (! Schema::hasColumn('campaign_participants', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->after('participant_name')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_participants', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('registered_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_participants', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('campaign_participants', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_participants', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn('campaign_participants', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('campaign_participants', 'note')) {
                $table->text('note')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('campaign_participants', 'metadata')) {
                $table->json('metadata')->nullable()->after('note');
            }

            $table->index(['campaign_id', 'status'], 'campaign_participants_campaign_status_index');
            $table->index(['participant_type', 'status'], 'campaign_participants_type_status_index');
        });

        Schema::table('campaign_results', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaign_results', 'conduct_points')) {
                $table->integer('conduct_points')->nullable()->after('award_title');
            }

            if (! Schema::hasColumn('campaign_results', 'class_points')) {
                $table->decimal('class_points', 8, 2)->nullable()->after('conduct_points');
            }

            if (! Schema::hasColumn('campaign_results', 'note')) {
                $table->text('note')->nullable()->after('class_points');
            }

            if (! Schema::hasColumn('campaign_results', 'entered_by')) {
                $table->foreignId('entered_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_results', 'published_by')) {
                $table->foreignId('published_by')->nullable()->after('entered_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_results', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('published_by');
            }

            $table->index(['campaign_id', 'status'], 'campaign_results_campaign_status_index');
            $table->index(['rank', 'total_score'], 'campaign_results_rank_score_index');
        });

        Schema::table('campaign_class_scores', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaign_class_scores', 'campaign_result_id')) {
                $table->foreignId('campaign_result_id')->nullable()->after('campaign_id')->constrained('campaign_results')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_class_scores', 'applied_by')) {
                $table->foreignId('applied_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaign_class_scores', 'applied_at')) {
                $table->timestamp('applied_at')->nullable()->after('applied_by');
            }
        });

        Schema::create('campaign_participant_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['campaign_participant_id', 'student_id'], 'campaign_member_unique');
            $table->index('student_id');
        });

        Schema::create('campaign_result_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_criterion_id')->constrained('campaign_criteria')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['campaign_result_id', 'campaign_criterion_id'], 'campaign_result_criterion_unique');
            $table->index('campaign_criterion_id');
        });

        Schema::create('campaign_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_result_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('file_type')->default('evidence')->index();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['campaign_id', 'file_type']);
            $table->index(['campaign_result_id', 'file_type']);
        });

        Schema::create('campaign_point_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_participant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('application_type')->index();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->foreignId('conduct_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_class_score_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('points', 8, 2)->default(0);
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_result_id', 'application_type', 'student_id'], 'campaign_points_result_student_unique');
            $table->unique(['campaign_result_id', 'application_type', 'class_id'], 'campaign_points_result_class_unique');
            $table->index(['campaign_id', 'application_type']);
        });

        DB::table('campaigns')
            ->whereNull('registration_modes')
            ->update([
                'target_audience' => 'all_students',
                'registration_modes' => json_encode(['individual', 'team', 'class']),
                'updated_at' => now(),
            ]);

        DB::table('campaigns')->where('status', 'open')->update(['status' => 'registration_open', 'updated_at' => now()]);
        DB::table('campaigns')->where('status', 'closed')->update(['status' => 'ended', 'updated_at' => now()]);

        DB::table('campaign_participants')
            ->whereNull('participant_type')
            ->update([
                'participant_type' => 'team',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_point_applications');
        Schema::dropIfExists('campaign_files');
        Schema::dropIfExists('campaign_result_scores');
        Schema::dropIfExists('campaign_participant_members');

        Schema::table('campaign_class_scores', function (Blueprint $table): void {
            if (Schema::hasColumn('campaign_class_scores', 'campaign_result_id')) {
                $table->dropConstrainedForeignId('campaign_result_id');
            }

            if (Schema::hasColumn('campaign_class_scores', 'applied_by')) {
                $table->dropConstrainedForeignId('applied_by');
            }

            if (Schema::hasColumn('campaign_class_scores', 'applied_at')) {
                $table->dropColumn('applied_at');
            }
        });

        Schema::table('campaign_results', function (Blueprint $table): void {
            $table->dropIndex('campaign_results_campaign_status_index');
            $table->dropIndex('campaign_results_rank_score_index');
            $table->dropConstrainedForeignId('entered_by');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['conduct_points', 'class_points', 'note', 'published_at']);
        });

        Schema::table('campaign_participants', function (Blueprint $table): void {
            $table->dropIndex('campaign_participants_campaign_status_index');
            $table->dropIndex('campaign_participants_type_status_index');
            $table->dropConstrainedForeignId('registered_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['participant_type', 'approved_at', 'rejected_at', 'rejection_reason', 'note', 'metadata']);
        });

        Schema::table('campaign_criteria', function (Blueprint $table): void {
            $table->dropIndex('campaign_criteria_campaign_status_index');
            $table->dropColumn(['code', 'description', 'weight']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('campaigns_type_status_index');
            $table->dropIndex('campaigns_date_range_index');
            $table->dropConstrainedForeignId('summarized_by');
            $table->dropColumn([
                'organizer_unit',
                'target_audience',
                'registration_modes',
                'conduct_points_per_student',
                'class_competition_points',
                'summary_report',
                'summarized_at',
            ]);
        });
    }
};
