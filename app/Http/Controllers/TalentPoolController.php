<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\TalentPool;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TalentPoolController extends Controller
{
    public const CATEGORIES = [
        'Engineers', 'Accountants', 'HR', 'Drivers', 'Labor', 'Technicians', 'Sales',
        'Project Managers', 'Procurement', 'Safety Officers', 'QA/QC', 'Other',
    ];

    public function index(TenantService $tenant): View
    {
        $user = Auth::user();
        return view('talent-pools.index', [
            'pools' => $tenant->scope(TalentPool::withCount('candidates')->with('candidates'), $user)->orderBy('category')->orderBy('name')->get(),
            'candidates' => $tenant->scope(Candidate::query(), $user)->orderBy('full_name')->take(500)->get(['id', 'full_name', 'title']),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request, TenantService $tenant, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $pool = TalentPool::firstOrCreate([
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
            'name' => $data['name'],
        ], $data + [
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
            'created_by' => Auth::id(),
        ]);
        $audit->log(Auth::id(), 'TALENT_POOL_CREATE', 'talent_pools', (string) $pool->id, $data, $request);

        return back()->with('status', 'Talent pool saved');
    }

    public function addCandidate(Request $request, TalentPool $pool, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($pool);
        $data = $request->validate([
            'candidate_id' => ['required', 'exists:candidates,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $candidate = Candidate::findOrFail($data['candidate_id']);
        if (! Auth::user()?->isSuperAdmin() && $candidate->company_id !== Auth::user()?->company_id) {
            abort(404);
        }

        $pool->candidates()->syncWithoutDetaching([
            $candidate->id => ['added_by' => Auth::id(), 'notes' => $data['notes'] ?? null],
        ]);
        $audit->log(Auth::id(), 'TALENT_POOL_ADD_CANDIDATE', 'talent_pools', (string) $pool->id, ['candidate_id' => $candidate->id], $request);

        return back()->with('status', 'Candidate saved to talent pool');
    }

    public function removeCandidate(TalentPool $pool, Candidate $candidate, AuditService $audit, Request $request): RedirectResponse
    {
        $this->authorizeTenant($pool);
        $pool->candidates()->detach($candidate->id);
        $audit->log(Auth::id(), 'TALENT_POOL_REMOVE_CANDIDATE', 'talent_pools', (string) $pool->id, ['candidate_id' => $candidate->id], $request);

        return back()->with('status', 'Candidate removed from talent pool');
    }

    private function authorizeTenant(TalentPool $pool): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $pool->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
