<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep only the newest score row per candidate/job before adding unique constraint.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                DELETE FROM candidate_scores
                WHERE job_id IS NOT NULL
                  AND EXISTS (
                    SELECT 1
                    FROM candidate_scores newer
                    WHERE newer.candidate_id = candidate_scores.candidate_id
                      AND newer.job_id = candidate_scores.job_id
                      AND newer.id > candidate_scores.id
                      AND newer.job_id IS NOT NULL
                  )
            ');
        } else {
            DB::statement('
                DELETE cs_old
                FROM candidate_scores cs_old
                INNER JOIN candidate_scores cs_new
                    ON cs_old.candidate_id = cs_new.candidate_id
                   AND cs_old.job_id = cs_new.job_id
                   AND cs_old.id < cs_new.id
                WHERE cs_old.job_id IS NOT NULL
                  AND cs_new.job_id IS NOT NULL
            ');
        }

        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->unique(['candidate_id', 'job_id'], 'candidate_scores_candidate_job_unique');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_scores', function (Blueprint $table) {
            $table->dropUnique('candidate_scores_candidate_job_unique');
        });
    }
};
