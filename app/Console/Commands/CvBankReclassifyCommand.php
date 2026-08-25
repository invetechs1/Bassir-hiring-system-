<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Services\SpecialtyClassifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CvBankReclassifyCommand extends Command
{
    protected $signature = 'bassir:cv-bank-reclassify
        {--company= : Limit to a company id}
        {--dry-run : Show planned reclassifications without writing}';

    protected $description = 'Re-run the specialty classifier over every candidate and move their CVs into the specialty folders.';

    public function handle(SpecialtyClassifierService $classifier): int
    {
        $query = Candidate::query()->with(['skills', 'documents' => fn ($q) => $q->latest()]);
        if ($companyId = $this->option('company')) {
            $query->where('company_id', (int) $companyId);
        }

        $moved = $updated = 0;
        $dry = (bool) $this->option('dry-run');

        $query->chunkById(200, function ($candidates) use (&$moved, &$updated, $classifier, $dry) {
            foreach ($candidates as $candidate) {
                $result = $classifier->classify([
                    'title' => $candidate->title,
                    'summary' => $candidate->ai_summary,
                    'skills' => $candidate->skills->pluck('name')->all(),
                    'raw_text' => is_array($candidate->parsed_profile ?? null) ? ($candidate->parsed_profile['summary'] ?? '') : '',
                ]);

                if ($candidate->specialization !== $result['name']) {
                    $this->line("#{$candidate->id} {$candidate->full_name}: {$candidate->specialization} → {$result['name']} ({$result['confidence']})");
                    if (! $dry) {
                        $candidate->update(['specialization' => $result['name']]);
                        $updated++;
                    }
                }

                foreach ($candidate->documents as $doc) {
                    $current = (string) $doc->storage_path;
                    if ($current === '') {
                        continue;
                    }
                    $target = 'private/cv-bank/'.$result['slug'].'/'.basename($current);
                    if ($current === $target) {
                        continue;
                    }
                    if ($dry) {
                        $this->line("  would move: {$current} → {$target}");

                        continue;
                    }
                    try {
                        if (! Storage::disk('local')->exists($current)) {
                            continue;
                        }
                        Storage::disk('local')->makeDirectory('private/cv-bank/'.$result['slug']);
                        Storage::disk('local')->move($current, $target);
                        $doc->update(['storage_path' => $target]);
                        $moved++;
                    } catch (Throwable $e) {
                        $this->warn("  move failed for doc #{$doc->id}: ".$e->getMessage());
                    }
                }
            }
        });

        $this->info("Reclassified {$updated} candidate(s); moved {$moved} CV file(s).");

        return self::SUCCESS;
    }
}
