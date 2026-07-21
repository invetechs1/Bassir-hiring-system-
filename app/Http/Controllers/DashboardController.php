<?php

namespace App\Http\Controllers;

use App\Models\AiSearchJob;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateScore;
use App\Models\Interview;
use App\Models\Job;
use App\Models\PipelineStageHistory;
use App\Services\TenantService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(TenantService $tenant): View
    {
        $user = Auth::user();
        $candidateQuery = $tenant->scope(Candidate::query(), $user);
        $jobQuery = $tenant->scope(Job::query(), $user);
        $interviewQuery = $tenant->scope(Interview::query(), $user);
        $aiSearchQuery = $tenant->scope(AiSearchJob::query(), $user);
        $applicationQuery = $tenant->scope(CandidateApplication::query(), $user);
        $jobPool = $tenant->scope(Job::withCount(['scores as strong_scores_count' => fn ($query) => $query->where('overall', '>=', 60)]), $user)->get();
        $aiReviewed = CandidateScore::whereHas('candidate', fn ($query) => $tenant->scope($query, $user))->count();
        $aiShortlisted = (clone $applicationQuery)->whereNotNull('ai_shortlisted_at')->count();

        return view('dashboard.index', [
            'totalCandidates' => (clone $candidateQuery)->count(),
            'shortlisted' => (clone $candidateQuery)->where('status', 'SHORTLISTED')->count(),
            'rejected' => (clone $candidateQuery)->where('status', 'REJECTED')->count(),
            'interviews' => (clone $interviewQuery)->where('status', 'SCHEDULED')->count(),
            'openJobs' => (clone $jobQuery)->whereIn('approval_status', ['PENDING', 'APPROVED'])->count(),
            'newCandidates' => (clone $candidateQuery)->whereDate('created_at', '>=', now()->subDays(30))->count(),
            'aiSearchRuns' => (clone $aiSearchQuery)->whereDate('created_at', '>=', now()->subDays(30))->count(),
            'recentCandidates' => $tenant->scope(Candidate::with('scores'), $user)->latest()->take(8)->get(),
            'specializations' => $tenant->scope(Candidate::query(), $user)->selectRaw('specialization, count(*) as total')->groupBy('specialization')->orderByDesc('total')->take(8)->get(),
            'statusBreakdown' => $tenant->scope(Candidate::query(), $user)->selectRaw('status, count(*) as total')->groupBy('status')->orderByDesc('total')->get(),
            'recentSearches' => $tenant->scope(AiSearchJob::query(), $user)->latest()->take(6)->get(),
            'timeToHireKpis' => [
                'avg_time_to_shortlist_hours' => $this->averageStageHours('SHORTLISTED', $tenant, $user),
                'avg_time_to_first_interview_hours' => $this->averageInterviewHours($tenant, $user),
                'avg_time_to_hire_days' => $this->averageHireDays($tenant, $user),
                'ai_reviewed' => $aiReviewed,
                'ai_shortlisted' => $aiShortlisted,
                'manual_hours_saved' => round($aiReviewed * 0.25, 1),
                'jobs_without_enough_candidates' => $jobPool->where('strong_scores_count', '<', 3)->count(),
                'urgent_hiring_positions' => $jobPool->filter(fn ($job) => in_array($job->approval_status, ['PENDING', 'APPROVED'], true) && $job->strong_scores_count < max(1, (int) $job->vacancies))->count(),
            ],
        ]);
    }

    private function averageStageHours(string $stage, TenantService $tenant, $user): ?float
    {
        $histories = $tenant->scope(PipelineStageHistory::with('application'), $user)
            ->where('to_stage', $stage)
            ->latest()
            ->take(200)
            ->get()
            ->filter(fn ($history) => $history->application?->applied_at);

        if ($histories->isEmpty()) {
            return null;
        }

        return round($histories->avg(fn ($history) => $history->application->applied_at->diffInMinutes($history->created_at) / 60), 1);
    }

    private function averageInterviewHours(TenantService $tenant, $user): ?float
    {
        $interviews = $tenant->scope(Interview::with('candidate'), $user)->latest('starts_at')->take(200)->get();
        $values = $interviews->filter(fn ($interview) => $interview->candidate?->created_at && $interview->starts_at)
            ->map(fn ($interview) => $interview->candidate->created_at->diffInMinutes($interview->starts_at) / 60);

        return $values->isEmpty() ? null : round($values->avg(), 1);
    }

    private function averageHireDays(TenantService $tenant, $user): ?float
    {
        $hires = $tenant->scope(CandidateApplication::query(), $user)
            ->where('current_stage', 'HIRED')
            ->latest()
            ->take(200)
            ->get()
            ->filter(fn ($application) => $application->applied_at && $application->updated_at);

        return $hires->isEmpty() ? null : round($hires->avg(fn ($application) => $application->applied_at->diffInHours($application->updated_at) / 24), 1);
    }
}
