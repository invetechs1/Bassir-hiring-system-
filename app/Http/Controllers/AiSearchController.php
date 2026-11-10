<?php

namespace App\Http\Controllers;

use App\Models\AiSearchJob;
use App\Models\AiSearchResult;
use App\Models\Candidate;
use App\Services\AiInsightsService;
use App\Services\AuditService;
use App\Services\CvDocumentFetcher;
use App\Services\DuplicateDetectionService;
use App\Services\SearchProviderService;
use App\Services\SpecialtyClassifierService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AiSearchController extends Controller
{
    public function index(TenantService $tenant, SearchProviderService $search): View
    {
        return view('ai-search.index', [
            'history' => $tenant->scope(AiSearchJob::query(), Auth::user())->latest()->take(10)->get(),
            'providers' => $search->configuredProviders(),
        ]);
    }

    /**
     * Show a past search's results again. Reached via the "Search History" list or by
     * the browser Back button after an import — results are read back from what was
     * persisted at search time, so this is a plain GET and never resubmits the search.
     */
    public function show(Request $request, AiSearchJob $searchJob, TenantService $tenant, SearchProviderService $search): View
    {
        if (! Auth::user()?->isSuperAdmin() && $searchJob->company_id !== Auth::user()?->company_id) {
            abort(404);
        }
        $searchJob->load('results');

        $all = $searchJob->results->map(fn ($row) => $row->raw_payload ?? [])->all();
        // Job postings, templates and social pages are hidden by default — they were what made results look "wrong".
        $showAll = $request->boolean('all');
        $results = $showAll ? $all : array_values(array_filter($all, fn ($row) => ! in_array($row['match_quality'] ?? 'uncertain', ['likely_job_posting', 'likely_other'], true)));

        return view('ai-search.index', [
            'history' => $tenant->scope(AiSearchJob::query(), Auth::user())->latest()->take(10)->get(),
            'queries' => $searchJob->queries ?? [],
            'results' => $results,
            'hiddenCount' => count($all) - count($results),
            'showAll' => $showAll,
            'searchJob' => $searchJob,
            'filters' => $searchJob->filters ?? [],
            'providers' => $search->configuredProviders(),
        ]);
    }

    public function cvSourcing(Request $request, SearchProviderService $search, TenantService $tenant): RedirectResponse
    {
        $data = $request->validate([
            'job_title' => ['nullable', 'string', 'max:160'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'skills' => ['nullable', 'string', 'max:1200'],
            'software_skills' => ['nullable', 'string', 'max:1200'],
            'languages' => ['nullable', 'string', 'max:500'],
        ]);
        $filters = [
            'job_title' => $data['job_title'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'quantity' => (int) ($data['quantity'] ?? 25),
            'skills' => $this->split($data['skills'] ?? ''),
            'software_skills' => $this->split($data['software_skills'] ?? ''),
            'languages' => $this->split($data['languages'] ?? ''),
        ];
        if (trim((string) $filters['job_title']) === '' && trim((string) $filters['specialization']) === '' && $filters['skills'] === [] && $filters['software_skills'] === []) {
            return back()->withInput()->withErrors(['job_title' => 'Enter at least a job title, a specialization, or a skill so there is something to search for.']);
        }
        $queries = $search->buildQueries($filters);
        $results = $search->cvSourcing($filters);
        $job = DB::transaction(function () use ($filters, $queries, $results, $tenant) {
            $job = AiSearchJob::create([
                'company_id' => $tenant->defaultCompanyId(Auth::user()),
                'created_by' => Auth::id(),
                'filters' => $filters + ['mode' => 'AI_CV_SOURCING'],
                'queries' => $queries,
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);
            foreach ($results as $result) {
                $job->results()->create([
                    'source' => $result['source'],
                    'source_url' => $result['url'],
                    'raw_payload' => $result,
                ]);
            }

            return $job;
        });

        return redirect()->route('ai-search.show', $job);
    }

    public function importResult(
        Request $request,
        DuplicateDetectionService $duplicates,
        AiInsightsService $insights,
        AuditService $audit,
        TenantService $tenant,
        CvDocumentFetcher $fetcher,
        SpecialtyClassifierService $classifier,
        SearchProviderService $search
    ): RedirectResponse {
        $data = $request->validate([
            'result_id' => ['required', 'exists:ai_search_results,id'],
            'consent_status' => ['required', 'in:CONSENTED,PENDING,WITHDRAWN'],
            'specialization' => ['nullable', 'string', 'max:120'],
        ]);

        $result = AiSearchResult::with('job')->findOrFail($data['result_id']);
        if (! Auth::user()?->isSuperAdmin() && $result->job?->company_id !== Auth::user()?->company_id) {
            abort(404);
        }
        $payload = $result->raw_payload ?? [];
        $sourceUrl = (string) ($payload['url'] ?? $result->source_url ?? '');
        $jobFilters = $result->job?->filters ?? [];

        // If the source is a real downloadable CV file (not a landing page), fetch and
        // parse it now so the import carries actual contact/skills data, not just the
        // search snippet. Best-effort: import must still succeed if this fails.
        $download = null;
        $fileType = strtolower((string) ($payload['file_type'] ?? 'profile'));
        if (in_array($fileType, ['pdf', 'doc', 'docx'], true)) {
            $download = $fetcher->fetch($sourceUrl, $fileType);
        }
        $parsed = $download['parsed'] ?? null;

        // Specialty: an explicit choice by the recruiter wins; otherwise classify what we actually know
        // (the whole CV when it could be downloaded, else the result title/snippet); otherwise fall back to
        // the specialty that was searched for.
        $chosen = trim((string) ($data['specialization'] ?? ''));
        $classification = $classifier->classify($parsed ?? [
            'title' => $payload['title'] ?? '',
            'summary' => $payload['snippet'] ?? '',
        ]);
        if ($chosen !== '' && strcasecmp($chosen, SpecialtyClassifierService::UNCLASSIFIED_NAME) !== 0) {
            $specialization = $chosen;
        } elseif ($classification['confidence'] >= 4) {
            $specialization = $classification['name'];
        } else {
            $specialization = trim((string) ($jobFilters['specialization'] ?? '')) ?: SpecialtyClassifierService::UNCLASSIFIED_NAME;
        }
        if ($download) {
            $download = $fetcher->relocate($download, $classifier->slugOf($specialization));
        }

        $candidateData = [
            'full_name' => $this->nameFromPayload($payload, $search),
            'email' => null,
            'phone' => null,
            'linkedin_url' => str_contains(strtolower($sourceUrl), 'linkedin.com') ? $sourceUrl : null,
            'title' => mb_substr((string) ($payload['title'] ?? 'Candidate Profile'), 0, 120),
            'specialization' => $specialization,
            'country' => $jobFilters['country'] ?? null,
            'city' => $jobFilters['city'] ?? null,
            'ai_summary' => mb_substr((string) ($payload['snippet'] ?? ''), 0, 900),
            'consent_status' => $data['consent_status'],
            'status' => 'NEW',
            'company_id' => $result->job?->company_id ?: $tenant->defaultCompanyId(Auth::user()),
        ];
        if ($parsed) {
            $candidateData = array_merge($candidateData, array_filter([
                'full_name' => $parsed['name'] ?: $candidateData['full_name'],
                'title' => $parsed['current_job_title'] ?? null,
                'email' => $parsed['email'] ?? null,
                'phone' => $parsed['phone'] ?? null,
                'current_company' => $parsed['current_company'] ?? null,
                'industry' => $parsed['industry'] ?? null,
                'country' => $parsed['country'] ?? $candidateData['country'],
                'city' => $parsed['city'] ?? $candidateData['city'],
                'nationality' => $parsed['nationality'] ?? null,
                'years_experience' => $parsed['years_experience'] ?? null,
                'expected_salary' => $parsed['expected_salary'] ?? null,
                'notice_period' => $parsed['notice_period'] ?? null,
                'ai_summary' => isset($parsed['raw_text']) ? mb_substr($parsed['raw_text'], 0, 1200) : null,
                'cv_text' => isset($parsed['raw_text']) ? mb_substr($parsed['raw_text'], 0, 100000) : null,
            ], fn ($v) => $v !== null && $v !== ''));
            $candidateData['parsed_profile'] = [
                'skills' => $parsed['skills'] ?? [],
                'languages' => $parsed['languages'] ?? [],
                'previous_companies' => $parsed['previous_companies'] ?? [],
                'source' => 'ai_search_download',
            ];
        }
        if ($candidateData['consent_status'] === 'CONSENTED') {
            $candidateData['consent_captured_at'] = now()->toDateString();
            $candidateData['consent_captured_by'] = Auth::id();
            $candidateData['contact_allowed'] = true;
        }
        $candidateData['duplicate_hash'] = $duplicates->hash($candidateData);

        $existing = $tenant->scope(Candidate::query(), Auth::user())
            ->where(function ($query) use ($candidateData) {
                $query->where('duplicate_hash', $candidateData['duplicate_hash'])
                    ->when($candidateData['linkedin_url'], fn ($inner) => $inner->orWhere('linkedin_url', $candidateData['linkedin_url']))
                    ->when($candidateData['email'] ?? null, fn ($inner) => $inner->orWhere('email', $candidateData['email']));
            })
            ->first();

        $candidate = DB::transaction(function () use ($existing, $candidateData, $result, $sourceUrl, $data, $download) {
            $candidate = $existing ?: Candidate::create($candidateData);
            if ($existing && empty($existing->cv_text) && ! empty($candidateData['cv_text'])) {
                $existing->update(['cv_text' => $candidateData['cv_text']]);
            }
            $candidate->sources()->firstOrCreate([
                'source_type' => $result->source,
                'source_url' => $sourceUrl,
            ], [
                'consent_note' => $data['consent_status'] === 'CONSENTED'
                    ? 'Consent recorded by HR during AI sourcing import.'
                    : 'Consent pending. Do not contact until consent is captured.',
                'consent_captured_at' => $data['consent_status'] === 'CONSENTED' ? now() : null,
                'consent_captured_by' => $data['consent_status'] === 'CONSENTED' ? Auth::id() : null,
                'contact_allowed' => $data['consent_status'] === 'CONSENTED',
            ]);

            if ($download) {
                $parsed = $download['parsed'];
                foreach ($parsed['skills'] ?? [] as $skill) {
                    $candidate->skills()->firstOrCreate(['name' => $skill]);
                }
                foreach ($parsed['languages'] ?? [] as $language) {
                    $candidate->languages()->firstOrCreate(['name' => $language]);
                }
                foreach ($parsed['experience_entries'] ?? [] as $entry) {
                    $candidate->experience()->firstOrCreate(['title' => $entry['title'] ?? '', 'company' => $entry['company'] ?? ''], $entry);
                }
                foreach ($parsed['education_entries'] ?? [] as $entry) {
                    $candidate->education()->firstOrCreate(['institution' => $entry['institution'] ?? '', 'degree' => $entry['degree'] ?? ''], $entry);
                }
                foreach ($parsed['certification_entries'] ?? [] as $entry) {
                    $candidate->certifications()->firstOrCreate(['name' => $entry['name'] ?? '', 'issuer' => $entry['issuer'] ?? ''], $entry);
                }
                $candidate->documents()->create([
                    'file_name' => $download['file_name'],
                    'mime_type' => $download['mime_type'],
                    'storage_path' => $download['path'],
                    'checksum' => $download['checksum'],
                    'scan_status' => 'COMPLETED',
                    'malware_scan_status' => $download['malware_scan_status'],
                ]);
            }

            return $candidate;
        });

        try {
            $ai = $insights->candidateInsight([
                'full_name' => $candidate->full_name,
                'title' => $candidate->title,
                'specialization' => $candidate->specialization,
                'skills' => $candidate->skills()->pluck('name')->all(),
                'years_experience' => $candidate->years_experience,
                'expected_salary' => $candidate->expected_salary,
                'location' => trim(($candidate->city ?? '').' '.($candidate->country ?? '')),
            ]);
            $candidate->update(['ai_summary' => $ai['summary']]);
        } catch (Throwable) {
            // AI enrichment is a bonus on top of the candidate record already saved above;
            // the import must still succeed if the AI provider misbehaves.
        }

        DB::transaction(function () use ($result, $candidate) {
            $result->update(['candidate_id' => $candidate->id]);
        });
        $audit->log(Auth::id(), 'AI_SEARCH_RESULT_IMPORT', 'ai_search_results', (string) $result->id, [
            'candidate_id' => $candidate->id,
            'source' => $result->source,
            'consent_status' => $data['consent_status'],
            'cv_downloaded' => $download !== null,
        ], $request);

        $message = $existing ? 'Existing candidate linked to AI search result' : 'AI search result imported to candidate database';
        if (in_array($fileType, ['pdf', 'doc', 'docx'], true) && ! $download) {
            $message .= '. The CV file could not be downloaded automatically (the source may block downloads), so only the search snippet was saved; open the source link and upload the CV file to complete the profile.';
        }

        return redirect()->route('candidates.show', $candidate)->with('status', $message);
    }

    public function importLinkedinManual(
        Request $request,
        DuplicateDetectionService $duplicates,
        AuditService $audit,
        TenantService $tenant
    ): RedirectResponse {
        $data = $request->validate([
            'linkedin_url' => ['required', 'url', 'regex:/linkedin\.com/i'],
            'full_name' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:120'],
            'specialization' => ['required', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'consent_status' => ['required', 'in:CONSENTED,PENDING,WITHDRAWN'],
        ]);

        $candidateData = [
            'full_name' => $data['full_name'],
            'linkedin_url' => $data['linkedin_url'],
            'title' => $data['title'],
            'specialization' => $data['specialization'],
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'consent_status' => $data['consent_status'],
            'status' => 'NEW',
            'ai_summary' => 'LinkedIn profile imported manually. No scraping performed.',
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
        ];
        if ($candidateData['consent_status'] === 'CONSENTED') {
            $candidateData['consent_captured_at'] = now()->toDateString();
            $candidateData['consent_captured_by'] = Auth::id();
            $candidateData['contact_allowed'] = true;
        }
        $candidateData['duplicate_hash'] = $duplicates->hash($candidateData);

        $existing = $tenant->scope(Candidate::query(), Auth::user())
            ->where(function ($query) use ($candidateData) {
                $query->where('linkedin_url', $candidateData['linkedin_url'])
                    ->orWhere('duplicate_hash', $candidateData['duplicate_hash']);
            })
            ->first();
        if ($existing) {
            return redirect()->route('candidates.show', $existing)->with('status', 'LinkedIn profile already exists and was opened.');
        }

        $candidate = DB::transaction(function () use ($candidateData, $data) {
            $candidate = Candidate::create($candidateData);
            $candidate->sources()->create([
                'source_type' => 'LinkedIn Manual Import',
                'source_url' => $candidateData['linkedin_url'],
                'consent_note' => $data['consent_status'] === 'CONSENTED'
                    ? 'Consent recorded by HR during manual LinkedIn import.'
                    : 'Consent pending. Manual profile added without scraping.',
                'consent_captured_at' => $data['consent_status'] === 'CONSENTED' ? now() : null,
                'consent_captured_by' => $data['consent_status'] === 'CONSENTED' ? Auth::id() : null,
                'contact_allowed' => $data['consent_status'] === 'CONSENTED',
            ]);

            return $candidate;
        });
        $audit->log(Auth::id(), 'LINKEDIN_MANUAL_IMPORT', 'candidates', (string) $candidate->id, [
            'linkedin_url' => $candidateData['linkedin_url'],
            'consent_status' => $data['consent_status'],
        ], $request);

        return redirect()->route('candidates.show', $candidate)->with('status', 'LinkedIn profile imported manually and compliantly');
    }

    private function nameFromPayload(array $payload, SearchProviderService $search): string
    {
        $stored = trim((string) ($payload['candidate_name'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $snippet = trim((string) ($payload['snippet'] ?? ''));
        if ($extracted = $search->candidateName($title, $snippet, (string) ($payload['url'] ?? ''))) {
            return $extracted;
        }

        if ($title === '') {
            return 'Imported Candidate';
        }
        if (str_contains($title, '-')) {
            return trim((string) preg_split('/-/', $title)[0]) ?: 'Imported Candidate';
        }

        return mb_substr($title, 0, 120);
    }

    private function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value))));
    }
}
