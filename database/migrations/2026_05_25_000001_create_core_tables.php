<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->constrained()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('linkedin_url')->nullable()->unique();
            $table->string('title');
            $table->string('specialization');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('nationality')->nullable();
            $table->unsignedInteger('years_experience')->default(0);
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->decimal('current_salary', 12, 2)->nullable();
            $table->string('availability')->nullable();
            $table->longText('ai_summary')->nullable();
            $table->enum('consent_status', ['CONSENTED', 'PENDING', 'WITHDRAWN'])->default('PENDING');
            $table->enum('status', ['NEW', 'REVIEWED', 'SHORTLISTED', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED', 'BLACKLISTED'])->default('NEW');
            $table->string('duplicate_hash')->nullable()->index();
            $table->timestamps();
            $table->index(['specialization', 'city', 'status']);
        });

        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('level')->nullable();
            $table->unique(['candidate_id', 'name']);
        });

        Schema::create('candidate_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('level')->nullable();
            $table->unique(['candidate_id', 'name']);
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('mime_type');
            $table->string('storage_path');
            $table->string('checksum');
            $table->string('scan_status')->default('PENDING');
            $table->timestamps();
        });

        Schema::create('candidate_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->text('source_url')->nullable();
            $table->text('consent_note')->nullable();
            $table->timestamps();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('specialization')->nullable();
            $table->string('department');
            $table->string('company');
            $table->string('project')->nullable();
            $table->string('location');
            $table->unsignedInteger('required_experience')->default(0);
            $table->decimal('salary_budget_min', 12, 2);
            $table->decimal('salary_budget_max', 12, 2);
            $table->longText('description');
            $table->enum('approval_status', ['DRAFT', 'PENDING', 'APPROVED', 'CLOSED'])->default('DRAFT');
            $table->string('hiring_manager');
            $table->unsignedInteger('vacancies')->default(1);
            $table->timestamps();
        });

        Schema::create('job_required_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('name');
            $table->unique(['job_id', 'name']);
        });

        Schema::create('candidate_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->unsignedTinyInteger('overall');
            $table->unsignedTinyInteger('technical');
            $table->unsignedTinyInteger('experience');
            $table->unsignedTinyInteger('salary_fit');
            $table->unsignedTinyInteger('availability');
            $table->unsignedTinyInteger('risk');
            $table->unsignedTinyInteger('matching_percentage');
            $table->string('recommendation');
            $table->json('rationale')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_search_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('filters');
            $table->json('queries');
            $table->string('status')->default('QUEUED');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_search_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->text('source_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamps();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('SCHEDULED');
            $table->string('channel')->nullable();
            $table->timestamps();
        });

        Schema::create('interview_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('technical_score')->nullable();
            $table->unsignedTinyInteger('hr_score')->nullable();
            $table->string('recommendation')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('job_title');
            $table->string('location');
            $table->decimal('min_salary', 12, 2);
            $table->decimal('max_salary', 12, 2);
            $table->unsignedInteger('years_experience_min');
            $table->unsignedInteger('years_experience_max');
            $table->string('source');
            $table->timestamps();
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('direction');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('candidate_tag', function (Blueprint $table) {
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['candidate_id', 'tag_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity');
            $table->string('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->text('encrypted_value');
            $table->string('status')->default('ACTIVE');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (array_reverse([
            'api_keys', 'system_settings', 'audit_logs', 'candidate_tag', 'tags', 'notes',
            'communications', 'salary_benchmarks', 'interview_feedback', 'interviews',
            'ai_search_results', 'ai_search_jobs', 'candidate_scores', 'job_required_skills',
            'jobs', 'candidate_sources', 'candidate_documents', 'candidate_languages',
            'candidate_skills', 'candidates', 'users', 'roles',
        ]) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
