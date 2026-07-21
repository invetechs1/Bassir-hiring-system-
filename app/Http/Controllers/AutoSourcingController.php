<?php

namespace App\Http\Controllers;

use App\Models\SourcingRun;
use App\Models\SourcingSearch;
use App\Services\AutoSourcingService;
use App\Services\ApiCredentialService;
use App\Services\Connectors\PlatformConnectorRegistry;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AutoSourcingController extends Controller
{
    public function index(PlatformConnectorRegistry $connectors, ApiCredentialService $credentials, TenantService $tenant): View
    {
        $searches = $tenant->scope(SourcingSearch::query(), Auth::user())
            ->withCount('runs')->latest()->get();
        $runs = $tenant->scope(SourcingRun::query(), Auth::user())
            ->with('search')->latest()->take(15)->get();

        $webProviders = [
            ['label' => 'Google Custom Search API', 'configured' => (bool) $credentials->get('google_cse_key', 'GOOGLE_CUSTOM_SEARCH_API_KEY') && (bool) $credentials->get('google_cse_id', 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID')],
            ['label' => 'Bing Search API', 'configured' => (bool) $credentials->get('bing_search', 'BING_SEARCH_API_KEY')],
            ['label' => 'SerpAPI', 'configured' => (bool) $credentials->get('serpapi', 'SERPAPI_API_KEY')],
            ['label' => 'Agency / job-board feed', 'configured' => (bool) $credentials->get('agency_feed_url', 'AGENCY_FEED_URL')],
        ];

        return view('auto-sourcing.index', [
            'searches' => $searches,
            'runs' => $runs,
            'connectors' => $connectors->statuses(),
            'webProviders' => $webProviders,
        ]);
    }

    public function store(Request $request, TenantService $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'skills' => ['nullable', 'string', 'max:1200'],
            'software_skills' => ['nullable', 'string', 'max:1200'],
            'languages' => ['nullable', 'string', 'max:500'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'frequency' => ['required', 'in:daily,weekly,manual'],
            'default_consent_status' => ['required', 'in:PENDING,CONSENTED'],
            'download_cvs' => ['nullable', 'boolean'],
            'auto_import' => ['nullable', 'boolean'],
        ]);

        SourcingSearch::create([
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
            'name' => $data['name'],
            'job_title' => $data['job_title'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'skills' => $this->split($data['skills'] ?? ''),
            'software_skills' => $this->split($data['software_skills'] ?? ''),
            'languages' => $this->split($data['languages'] ?? ''),
            'quantity' => (int) ($data['quantity'] ?? 25),
            'frequency' => $data['frequency'],
            'default_consent_status' => $data['default_consent_status'],
            'download_cvs' => $request->boolean('download_cvs'),
            'auto_import' => $request->boolean('auto_import', true),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('auto-sourcing.index')->with('status', 'Sourcing search saved.');
    }

    public function run(SourcingSearch $sourcingSearch, AutoSourcingService $engine): RedirectResponse
    {
        $this->authorizeTenant($sourcingSearch);
        $run = $engine->runSearch($sourcingSearch, Auth::user());

        return redirect()->route('auto-sourcing.index')->with(
            'status',
            "Run complete: {$run->results_found} results · {$run->candidates_created} created · {$run->candidates_linked} linked · {$run->cvs_downloaded} CVs · {$run->flagged_manual} flagged for manual review."
        );
    }

    public function destroy(SourcingSearch $sourcingSearch): RedirectResponse
    {
        $this->authorizeTenant($sourcingSearch);
        $sourcingSearch->delete();

        return redirect()->route('auto-sourcing.index')->with('status', 'Sourcing search removed.');
    }

    private function authorizeTenant(SourcingSearch $search): void
    {
        $user = Auth::user();
        if (! $user?->isSuperAdmin() && $search->company_id !== $user?->company_id) {
            abort(404);
        }
    }

    /**
     * @return array<int, string>
     */
    private function split(string $value): array
    {
        return collect(preg_split('/[,\n]+/', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
