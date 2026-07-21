<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saved, reusable search profiles that the auto-sourcing engine runs on a schedule.
        Schema::create('sourcing_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('specialization')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->json('skills')->nullable();
            $table->json('software_skills')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedSmallInteger('quantity')->default(25);
            $table->json('providers')->nullable();          // null = every configured provider
            $table->boolean('download_cvs')->default(true);  // fetch & parse public CV files
            $table->boolean('auto_import')->default(true);   // create candidate leads automatically
            $table->string('default_consent_status')->default('PENDING');
            $table->string('frequency')->default('daily');   // daily | weekly | manual
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_result_count')->default(0);
            $table->unsignedInteger('last_import_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        // Per-execution audit of the auto-sourcing engine.
        Schema::create('sourcing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sourcing_search_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('RUNNING'); // RUNNING | SUCCESS | PARTIAL | FAILED
            $table->unsignedInteger('results_found')->default(0);
            $table->unsignedInteger('candidates_created')->default(0);
            $table->unsignedInteger('candidates_linked')->default(0);
            $table->unsignedInteger('cvs_downloaded')->default(0);
            $table->unsignedInteger('flagged_manual')->default(0);
            $table->text('message')->nullable();
            $table->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete(); // null = scheduler
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'sourcing_search_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_runs');
        Schema::dropIfExists('sourcing_searches');
    }
};
