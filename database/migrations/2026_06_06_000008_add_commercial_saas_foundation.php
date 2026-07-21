<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('ACTIVE');
            $table->string('default_locale', 8)->default('en');
            $table->string('default_currency', 8)->default('SAR');
            $table->string('subscription_status')->default('INTERNAL');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->default('Saudi Arabia');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'role_id', 'is_active'], 'users_company_role_active_idx');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropUnique('candidates_email_unique');
            $table->dropUnique('candidates_linkedin_url_unique');
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->date('consent_captured_at')->nullable()->after('consent_status');
            $table->foreignId('consent_captured_by')->nullable()->after('consent_captured_at')->constrained('users')->nullOnDelete();
            $table->text('consent_evidence')->nullable()->after('consent_captured_by');
            $table->boolean('contact_allowed')->default(false)->after('consent_evidence');
            $table->index(['company_id', 'status', 'created_at'], 'candidates_company_status_created_idx');
            $table->unique(['company_id', 'email'], 'candidates_company_email_unique');
            $table->unique(['company_id', 'linkedin_url'], 'candidates_company_linkedin_unique');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('recruiter_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->string('employment_type')->default('Full-time')->after('location');
            $table->string('public_slug')->nullable()->unique()->after('title');
            $table->longText('requirements')->nullable()->after('description');
            $table->longText('internal_notes')->nullable()->after('requirements');
            $table->index(['company_id', 'approval_status', 'created_at'], 'jobs_company_status_created_idx');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('interview_type')->nullable()->after('job_id');
            $table->string('meeting_link')->nullable()->after('channel');
            $table->index(['company_id', 'status', 'starts_at'], 'interviews_company_status_start_idx');
        });

        Schema::table('salary_benchmarks', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'job_title', 'location'], 'salary_company_title_location_idx');
        });

        Schema::table('ai_search_jobs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'status', 'created_at'], 'ai_search_company_status_created_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'action', 'created_at'], 'audit_company_action_created_idx');
        });

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->default(70)->after('matching_percentage');
            $table->unsignedTinyInteger('manual_override_score')->nullable()->after('confidence');
            $table->foreignId('reviewed_by')->nullable()->after('manual_override_score')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('prompt_version')->default('fallback-v1')->after('reviewed_at');
        });

        Schema::table('candidate_documents', function (Blueprint $table) {
            $table->unsignedInteger('download_count')->default(0)->after('scan_status');
            $table->timestamp('last_downloaded_at')->nullable()->after('download_count');
            $table->string('malware_scan_status')->default('NOT_CONFIGURED')->after('last_downloaded_at');
        });

        Schema::table('candidate_sources', function (Blueprint $table) {
            $table->timestamp('consent_captured_at')->nullable()->after('consent_note');
            $table->foreignId('consent_captured_by')->nullable()->after('consent_captured_at')->constrained('users')->nullOnDelete();
            $table->text('consent_evidence')->nullable()->after('consent_captured_by');
            $table->boolean('contact_allowed')->default(false)->after('consent_evidence');
        });

        Schema::create('candidate_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->string('source')->default('Internal');
            $table->string('current_stage')->default('APPLIED');
            $table->string('status')->default('ACTIVE');
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['candidate_id', 'job_id'], 'candidate_applications_candidate_job_unique');
            $table->index(['company_id', 'current_stage', 'created_at'], 'applications_company_stage_created_idx');
        });

        Schema::create('pipeline_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'to_stage', 'created_at'], 'pipeline_company_stage_created_idx');
        });

        $companyId = DB::table('companies')->where('slug', 'bassir-demo')->value('id');
        if (! $companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Bassir Demo Company',
                'slug' => 'bassir-demo',
                'status' => 'ACTIVE',
                'default_locale' => 'en',
                'default_currency' => 'SAR',
                'subscription_status' => 'INTERNAL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['users', 'candidates', 'jobs', 'interviews', 'salary_benchmarks', 'ai_search_jobs', 'audit_logs'] as $table) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stage_histories');
        Schema::dropIfExists('candidate_applications');

        Schema::table('candidate_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consent_captured_by');
            $table->dropColumn(['consent_captured_at', 'consent_evidence', 'contact_allowed']);
        });

        Schema::table('candidate_documents', function (Blueprint $table) {
            $table->dropColumn(['download_count', 'last_downloaded_at', 'malware_scan_status']);
        });

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['confidence', 'manual_override_score', 'reviewed_at', 'prompt_version']);
        });

        Schema::table('ai_search_jobs', function (Blueprint $table) {
            $table->dropIndex('ai_search_company_status_created_idx');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_company_action_created_idx');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('salary_benchmarks', function (Blueprint $table) {
            $table->dropIndex('salary_company_title_location_idx');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex('interviews_company_status_start_idx');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['interview_type', 'meeting_link']);
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('jobs_company_status_created_idx');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('recruiter_id');
            $table->dropColumn(['employment_type', 'public_slug', 'requirements', 'internal_notes']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex('candidates_company_status_created_idx');
            $table->dropUnique('candidates_company_email_unique');
            $table->dropUnique('candidates_company_linkedin_unique');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('consent_captured_by');
            $table->dropColumn(['consent_captured_at', 'consent_evidence', 'contact_allowed']);
            $table->unique('email', 'candidates_email_unique');
            $table->unique('linkedin_url', 'candidates_linkedin_url_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_company_role_active_idx');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('branches');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');
    }
};
