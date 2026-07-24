<?php

namespace App\Jobs;

use App\Models\SourcingSearch;
use App\Services\AutoSourcingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executes one saved auto-sourcing search off the web/cron request. Under the
 * sync driver it runs inline; with a real queue each scheduled search is
 * processed independently so a large batch never runs as one long process.
 */
class RunSourcingSearch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $searchId)
    {
    }

    public function handle(AutoSourcingService $engine): void
    {
        $search = SourcingSearch::find($this->searchId);
        if (! $search || ! $search->is_active) {
            return;
        }

        $engine->runSearch($search);
    }
}
