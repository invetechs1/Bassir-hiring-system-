<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MatchingController extends Controller
{
    public function index(Request $request, TenantService $tenant): View
    {
        $user = Auth::user();
        $jobs = $tenant->scope(Job::query(), $user)->orderByDesc('created_at')->get(['id', 'title', 'location']);
        $selectedJobId = (int) ($request->input('job_id') ?: ($jobs->first()->id ?? 0));

        $job = null;
        $scores = collect();
        if ($selectedJobId > 0) {
            $job = $tenant->scope(Job::with(['requiredSkills', 'scores.candidate.skills', 'scores.candidate.languages']), $user)->find($selectedJobId);
            $scores = $job?->scores?->sortByDesc('overall')->values() ?? collect();
        }

        return view('matching.index', [
            'jobs' => $jobs,
            'selectedJobId' => $selectedJobId,
            'job' => $job,
            'scores' => $scores,
            'topThree' => $scores->take(3),
        ]);
    }
}
