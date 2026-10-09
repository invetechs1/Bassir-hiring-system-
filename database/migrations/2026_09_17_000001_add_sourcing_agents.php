<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persistent "senior HR employee" agents — each targets one specialty with
        // a fixed persona, requirements, and schedule, then hands searches to the
        // compliant AutoSourcingService pipeline (Google/Bing/SerpAPI + partners).
        Schema::create('sourcing_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('persona')->default('senior_technical');
            // senior_technical | campus_recruiter | executive_headhunter | volume_recruiter
            $table->string('avatar_emoji', 8)->default('🧑‍💼');
            $table->text('bio')->nullable();
            $table->string('specialty_slug');
            $table->string('specialty_name');
            $table->json('countries')->nullable();
            $table->json('cities')->nullable();
            $table->json('must_have_skills')->nullable();
            $table->json('nice_to_have_skills')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedSmallInteger('min_years')->default(0);
            $table->unsignedSmallInteger('max_years')->default(40);
            $table->unsignedTinyInteger('min_score')->default(40); // 0-100
            $table->unsignedSmallInteger('quantity_per_run')->default(25);
            $table->json('providers')->nullable();
            $table->string('frequency')->default('daily'); // hourly | daily | weekly | manual
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('candidates_added')->default(0);
            $table->unsignedTinyInteger('avg_score')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'specialty_slug']);
        });

        Schema::create('sourcing_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sourcing_agent_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('RUNNING'); // RUNNING | SUCCESS | PARTIAL | FAILED
            $table->unsignedInteger('results_scanned')->default(0);
            $table->unsignedInteger('candidates_added')->default(0);
            $table->unsignedInteger('candidates_skipped_low_score')->default(0);
            $table->unsignedInteger('candidates_skipped_wrong_specialty')->default(0);
            $table->unsignedInteger('candidates_duplicate')->default(0);
            $table->unsignedTinyInteger('avg_score')->default(0);
            $table->text('message')->nullable();
            $table->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'sourcing_agent_id']);
        });

        // Every candidate an agent has successfully imported, with the score
        // at time of import so the recruiter can see the agent's picks.
        Schema::create('sourcing_agent_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sourcing_agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0); // 0-100
            $table->json('score_reasons')->nullable();
            $table->timestamps();
            $table->unique(['sourcing_agent_id', 'candidate_id']);
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_agent_candidates');
        Schema::dropIfExists('sourcing_agent_runs');
        Schema::dropIfExists('sourcing_agents');
    }
};
