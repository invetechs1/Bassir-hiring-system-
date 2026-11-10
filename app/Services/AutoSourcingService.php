<?php

namespace App\Services;

use App\Models\AiSearchJob;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Jobs\RunSourcingSearch;
use App\Models\SourcingRun;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\Connectors\PlatformConnectorRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Automated, compliant web-sourcing engine.
 *
 * Runs saved search profiles across the official search APIs and partner
 * connectors, then (optionally) downloads & parses genuinely public CV files
 * and creates candidate LEADS with consent = pending. It never scrapes and
 * never auto-contacts anyone. LinkedIn public-web hits are flagged for manual
 * handling; LinkedIn data only enters automatically via the official API
 * connector.
 */
class AutoSourcingService
{
    public function __construct(
        private readonly SearchProviderService $providers,
        private readonly PlatformConnectorRegistry $connectors,
        private readonly CvDocumentFetcher $fetcher,
        private readonly SpecialtyClassifierService $classifier,
        private readonly DuplicateDetectionService $duplicates,
        private readonly AuditService $audit,
    ) {
    }

    /** File extensions we will download & parse from the open web. */
    private const DOWNLOADABLE = ['pdf', 'doc', 'docx'];

    /**
     * Execute one saved search: gather results from every configured source,
     * then import them. Returns the persisted run record.
     */
    public function runSearch(SourcingSearch $search, ?User $actor = null): SourcingRun
    {
        $run = SourcingRun::create([
            'company_id' => $search->company_id,
            'sourcing_search_id' => $search->id,
            'status' => 'RUNNING',
            'ran_by' => $actor?->id,
            'started_at' => now(),
        ]);

        try {
            $filters = $search->toFilters();
            $results = array_merge(
                $this->providers->cvSourcing($filters),   // Google CSE / Bing / SerpAPI / agency feed
                $this->connectors->search($filters),        // official partner APIs (LinkedIn, Indeed, …)
            );

            $this->importResults($search, $results, $run, $actor);

            $run->forceFill([
                'status' => 'SUCCESS',
                'results_found' => count($results),
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => 'FAILED',
                'message' => Str::limit($e->getMessage(), 480),
                'finished_at' => now(),
            ])->save();
        }

        $search->forceFill([
            'last_run_at' => now(),
            'last_result_count' => $run->results_found,
            'last_import_count' => $run->candidates_created,
        ])->save();

        $this->audit->log($actor?->id, 'AUTO_SOURCING_RUN', 'sourcing_searches', (string) $search->id, [
            'run_id' => $run->id,
            'status' => $run->status,
            'results' => $run->results_found,
            'created' => $run->candidates_created,
            'linked' => $run->candidates_linked,
            'downloaded' => $run->cvs_downloaded,
            'flagged_manual' => $run->flagged_manual,
        ]);

        return $run;
    }

    /**
     * Queue every active saved search (used by the scheduled command). Each
     * search is dispatched independently so a large batch never runs as one long
     * process; under the sync driver they execute inline. Returns the count queued.
     */
    public function runDueSearches(?int $companyId = null): int
    {
        $ids = SourcingSearch::query()
            ->where('is_active', true)
            ->where('frequency', '!=', 'manual')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->pluck('id');

        foreach ($ids as $id) {
            RunSourcingSearch::dispatch($id);
        }

        return $ids->count();
    }

    /**
     * Import a batch of normalized result rows against a saved search.
     * Pure of network I/O except the optional CV download — safe to unit test
     * with canned results.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    public function importResults(SourcingSearch $search, array $results, SourcingRun $run, ?User $actor = null): void
    {
        $job = AiSearchJob::create([
            'company_id' => $search->company_id,
            'created_by' => $actor?->id,
            'filters' => $search->toFilters() + ['mode' => 'AUTO_SOURCING', 'sourcing_search_id' => $search->id],
            'queries' => $this->providers->buildQueries($search->toFilters()),
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $seen = [];
        foreach ($results as $row) {
            $url = (string) ($row['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $key = $this->urlKey($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // Persist the raw result so it also appears in the AI Search history for manual review.
            $job->results()->create([
                'source' => $row['source'] ?? 'Auto Sourcing',
                'source_url' => $url,
                'raw_payload' => $row,
            ]);

            // LinkedIn public-web hits are import-by-human only: flag, never auto-create or scrape.
            if (($row['compliance_status'] ?? 'allowed') === 'manual_only') {
                $run->increment('flagged_manual');
                continue;
            }

            if (! $search->auto_import) {
                continue;
            }

            // Never turn a job advert, template or non-CV page into a "candidate". Only results that look
            // like a person's CV/profile are imported automatically; the rest stay in the search history
            // for a human to look at.
            if (! $this->worthImporting($row)) {
                continue;
            }

            $this->importOne($search, $row, $url, $run, $actor);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function worthImporting(array $row): bool
    {
        $quality = $row['match_quality'] ?? null;
        if (in_array($quality, ['likely_job_posting', 'likely_other'], true)) {
            return false;
        }
        // Unsure results must at least match the request reasonably well (results from connectors that
        // do not score are given the benefit of the doubt).
        if ($quality === 'uncertain' && isset($row['relevance_score'])) {
            return (int) $row['relevance_score'] >= 50;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importOne(SourcingSearch $search, array $row, string $url, SourcingRun $run, ?User $actor): void
    {
        $consent = in_array($search->default_consent_status, ['CONSENTED', 'PENDING', 'WITHDRAWN'], true)
            ? $search->default_consent_status
            : 'PENDING';

        // Base candidate data from the search metadata.
        $candidateData = [
            'company_id' => $search->company_id,
            'full_name' => $this->nameFromRow($row),
            'linkedin_url' => str_contains(strtolower($url), 'linkedin.com') ? $url : null,
            'title' => Str::limit((string) ($row['title'] ?? 'Sourced Candidate'), 118, ''),
            'specialization' => $search->specialization ?: 'Unclassified',
            'country' => $search->country,
            'city' => $search->city,
            'ai_summary' => Str::limit((string) ($row['snippet'] ?? ''), 890, ''),
            'consent_status' => $consent,
            'status' => 'NEW',
        ];

        // Optionally enrich by downloading & parsing a genuinely public CV file.
        $download = null;
        $fileType = strtolower((string) ($row['file_type'] ?? 'profile'));
        if ($search->download_cvs && in_array($fileType, self::DOWNLOADABLE, true)) {
            $download = $this->fetcher->fetch($url, $fileType, 'private/cvs');
            if ($download) {
                $p = $download['parsed'];
                $classification = $this->classifier->classify($p);
                // Content wins over the saved search's specialty when the CV clearly says something else.
                if ($classification['confidence'] >= 4) {
                    $candidateData['specialization'] = $classification['name'];
                }
                $download = $this->fetcher->relocate($download, $this->classifier->slugOf($candidateData['specialization']));
                $candidateData = array_merge($candidateData, array_filter([
                    'full_name' => $p['name'] ?: $candidateData['full_name'],
                    'title' => $p['current_job_title'] ?? null,
                    'email' => $p['email'] ?? null,
                    'phone' => $p['phone'] ?? null,
                    'current_company' => $p['current_company'] ?? null,
                    'industry' => $p['industry'] ?? null,
                    'country' => $p['country'] ?? $candidateData['country'],
                    'city' => $p['city'] ?? $candidateData['city'],
                    'nationality' => $p['nationality'] ?? null,
                    'years_experience' => $p['years_experience'] ?? null,
                    'expected_salary' => $p['expected_salary'] ?? null,
                    'notice_period' => $p['notice_period'] ?? null,
                    'cv_text' => isset($p['raw_text']) ? mb_substr($p['raw_text'], 0, 100000) : null,
                ], fn ($v) => $v !== null && $v !== ''));
                $candidateData['parsed_profile'] = [
                    'skills' => $p['skills'] ?? [],
                    'languages' => $p['languages'] ?? [],
                    'previous_companies' => $p['previous_companies'] ?? [],
                    'source' => 'auto_sourcing_download',
                ];
            }
        }

        if ($consent === 'CONSENTED') {
            $candidateData['consent_captured_at'] = now()->toDateString();
            $candidateData['consent_captured_by'] = $actor?->id;
            $candidateData['contact_allowed'] = true;
        }
        $candidateData['duplicate_hash'] = $this->duplicates->hash($candidateData);

        $existing = Candidate::query()
            ->where('company_id', $search->company_id)
            ->where(function ($q) use ($candidateData) {
                $q->where('duplicate_hash', $candidateData['duplicate_hash'])
                    ->when($candidateData['linkedin_url'] ?? null, fn ($inner) => $inner->orWhere('linkedin_url', $candidateData['linkedin_url']))
                    ->when($candidateData['email'] ?? null, fn ($inner) => $inner->orWhere('email', $candidateData['email']));
            })
            ->first();

        $candidate = DB::transaction(function () use ($existing, $candidateData, $row, $url, $consent, $actor, $download) {
            $candidate = $existing ?: Candidate::create($candidateData);

            $candidate->sources()->firstOrCreate([
                'source_type' => $row['source'] ?? 'Auto Sourcing',
                'source_url' => $url,
            ], [
                'consent_note' => $consent === 'CONSENTED'
                    ? 'Consent recorded during automated sourcing import.'
                    : 'Consent pending. Do not contact until consent is captured.',
                'consent_captured_at' => $consent === 'CONSENTED' ? now() : null,
                'consent_captured_by' => $consent === 'CONSENTED' ? $actor?->id : null,
                'contact_allowed' => $consent === 'CONSENTED',
            ]);

            if ($download) {
                foreach ($download['parsed']['skills'] ?? [] as $skill) {
                    $candidate->skills()->firstOrCreate(['name' => $skill]);
                }
                foreach ($download['parsed']['languages'] ?? [] as $language) {
                    $candidate->languages()->firstOrCreate(['name' => $language]);
                }
                foreach ($download['parsed']['experience_entries'] ?? [] as $entry) {
                    $candidate->experience()->firstOrCreate(['title' => $entry['title'] ?? '', 'company' => $entry['company'] ?? ''], $entry);
                }
                foreach ($download['parsed']['education_entries'] ?? [] as $entry) {
                    $candidate->education()->firstOrCreate(['institution' => $entry['institution'] ?? '', 'degree' => $entry['degree'] ?? ''], $entry);
                }
                $candidate->documents()->create([
                    'file_name' => $download['file_name'],
                    'mime_type' => $download['mime_type'],
                    'storage_path' => $download['path'],
                    'checksum' => $download['checksum'],
                    'scan_status' => 'AUTO_SOURCED',
                    'malware_scan_status' => $download['malware_scan_status'],
                ]);
            }

            return $candidate;
        });

        if ($existing) {
            $run->increment('candidates_linked');
        } else {
            $run->increment('candidates_created');
        }
        if ($download) {
            $run->increment('cvs_downloaded');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nameFromRow(array $row): string
    {
        if (! empty($row['candidate_name'])) {
            return Str::limit((string) $row['candidate_name'], 118, '');
        }
        $title = trim((string) ($row['title'] ?? ''));
        // A search title is often "Ahmed Ali — Senior BIM Engineer | CV". Take the leading name-ish part.
        $lead = preg_split('/[|\x{2013}\x{2014}\-–—:,]/u', $title)[0] ?? $title;
        $lead = trim(preg_replace('/\b(cv|resume|curriculum vitae|pdf|docx?)\b/i', '', (string) $lead));

        return $lead !== '' ? Str::limit($lead, 118, '') : 'Sourced Candidate';
    }

    private function urlKey(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return strtolower(trim($url));
        }

        return strtolower(($parts['host'] ?? '').($parts['path'] ?? ''));
    }
}
