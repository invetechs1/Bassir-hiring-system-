<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\CandidateSearchService;
use App\Services\SpecialtyClassifierService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CvBankController extends Controller
{
    public function __construct(private readonly SpecialtyClassifierService $classifier)
    {
    }

    public function index(TenantService $tenant): View
    {
        $counts = $tenant->scope(Candidate::query(), Auth::user())
            ->selectRaw('specialization, count(*) as total')
            ->groupBy('specialization')
            ->pluck('total', 'specialization')
            ->toArray();

        $specialties = collect($this->classifier->all())->map(function (array $spec) use ($counts) {
            $spec['count'] = (int) ($counts[$spec['name']] ?? 0);

            return $spec;
        })->sortByDesc('count')->values()->all();

        $totalIndexed = array_sum(array_map(fn ($s) => $s['count'], $specialties));

        return view('cv-bank.index', [
            'specialties' => $specialties,
            'totalIndexed' => $totalIndexed,
        ]);
    }

    public function show(Request $request, string $slug, TenantService $tenant, CandidateSearchService $search): View
    {
        $spec = $this->classifier->findBySlug($slug) ?? [
            'slug' => $slug,
            'name' => SpecialtyClassifierService::UNCLASSIFIED_NAME,
            'name_ar' => 'غير مصنف',
        ];

        $q = trim((string) $request->query('q', ''));
        $mode = in_array($request->query('mode'), ['all', 'any'], true) ? $request->query('mode') : 'balanced';

        if ($q !== '') {
            // Inside a specialty folder the search still looks at skills, past roles and the full CV
            // text — not just name/title/city — and ranks the best fit first.
            $ranked = $search->search($q, Auth::user(), ['mode' => $mode, 'specialization' => $spec['name']]);
            $page = LengthAwarePaginator::resolveCurrentPage();
            $candidates = new LengthAwarePaginator(
                $ranked->forPage($page, 25)->values(),
                $ranked->count(),
                25,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $candidates = $tenant->scope(Candidate::query(), Auth::user())
                ->where('specialization', $spec['name'])
                ->with(['skills', 'documents' => fn ($docs) => $docs->latest()])
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        }

        return view('cv-bank.show', [
            'specialty' => $spec,
            'candidates' => $candidates,
            'q' => $q,
            'mode' => $mode,
        ]);
    }

    public function reclassify(Request $request, Candidate $candidate, TenantService $tenant): RedirectResponse
    {
        abort_unless(Auth::user()?->hasPermission('candidate.write'), 403);

        $data = $request->validate([
            'specialization' => 'required|string|max:120',
        ]);

        // Tenant scope guard so a user can't touch other companies' candidates.
        $scoped = $tenant->scope(Candidate::query(), Auth::user())
            ->whereKey($candidate->getKey())
            ->exists();
        abort_unless($scoped, 403);

        $candidate->update(['specialization' => $data['specialization']]);

        return back()->with('status', 'Candidate specialty updated.');
    }
}
