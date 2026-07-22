<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('candidate_sources', function (Blueprint $table) {
            $table->index('source_type');
        });

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->index(['job_id', 'overall']);
        });

        Schema::table('ai_search_results', function (Blueprint $table) {
            $table->index('source');
            $table->index('candidate_id');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->index(['job_id', 'starts_at']);
            $table->index('status');
        });

        Schema::table('communications', function (Blueprint $table) {
            $table->index(['candidate_id', 'sent_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'entity']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role_id', 'is_active']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'entity']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('communications', function (Blueprint $table) {
            $table->dropIndex(['candidate_id', 'sent_at']);
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex(['job_id', 'starts_at']);
            $table->dropIndex(['status']);
        });

        Schema::table('ai_search_results', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['candidate_id']);
        });

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->dropIndex(['job_id', 'overall']);
        });

        Schema::table('candidate_sources', function (Blueprint $table) {
            $table->dropIndex(['source_type']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
