<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\AiCandidateRankingService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CandidateJobMatchController extends Controller
{
    public function show(Candidate $candidate, AiCandidateRankingService $ranking): View
    {
        $this->authorizeTenant($candidate);
        $scores = $ranking->suggestJobsForCandidate($candidate);

        return view('candidate-matches.show', [
            'candidate' => $candidate->load(['skills', 'languages', 'education']),
            'scores' => $scores,
        ]);
    }

    public function rebuild(Candidate $candidate, AiCandidateRankingService $ranking, AuditService $audit, Request $request): RedirectResponse
    {
        $this->authorizeTenant($candidate);
        $scores = $ranking->suggestJobsForCandidate($candidate);
        $audit->log(Auth::id(), 'AI_CANDIDATE_JOB_MATCH_REBUILD', 'candidates', (string) $candidate->id, ['scores' => $scores->count()], $request);

        return back()->with('status', 'Candidate-to-job matches refreshed');
    }

    private function authorizeTenant(Candidate $candidate): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $candidate->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
