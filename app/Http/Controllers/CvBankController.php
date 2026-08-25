<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\SpecialtyClassifierService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, string $slug, TenantService $tenant): View
    {
        $spec = $this->classifier->findBySlug($slug) ?? [
            'slug' => $slug,
            'name' => SpecialtyClassifierService::UNCLASSIFIED_NAME,
            'name_ar' => 'غير مصنف',
        ];

        $q = trim((string) $request->query('q', ''));

        $candidates = $tenant->scope(Candidate::query(), Auth::user())
            ->where('specialization', $spec['name'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('full_name', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->with(['documents' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('cv-bank.show', [
            'specialty' => $spec,
            'candidates' => $candidates,
            'q' => $q,
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
