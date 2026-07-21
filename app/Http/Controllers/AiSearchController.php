<?php

namespace App\Http\Controllers;

use App\Models\AiSearchJob;
use App\Models\AiSearchResult;
use App\Models\Candidate;
use App\Services\AiInsightsService;
use App\Services\AuditService;
use App\Services\DuplicateDetectionService;
use App\Services\SearchProviderService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiSearchController extends Controller
{
    public function index(TenantService $tenant): View
    {
        return view('ai-search.index', ['history' => $tenant->scope(AiSearchJob::query(), Auth::user())->latest()->take(10)->get()]);
    }

    public function cvSourcing(Request $request, SearchProviderService $search, TenantService $tenant)
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
        return view('ai-search.index', [
            'history' => $tenant->scope(AiSearchJob::query(), Auth::user())->latest()->take(10)->get(),
            'queries' => $queries,
            'results' => $results,
            'searchJob' => $job,
        ]);
    }

    public function importResult(
        Request $request,
        DuplicateDetectionService $duplicates,
        AiInsightsService $insights,
        AuditService $audit,
        TenantService $tenant
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
        if ($candidateData['consent_status'] === 'CONSENTED') {
            $candidateData['consent_captured_at'] = now()->toDateString();
            $candidateData['consent_captured_by'] = Auth::id();
            $candidateData['contact_allowed'] = true;
        }
        $candidateData['duplicate_hash'] = $duplicates->hash($candidateData);

        $existing = $tenant->scope(Candidate::query(), Auth::user())
            ->where(function ($query) use ($candidateData) {
                $query->where('duplicate_hash', $candidateData['duplicate_hash'])
                    ->when($candidateData['linkedin_url'], fn ($inner) => $inner->orWhere('linkedin_url', $candidateData['linkedin_url']));
            })
            ->first();

        $candidate = DB::transaction(function () use ($existing, $candidateData, $result, $sourceUrl, $data) {
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

            return $candidate;
        });

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
        $title = trim((string) ($payload['title'] ?? ''));
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
