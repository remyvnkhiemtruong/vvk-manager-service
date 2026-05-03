<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'organizer_unit')) {
                $table->string('organizer_unit')->nullable();
            }

            if (! Schema::hasColumn('events', 'location')) {
                $table->string('location')->nullable();
            }

            if (! Schema::hasColumn('events', 'target_audience')) {
                $table->string('target_audience')->default('all_students')->index();
            }

            if (! Schema::hasColumn('events', 'registration_modes')) {
                $table->json('registration_modes')->nullable();
            }

            if (! Schema::hasColumn('events', 'conduct_points_per_student')) {
                $table->integer('conduct_points_per_student')->default(0);
            }

            if (! Schema::hasColumn('events', 'class_competition_points')) {
                $table->decimal('class_competition_points', 8, 2)->default(0);
            }

            if (! Schema::hasColumn('events', 'summary_report')) {
                $table->text('summary_report')->nullable();
            }

            if (! Schema::hasColumn('events', 'summarized_by')) {
                $table->foreignId('summarized_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('events', 'summarized_at')) {
                $table->timestamp('summarized_at')->nullable();
            }

            if (! Schema::hasColumn('events', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('event_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_categories', 'participation_type')) {
                $table->string('participation_type')->default('team')->index();
            }

            if (! Schema::hasColumn('event_categories', 'max_participants')) {
                $table->unsignedSmallInteger('max_participants')->nullable();
            }

            if (! Schema::hasColumn('event_categories', 'gender_rule')) {
                $table->string('gender_rule')->nullable();
            }

            if (! Schema::hasColumn('event_categories', 'allowed_grade_ids')) {
                $table->json('allowed_grade_ids')->nullable();
            }

            if (! Schema::hasColumn('event_categories', 'allowed_class_ids')) {
                $table->json('allowed_class_ids')->nullable();
            }

            if (! Schema::hasColumn('event_categories', 'rules_text')) {
                $table->text('rules_text')->nullable();
            }

            if (! Schema::hasColumn('event_categories', 'scoring_mode')) {
                $table->string('scoring_mode')->default('sport')->index();
            }

            if (! Schema::hasColumn('event_categories', 'sport_rule')) {
                $table->string('sport_rule')->nullable()->index();
            }

            if (! Schema::hasColumn('event_categories', 'judge_score_mode')) {
                $table->string('judge_score_mode')->default('average');
            }

            if (! Schema::hasColumn('event_categories', 'drop_extreme_scores')) {
                $table->boolean('drop_extreme_scores')->default(false);
            }

            if (! Schema::hasColumn('event_categories', 'max_score')) {
                $table->decimal('max_score', 8, 2)->default(100);
            }

            if (! Schema::hasColumn('event_categories', 'order_index')) {
                $table->unsignedInteger('order_index')->default(1)->index();
            }
        });

        Schema::table('event_organizers', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_organizers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_organizers', 'note')) {
                $table->text('note')->nullable();
            }
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_registrations', 'event_team_id')) {
                $table->foreignId('event_team_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('event_registrations', 'participant_name')) {
                $table->string('participant_name')->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_registrations', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_registrations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_registrations', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'note')) {
                $table->text('note')->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('event_teams', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_teams', 'captain_student_id')) {
                $table->foreignId('captain_student_id')->nullable()->constrained('students')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_teams', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_teams', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_teams', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('event_teams', 'group_code')) {
                $table->string('group_code')->nullable()->index();
            }

            if (! Schema::hasColumn('event_teams', 'seed_number')) {
                $table->unsignedSmallInteger('seed_number')->nullable();
            }

            if (! Schema::hasColumn('event_teams', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('event_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_schedules', 'name')) {
                $table->string('name')->nullable();
            }

            if (! Schema::hasColumn('event_schedules', 'schedule_type')) {
                $table->string('schedule_type')->default('match')->index();
            }
        });

        Schema::table('event_matches', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_matches', 'event_category_id')) {
                $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('event_matches', 'group_code')) {
                $table->string('group_code')->nullable()->index();
            }

            if (! Schema::hasColumn('event_matches', 'bracket_round')) {
                $table->string('bracket_round')->nullable();
            }

            if (! Schema::hasColumn('event_matches', 'match_order')) {
                $table->unsignedInteger('match_order')->default(1)->index();
            }

            if (! Schema::hasColumn('event_matches', 'home_score')) {
                $table->decimal('home_score', 8, 2)->nullable();
            }

            if (! Schema::hasColumn('event_matches', 'away_score')) {
                $table->decimal('away_score', 8, 2)->nullable();
            }

            if (! Schema::hasColumn('event_matches', 'home_sets_won')) {
                $table->unsignedSmallInteger('home_sets_won')->default(0);
            }

            if (! Schema::hasColumn('event_matches', 'away_sets_won')) {
                $table->unsignedSmallInteger('away_sets_won')->default(0);
            }

            if (! Schema::hasColumn('event_matches', 'winner_team_id')) {
                $table->foreignId('winner_team_id')->nullable()->constrained('event_teams')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_matches', 'played_at')) {
                $table->timestamp('played_at')->nullable();
            }

            if (! Schema::hasColumn('event_matches', 'result_note')) {
                $table->text('result_note')->nullable();
            }

            if (! Schema::hasColumn('event_matches', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('event_results', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_results', 'event_registration_id')) {
                $table->foreignId('event_registration_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('event_results', 'conduct_points')) {
                $table->integer('conduct_points')->nullable();
            }

            if (! Schema::hasColumn('event_results', 'class_points')) {
                $table->decimal('class_points', 8, 2)->nullable();
            }

            if (! Schema::hasColumn('event_results', 'remarks')) {
                $table->text('remarks')->nullable();
            }

            if (! Schema::hasColumn('event_results', 'entered_by')) {
                $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_results', 'published_by')) {
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_results', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }

            if (! Schema::hasColumn('event_results', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('event_awards', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_awards', 'event_category_id')) {
                $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('event_awards', 'event_team_id')) {
                $table->foreignId('event_team_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('event_awards', 'student_id')) {
                $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('event_awards', 'class_id')) {
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_awards', 'award_type')) {
                $table->string('award_type')->default('ranked')->index();
            }

            if (! Schema::hasColumn('event_awards', 'rank')) {
                $table->unsignedSmallInteger('rank')->nullable();
            }

            if (! Schema::hasColumn('event_awards', 'awarded_by')) {
                $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('event_awards', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        if (! Schema::hasTable('event_files')) {
            Schema::create('event_files', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_result_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('file_type')->default('plan')->index();
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['event_id', 'file_type']);
            });
        }

        if (! Schema::hasTable('event_category_criteria')) {
            Schema::create('event_category_criteria', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_category_id')->constrained()->cascadeOnDelete();
                $table->string('code')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('max_score', 8, 2)->default(10);
                $table->decimal('weight', 8, 2)->default(1);
                $table->unsignedInteger('order_index')->default(1);
                $table->string('status')->default('active')->index();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['event_category_id', 'status']);
            });
        }

        if (! Schema::hasTable('event_match_sets')) {
            Schema::create('event_match_sets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_match_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('set_number');
                $table->decimal('home_score', 8, 2)->default(0);
                $table->decimal('away_score', 8, 2)->default(0);
                $table->foreignId('winner_team_id')->nullable()->constrained('event_teams')->nullOnDelete();
                $table->timestamps();
                $table->unique(['event_match_id', 'set_number']);
            });
        }

        if (! Schema::hasTable('event_group_standings')) {
            Schema::create('event_group_standings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_category_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_team_id')->constrained()->cascadeOnDelete();
                $table->string('group_code')->nullable()->index();
                $table->unsignedSmallInteger('played')->default(0);
                $table->unsignedSmallInteger('won')->default(0);
                $table->unsignedSmallInteger('drawn')->default(0);
                $table->unsignedSmallInteger('lost')->default(0);
                $table->decimal('points', 8, 2)->default(0);
                $table->decimal('score_for', 8, 2)->default(0);
                $table->decimal('score_against', 8, 2)->default(0);
                $table->decimal('score_diff', 8, 2)->default(0);
                $table->decimal('set_for', 8, 2)->default(0);
                $table->decimal('set_against', 8, 2)->default(0);
                $table->decimal('set_diff', 8, 2)->default(0);
                $table->decimal('buchholz', 8, 2)->default(0);
                $table->unsignedSmallInteger('rank')->nullable();
                $table->boolean('needs_manual_rank')->default(false);
                $table->timestamps();
                $table->unique(['event_category_id', 'event_team_id', 'group_code'], 'event_standings_unique');
            });
        }

        if (! Schema::hasTable('event_judge_scores')) {
            Schema::create('event_judge_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_result_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_category_criterion_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('event_judge_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('score', 8, 2)->default(0);
                $table->text('comment')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['event_result_id', 'event_category_criterion_id', 'event_judge_id'], 'event_judge_scores_unique');
            });
        }

        if (! Schema::hasTable('event_class_scores')) {
            Schema::create('event_class_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('event_result_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->decimal('score', 8, 2)->default(0);
                $table->text('note')->nullable();
                $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['event_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('event_award_recipients')) {
            Schema::create('event_award_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_award_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->foreignId('reward_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->unique(['event_award_id', 'student_id'], 'event_award_student_unique');
            });
        }

        if (! Schema::hasTable('event_point_applications')) {
            Schema::create('event_point_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_result_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('event_registration_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('event_team_id')->nullable()->constrained()->nullOnDelete();
                $table->string('application_type')->index();
                $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
                $table->foreignId('conduct_record_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('event_class_score_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('reward_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('points', 8, 2)->default(0);
                $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->unique(['event_result_id', 'application_type', 'student_id', 'class_id'], 'event_points_unique');
            });
        }

        DB::table('events')->where('status', 'open')->update(['status' => 'registration_open', 'updated_at' => now()]);
        DB::table('events')->where('status', 'closed')->update(['status' => 'ended', 'updated_at' => now()]);
        DB::table('event_registrations')->where('status', 'registered')->update(['status' => 'pending', 'updated_at' => now()]);
        DB::table('event_teams')->where('status', 'active')->update(['status' => 'approved', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_point_applications');
        Schema::dropIfExists('event_award_recipients');
        Schema::dropIfExists('event_class_scores');
        Schema::dropIfExists('event_judge_scores');
        Schema::dropIfExists('event_group_standings');
        Schema::dropIfExists('event_match_sets');
        Schema::dropIfExists('event_category_criteria');
        Schema::dropIfExists('event_files');
    }
};
