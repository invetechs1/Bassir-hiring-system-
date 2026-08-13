<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->decimal('salary_amount', 12, 2);
            $table->string('currency', 8)->default('SAR');
            $table->date('start_date')->nullable();
            $table->string('employment_type')->nullable();
            $table->text('terms')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT|PENDING_APPROVAL|APPROVED|SENT|ACCEPTED|DECLINED|WITHDRAWN
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('onboarding_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING'); // PENDING|IN_PROGRESS|COMPLETED
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_record_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('TASK'); // DOCUMENT_UPLOAD|ACKNOWLEDGEMENT|TASK
            $table->string('status')->default('PENDING'); // PENDING|COMPLETED
            $table->string('document_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_records');
        Schema::dropIfExists('offers');
    }
};
