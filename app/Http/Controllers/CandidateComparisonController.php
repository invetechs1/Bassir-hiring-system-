<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CandidateComparisonController extends Controller
{
    public function index(Request $request, TenantService $tenant): View
    {
        $user = Auth::user();
        $ids = array_slice(array_values(array_filter(array_map('intval', (array) $request->input('candidate_ids', [])))), 0, 5);

        return view('comparisons.candidates', [
            'candidateOptions' => $tenant->scope(Candidate::query(), $user)->orderBy('full_name')->take(500)->get(['id', 'full_name', 'title']),
            'selectedCandidates' => $ids
                ? $tenant->scope(Candidate::with(['skills', 'languages', 'education', 'certifications', 'scores', 'notes']), $user)->whereIn('id', $ids)->get()
                : collect(),
        ]);
    }
}
