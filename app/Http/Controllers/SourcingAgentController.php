<?php

namespace App\Http\Controllers;

use App\Models\SourcingAgent;
use App\Services\SourcingAgentService;
use App\Services\SpecialtyClassifierService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SourcingAgentController extends Controller
{
    public function __construct(private readonly SpecialtyClassifierService $classifier)
    {
    }

    public function index(TenantService $tenant): View
    {
        $agents = $tenant->scope(SourcingAgent::query(), Auth::user())
            ->withCount(['candidates', 'runs'])
            ->orderByDesc('is_active')
            ->orderByDesc('candidates_added')
            ->get();

        return view('sourcing-agents.index', [
            'agents' => $agents,
            'personas' => SourcingAgent::PERSONAS,
        ]);
    }

    public function create(): View
    {
        return view('sourcing-agents.create', [
            'specialties' => $this->classifier->all(),
            'personas' => SourcingAgent::PERSONAS,
        ]);
    }

    public function store(Request $request, TenantService $tenant): RedirectResponse
    {
        $data = $this->validateAgent($request);
        $data['company_id'] = $tenant->defaultCompanyId(Auth::user());
        $data['created_by'] = Auth::id();
        $data['specialty_name'] = ($this->classifier->findBySlug($data['specialty_slug']) ?? ['name' => 'Unclassified'])['name'];

        $agent = SourcingAgent::create($data);
        $agent->scheduleNext();

        return redirect()->route('sourcing-agents.show', $agent)
            ->with('status', 'Agent hired. It will run on its next scheduled tick — or press "Run now".');
    }

    public function show(SourcingAgent $agent, TenantService $tenant): View
    {
        $this->authorizeAgent($agent, $tenant);
        $agent->load(['runs' => fn ($q) => $q->limit(20)]);
        $picks = $agent->candidates()->orderByPivot('score', 'desc')->limit(50)->get();

        return view('sourcing-agents.show', [
            'agent' => $agent,
            'picks' => $picks,
        ]);
    }

    public function edit(SourcingAgent $agent, TenantService $tenant): View
    {
        $this->authorizeAgent($agent, $tenant);

        return view('sourcing-agents.edit', [
            'agent' => $agent,
            'specialties' => $this->classifier->all(),
            'personas' => SourcingAgent::PERSONAS,
        ]);
    }

    public function update(Request $request, SourcingAgent $agent, TenantService $tenant): RedirectResponse
    {
        $this->authorizeAgent($agent, $tenant);
        $data = $this->validateAgent($request);
        $data['specialty_name'] = ($this->classifier->findBySlug($data['specialty_slug']) ?? ['name' => $agent->specialty_name])['name'];
        $agent->update($data);

        return redirect()->route('sourcing-agents.show', $agent)->with('status', 'Agent updated.');
    }

    public function destroy(SourcingAgent $agent, TenantService $tenant): RedirectResponse
    {
        $this->authorizeAgent($agent, $tenant);
        $agent->delete();

        return redirect()->route('sourcing-agents.index')->with('status', 'Agent retired.');
    }

    public function run(SourcingAgent $agent, TenantService $tenant, SourcingAgentService $service): RedirectResponse
    {
        $this->authorizeAgent($agent, $tenant);
        $run = $service->runAgent($agent, Auth::user());

        return redirect()->route('sourcing-agents.show', $agent)
            ->with('status', "Run #{$run->id} complete: {$run->candidates_added} pick(s), {$run->results_scanned} scanned.");
    }

    private function validateAgent(Request $request): array
    {
        $csvKeys = [
            'countries_csv' => 'countries',
            'cities_csv' => 'cities',
            'must_csv' => 'must_have_skills',
            'nice_csv' => 'nice_to_have_skills',
            'languages_csv' => 'languages',
        ];
        foreach ($csvKeys as $from => $to) {
            $raw = (string) $request->input($from, '');
            $items = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
            $request->merge([$to => $items]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'persona' => 'required|in:'.implode(',', array_keys(SourcingAgent::PERSONAS)),
            'avatar_emoji' => 'nullable|string|max:8',
            'bio' => 'nullable|string|max:2000',
            'specialty_slug' => 'required|string|max:120',
            'countries' => 'nullable|array',
            'countries.*' => 'string|max:80',
            'cities' => 'nullable|array',
            'cities.*' => 'string|max:80',
            'must_have_skills' => 'nullable|array',
            'must_have_skills.*' => 'string|max:80',
            'nice_to_have_skills' => 'nullable|array',
            'nice_to_have_skills.*' => 'string|max:80',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:60',
            'min_years' => 'required|integer|min:0|max:40',
            'max_years' => 'required|integer|min:0|max:60|gte:min_years',
            'min_score' => 'required|integer|min:0|max:100',
            'quantity_per_run' => 'required|integer|min:1|max:100',
            'frequency' => 'required|in:hourly,daily,weekly,manual',
            'is_active' => 'sometimes|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function authorizeAgent(SourcingAgent $agent, TenantService $tenant): void
    {
        $ok = $tenant->scope(SourcingAgent::query(), Auth::user())
            ->whereKey($agent->getKey())
            ->exists();
        abort_unless($ok, 403);
    }
}
