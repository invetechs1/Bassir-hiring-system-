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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        private readonly CvParserService $parser,
        private readonly DuplicateDetectionService $duplicates,
        private readonly FileSecurityService $fileSecurity,
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

            $this->importOne($search, $row, $url, $run, $actor);
        }
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
            $download = $this->downloadAndParse($url, $fileType);
            if ($download) {
                $p = $download['parsed'];
                $candidateData = array_merge($candidateData, array_filter([
                    'full_name' => $p['name'] ?: $candidateData['full_name'],
                    'email' => $p['email'] ?? null,
                    'phone' => $p['phone'] ?? null,
                    'current_company' => $p['current_company'] ?? null,
                    'industry' => $p['industry'] ?? null,
                    'city' => $p['city'] ?? $candidateData['city'],
                    'nationality' => $p['nationality'] ?? null,
                    'years_experience' => $p['years_experience'] ?? null,
                    'notice_period' => $p['notice_period'] ?? null,
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
     * Download a public CV file and parse it. Returns null on any failure so the
     * caller falls back to metadata-only import.
     *
     * @return array{path:string,file_name:string,mime_type:string,checksum:string,malware_scan_status:string,parsed:array<string,mixed>}|null
     */
    private function downloadAndParse(string $url, string $fileType): ?array
    {
        if (! preg_match('#^https?://#i', $url) || str_contains(strtolower($url), 'linkedin.com')) {
            return null;
        }

        try {
            $response = Http::timeout(25)->withOptions(['allow_redirects' => true])->get($url);
        } catch (Throwable) {
            return null;
        }
        if (! $response->ok()) {
            return null;
        }

        $body = $response->body();
        $maxBytes = ((int) config('bassir.max_upload_kb', 10240)) * 1024;
        if ($body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        $path = 'private/cvs/'.Str::uuid()->toString().'.'.$fileType;
        Storage::disk('local')->put($path, $body);
        $absolute = Storage::disk('local')->path($path);

        if ($this->fileSecurity->malwareScan($absolute) === 'FAILED') {
            Storage::disk('local')->delete($path);
            return null;
        }

        try {
            $parsed = $this->parser->parse($absolute);
        } catch (Throwable) {
            Storage::disk('local')->delete($path);
            return null;
        }

        return [
            'path' => $path,
            'file_name' => Str::limit(basename(parse_url($url, PHP_URL_PATH) ?: 'cv.'.$fileType), 180, ''),
            'mime_type' => $response->header('Content-Type') ?: 'application/octet-stream',
            'checksum' => hash('sha256', $body),
            'malware_scan_status' => 'NOT_CONFIGURED',
            'parsed' => $parsed,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nameFromRow(array $row): string
    {
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
