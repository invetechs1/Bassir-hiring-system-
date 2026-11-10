<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Services\CvParserService;
use App\Services\SkillCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CvReindexCommand extends Command
{
    protected $signature = 'bassir:cv-reindex
        {--company= : Limit to a company id}
        {--force : Re-extract text even for candidates that already have it}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Re-read every stored CV file and save its full text + recognised skills so candidate search can find them. Run once after deploying full-text CV search.';

    public function handle(CvParserService $parser, SkillCatalog $skills): int
    {
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $indexed = $skipped = $unreadable = $skillsAdded = 0;

        $query = Candidate::query()->with(['documents' => fn ($q) => $q->latest()]);
        if ($companyId = $this->option('company')) {
            $query->where('company_id', (int) $companyId);
        }

        $query->chunkById(100, function ($candidates) use ($parser, $skills, $dry, $force, &$indexed, &$skipped, &$unreadable, &$skillsAdded) {
            foreach ($candidates as $candidate) {
                if (! $force && ! empty($candidate->cv_text)) {
                    $skipped++;

                    continue;
                }
                $document = $candidate->documents->first();
                if (! $document || ! Storage::disk('local')->exists($document->storage_path)) {
                    $unreadable++;

                    continue;
                }

                try {
                    $text = trim($parser->extractText(Storage::disk('local')->path($document->storage_path)));
                } catch (Throwable $e) {
                    $this->warn("#{$candidate->id} {$candidate->full_name}: {$e->getMessage()}");
                    $unreadable++;

                    continue;
                }
                if ($text === '') {
                    $this->line("#{$candidate->id} {$candidate->full_name}: no readable text (scanned image? enable OCR).");
                    $unreadable++;

                    continue;
                }

                $found = $skills->extract($text);
                $this->line("#{$candidate->id} {$candidate->full_name}: ".mb_strlen($text).' chars, '.count($found).' skill(s)');
                if (! $dry) {
                    $candidate->forceFill(['cv_text' => mb_substr($text, 0, 100000)])->save();
                    foreach ($found as $skill) {
                        if ($candidate->skills()->firstOrCreate(['name' => $skill])->wasRecentlyCreated) {
                            $skillsAdded++;
                        }
                    }
                }
                $indexed++;
            }
        });

        $this->info(($dry ? '[dry run] ' : '')."Indexed {$indexed} CV(s); {$skillsAdded} skill(s) added; {$skipped} already indexed; {$unreadable} without a readable file.");

        return self::SUCCESS;
    }
}
