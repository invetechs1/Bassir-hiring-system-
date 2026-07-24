<?php

namespace App\Jobs;

use App\Models\Job as JobRequisition;
use App\Services\AiCandidateRankingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Rebuilds AI candidate ranking for a job requisition off the web request.
 * Runs inline under the sync driver (shared hosting) and in the background when
 * a real queue is configured, so ranking large candidate pools never blocks or
 * times out a page load.
 */
class RankJobCandidates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $jobId)
    {
    }

    public function handle(AiCandidateRankingService $ranking): void
    {
        $job = JobRequisition::find($this->jobId);
        if (! $job) {
            return; // requisition was removed before processing
        }

        try {
            $ranking->rankJob($job);
        } catch (Throwable) {
            // Ranking is an accelerator; failures must not break the pipeline.
        }
    }
}
