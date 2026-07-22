<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('current_company')->nullable()->after('title');
            $table->string('industry')->nullable()->after('specialization');
            $table->string('notice_period')->nullable()->after('availability');
            $table->unsignedTinyInteger('quality_score')->default(0)->after('contact_allowed');
            $table->unsignedTinyInteger('cv_completeness_score')->default(0)->after('quality_score');
            $table->unsignedTinyInteger('recruiter_rating')->nullable()->after('cv_completeness_score');
            $table->json('quality_factors')->nullable()->after('recruiter_rating');
            $table->json('parsed_profile')->nullable()->after('quality_factors');
            $table->timestamp('last_quality_calculated_at')->nullable()->after('parsed_profile');
            $table->index(['company_id', 'quality_score'], 'candidates_company_quality_idx');
            $table->index(['company_id', 'notice_period'], 'candidates_company_notice_idx');
        });

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->unsignedTinyInteger('education')->default(60)->after('experience');
            $table->unsignedTinyInteger('location_fit')->default(60)->after('education');
            $table->unsignedTinyInteger('notice_period_fit')->default(60)->after('availability');
            $table->string('ranking_band')->default('WEAK')->after('recommendation');
            $table->json('risk_indicators')->nullable()->after('rationale');
            $table->json('interview_questions')->nullable()->after('risk_indicators');
            $table->string('recruiter_decision')->nullable()->after('interview_questions');
            $table->text('recruiter_decision_note')->nullable()->after('recruiter_decision');
            $table->string('recruiter_feedback')->nullable()->after('recruiter_decision_note');
            $table->text('recruiter_feedback_note')->nullable()->after('recruiter_feedback');
            $table->foreignId('feedback_by')->nullable()->after('recruiter_feedback_note')->constrained('users')->nullOnDelete();
            $table->timestamp('feedback_at')->nullable()->after('feedback_by');
            $table->index(['job_id', 'ranking_band', 'overall'], 'scores_job_band_overall_idx');
            $table->index(['job_id', 'recruiter_decision'], 'scores_job_decision_idx');
        });

        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->timestamp('ai_shortlisted_at')->nullable()->after('reviewed_by');
            $table->foreignId('ai_shortlisted_by')->nullable()->after('ai_shortlisted_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('talent_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->default('Other');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'talent_pools_company_name_unique');
            $table->index(['company_id', 'category'], 'talent_pools_company_category_idx');
        });

        Schema::create('talent_pool_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['talent_pool_id', 'candidate_id'], 'pool_candidate_unique');
        });

        Schema::create('ai_recommendation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_score_id')->nullable()->constrained('candidate_scores')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->string('feedback');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'feedback', 'created_at'], 'ai_feedback_company_feedback_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendation_feedback');
        Schema::dropIfExists('talent_pool_candidates');
        Schema::dropIfExists('talent_pools');

        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_shortlisted_by');
            $table->dropColumn('ai_shortlisted_at');
        });

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->dropIndex('scores_job_band_overall_idx');
            $table->dropIndex('scores_job_decision_idx');
            $table->dropConstrainedForeignId('feedback_by');
            $table->dropColumn([
                'education',
                'location_fit',
                'notice_period_fit',
                'ranking_band',
                'risk_indicators',
                'interview_questions',
                'recruiter_decision',
                'recruiter_decision_note',
                'recruiter_feedback',
                'recruiter_feedback_note',
                'feedback_at',
            ]);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex('candidates_company_quality_idx');
            $table->dropIndex('candidates_company_notice_idx');
            $table->dropColumn([
                'current_company',
                'industry',
                'notice_period',
                'quality_score',
                'cv_completeness_score',
                'recruiter_rating',
                'quality_factors',
                'parsed_profile',
                'last_quality_calculated_at',
            ]);
        });
    }
};
