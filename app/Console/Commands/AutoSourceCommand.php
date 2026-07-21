<?php

namespace App\Console\Commands;

use App\Models\SourcingSearch;
use App\Services\AutoSourcingService;
use Illuminate\Console\Command;

class AutoSourceCommand extends Command
{
    protected $signature = 'bassir:auto-source
        {--search= : Run only this saved-search id}
        {--company= : Limit to a company id}';

    protected $description = 'Run automated compliant candidate sourcing across configured search APIs and partner connectors.';

    public function handle(AutoSourcingService $engine): int
    {
        if ($searchId = $this->option('search')) {
            $search = SourcingSearch::find($searchId);
            if (! $search) {
                $this->error("Sourcing search {$searchId} not found.");

                return self::FAILURE;
            }
            $run = $engine->runSearch($search);
            $this->info("Ran '{$search->name}': {$run->results_found} results, {$run->candidates_created} created, {$run->candidates_linked} linked, {$run->cvs_downloaded} CVs, {$run->flagged_manual} flagged for manual review.");

            return self::SUCCESS;
        }

        $company = $this->option('company') ? (int) $this->option('company') : null;
        $count = $engine->runDueSearches($company);
        $this->info("Completed {$count} active sourcing search(es).");

        return self::SUCCESS;
    }
}
