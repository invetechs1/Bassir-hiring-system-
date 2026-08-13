<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A reusable assessment definition (test / questionnaire / document check / background check).
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // TEST | QUESTIONNAIRE | DOCUMENT_VERIFICATION | BACKGROUND_CHECK
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('passing_score')->nullable(); // percentage, TEST/QUESTIONNAIRE only
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'type']);
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type')->default('MCQ'); // MCQ | TEXT
            $table->json('options')->nullable(); // MCQ choices
            $table->string('correct_answer')->nullable(); // MCQ only, auto-scored
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One assignment of an assessment to a specific candidate/application.
        Schema::create('candidate_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING'); // PENDING|IN_PROGRESS|SUBMITTED|SCORED|CLEARED|FLAGGED
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('max_score')->nullable();
            $table->text('notes')->nullable(); // manual reviewer notes (document/background checks)
            $table->string('document_path')->nullable(); // uploaded verification document
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'candidate_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('candidate_assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_assessment_answers');
        Schema::dropIfExists('candidate_assessments');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
    }
};
