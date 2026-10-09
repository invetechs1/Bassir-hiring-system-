<?php

namespace App\Console\Commands;

use App\Models\SourcingAgent;
use App\Services\SourcingAgentService;
use Illuminate\Console\Command;

class RunSourcingAgentsCommand extends Command
{
    protected $signature = 'bassir:agents-run
        {--agent= : Run only this agent id}
        {--company= : Limit to a company id}
        {--force : Ignore the next_run_at schedule and run every active agent}';

    protected $description = 'Run every due sourcing agent (they behave like senior HR employees on a schedule).';

    public function handle(SourcingAgentService $service): int
    {
        $query = SourcingAgent::query()->where('is_active', true);
        if ($agentId = $this->option('agent')) {
            $query->whereKey((int) $agentId);
        }
        if ($companyId = $this->option('company')) {
            $query->where('company_id', (int) $companyId);
        }

        $ran = 0;
        foreach ($query->get() as $agent) {
            if (! $this->option('force') && ! $agent->isDue()) {
                continue;
            }
            $run = $service->runAgent($agent);
            $this->info("[{$agent->id}] {$agent->name}: {$run->status} — {$run->candidates_added} pick(s) from {$run->results_scanned} scanned.");
            $ran++;
        }

        $this->info("Ran {$ran} agent(s).");

        return self::SUCCESS;
    }
}
