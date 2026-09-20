<?php

namespace App\Http\Controllers;

use App\Models\AiSearchJob;
use App\Models\AiSearchResult;
use App\Models\Candidate;
use App\Services\AiInsightsService;
use App\Services\AuditService;
use App\Services\CvParserService;
use App\Services\DuplicateDetectionService;
use App\Services\FileSecurityService;
use App\Services\SearchProviderService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AiSearchController extends Controller
{
    public function index(TenantService $tenant): View
    {
        return view('ai-search.index', ['history' => $tenant->scope(AiSearchJob::query(), Auth::user())->latest()->take(10)->get()]);
    }

    /**
     * Show a past search's results again. Reached via the "Search History" list or by
     * the browser Back button after an import — results are read back from what was
     * persisted at search time, so this is a plain GET and never resubmits the search.
     */
    public function show(AiSearchJob $searchJob, TenantService $tenant): View
    {
        if (! Auth::user()?->isSuperAdmin() && $searchJob->company_id !== Auth::user()?->company_id) {
            abort(404);
        }
        $searchJob->load('results');

        return view('ai-search.index', [
            'history' => $tenant->scope(AiSearchJob::query(), Auth::user())->latest()->take(10)->get(),
            'queries' => $searchJob->queries ?? [],
            'results' => $searchJob->results->map(fn ($row) => $row->raw_payload ?? [])->all(),
            'searchJob' => $searchJob,
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
        $queries = $search->buildQueries($filters);
        $results = $search->cvSourcing($filters);
        foreach ($results as &$result) {
            $result['candidate_name'] = $this->extractCandidateName($result['title'], $result['snippet']);
        }
        unset($result);
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
        CvParserService $parser,
        FileSecurityService $fileSecurity
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
            $download = $this->downloadAndParseSourceDocument($sourceUrl, $fileType, $fileSecurity, $parser);
        }

        $candidateData = [
            'full_name' => $this->nameFromPayload($payload),
            'email' => null,
            'phone' => null,
            'linkedin_url' => str_contains(strtolower($sourceUrl), 'linkedin.com') ? $sourceUrl : null,
            'title' => mb_substr((string) ($payload['title'] ?? 'Candidate Profile'), 0, 120),
            'specialization' => $data['specialization'] ?: ($jobFilters['specialization'] ?? 'Unclassified'),
            'country' => $jobFilters['country'] ?? null,
            'city' => $jobFilters['city'] ?? null,
            'ai_summary' => mb_substr((string) ($payload['snippet'] ?? ''), 0, 900),
            'consent_status' => $data['consent_status'],
            'status' => 'NEW',
            'company_id' => $result->job?->company_id ?: $tenant->defaultCompanyId(Auth::user()),
        ];
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
                'expected_salary' => $p['expected_salary'] ?? null,
                'notice_period' => $p['notice_period'] ?? null,
                'ai_summary' => isset($p['raw_text']) ? mb_substr($p['raw_text'], 0, 1200) : null,
            ], fn ($v) => $v !== null && $v !== ''));
            $candidateData['parsed_profile'] = [
                'skills' => $p['skills'] ?? [],
                'languages' => $p['languages'] ?? [],
                'previous_companies' => $p['previous_companies'] ?? [],
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
        ], $request);

        return redirect()->route('candidates.show', $candidate)->with('status', $existing ? 'Existing candidate linked to AI search result' : 'AI search result imported to candidate database');
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

    private function nameFromPayload(array $payload): string
    {
        $stored = trim((string) ($payload['candidate_name'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $snippet = trim((string) ($payload['snippet'] ?? ''));
        if ($extracted = $this->extractCandidateName($title, $snippet)) {
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

    /**
     * Pull the candidate's real name out of the title/snippet when the search result
     * follows one of the common CV-listing phrasings ("X is a Jordanian civil
     * engineer...", "resume for X", "Profile: X.", "Resume - X"). Falls back to null
     * so the caller can use its own title-based heuristic — this only returns a name
     * it's reasonably confident about.
     */
    /** Capitalized phrases that match the name pattern but are places, not people — must be rejected. */
    private const NAME_EXTRACTION_BLOCKLIST = [
        'saudi arabia', 'united arab emirates', 'united states', 'united kingdom', 'middle east',
        'riyadh', 'jeddah', 'dammam', 'khobar', 'mecca', 'medina', 'abu dhabi', 'dubai', 'doha',
        'kuwait city', 'manama', 'muscat', 'amman', 'cairo', 'gulf', 'gcc', 'ksa', 'uae', 'qatar',
        'kuwait', 'bahrain', 'oman', 'egypt', 'jordan', 'pakistan', 'india', 'philippines', 'lebanon',
        'civil engineer', 'civil engineering', 'senior civil', 'project engineer', 'site engineer',
    ];

    private function extractCandidateName(string $title, string $snippet): ?string
    {
        $namePattern = "[A-Z][\p{L}'\-]+(?:\s+[A-Z][\p{L}'\-]+){1,4}";
        $patterns = [
            "/({$namePattern})\s+is\s+(?:a|an)\s/u",
            "/resume\s+for\s+({$namePattern})/iu",
            "/(?:cv|curriculum vitae)\s+for\s+({$namePattern})/iu",
            "/profile:\s*({$namePattern})\s*\./iu",
            "/resume\s*[:\-–]\s*({$namePattern})/iu",
            "/\bcv\s*[:\-–]\s*({$namePattern})/iu",
        ];

        foreach ([$snippet, $title] as $source) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source, $matches)) {
                    $name = trim($matches[1]);
                    $wordCount = str_word_count($name);
                    if ($wordCount < 2 || $wordCount > 5) {
                        continue;
                    }
                    if (in_array(strtolower($name), self::NAME_EXTRACTION_BLOCKLIST, true)) {
                        continue;
                    }

                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Fetch and parse a genuinely public, directly-downloadable CV file (pdf/doc/docx
     * URL, never LinkedIn) so an import carries real contact/skills data instead of
     * just the search snippet. Best-effort: returns null on any failure so the caller
     * falls back to metadata-only import — a network hiccup must never block the import.
     *
     * @return array{path:string,file_name:string,mime_type:string,checksum:string,malware_scan_status:string,parsed:array<string,mixed>}|null
     */
    private function downloadAndParseSourceDocument(string $url, string $fileType, FileSecurityService $fileSecurity, CvParserService $parser): ?array
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

        $path = 'private/cv-bank-imports/'.Str::uuid()->toString().'.'.$fileType;
        Storage::disk('local')->put($path, $body);
        $absolute = Storage::disk('local')->path($path);

        $scanStatus = $fileSecurity->malwareScan($absolute);
        if ($scanStatus === 'FAILED') {
            Storage::disk('local')->delete($path);

            return null;
        }

        try {
            $parsed = $parser->parse($absolute);
        } catch (Throwable) {
            Storage::disk('local')->delete($path);

            return null;
        }

        return [
            'path' => $path,
            'file_name' => Str::limit(basename(parse_url($url, PHP_URL_PATH) ?: 'cv.'.$fileType), 180, ''),
            'mime_type' => $response->header('Content-Type') ?: 'application/octet-stream',
            'checksum' => hash('sha256', $body),
            'malware_scan_status' => $scanStatus,
            'parsed' => $parsed,
        ];
    }

    private function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value))));
    }
}
