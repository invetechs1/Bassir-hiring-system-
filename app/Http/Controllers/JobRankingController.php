<?php

namespace App\Http\Controllers;

use App\Models\AiRecommendationFeedback;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateScore;
use App\Models\Job;
use App\Models\PipelineStageHistory;
use App\Services\AiCandidateRankingService;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobRankingController extends Controller
{
    public function index(Job $job, Request $request, AiCandidateRankingService $ranking, TenantService $tenant): View
    {
        $this->authorizeTenant($job);
        if ($request->boolean('rebuild') || $job->scores()->count() === 0) {
            $ranking->rankJob($job);
        }

        $scoresQuery = CandidateScore::with(['candidate.skills', 'candidate.languages', 'candidate.education', 'candidate.documents'])
            ->where('job_id', $job->id)
            ->when($job->company_id, fn ($query) => $query->whereHas('candidate', fn ($candidate) => $candidate->where('company_id', $job->company_id)))
            ->when($request->filled('score_min'), fn ($query) => $query->where('overall', '>=', $request->integer('score_min')))
            ->when($request->filled('availability'), fn ($query) => $query->whereHas('candidate', function ($inner) use ($request) {
                $inner->where(function ($availabilityQuery) use ($request) {
                    $availabilityQuery->where('availability', 'like', '%'.$request->availability.'%')
                        ->orWhere('notice_period', 'like', '%'.$request->availability.'%');
                });
            }))
            ->when($request->filled('city'), fn ($query) => $query->whereHas('candidate', fn ($inner) => $inner->where('city', 'like', '%'.$request->city.'%')))
            ->when($request->filled('nationality'), fn ($query) => $query->whereHas('candidate', fn ($inner) => $inner->where('nationality', 'like', '%'.$request->nationality.'%')))
            ->when($request->filled('status'), fn ($query) => $query->whereHas('candidate', fn ($inner) => $inner->where('status', $request->status)))
            ->when($request->filled('years_min'), fn ($query) => $query->whereHas('candidate', fn ($inner) => $inner->where('years_experience', '>=', $request->integer('years_min'))))
            ->when($request->filled('salary_min'), fn ($query) => $query->whereHas('candidate', fn ($inner) => $inner->where('expected_salary', '>=', $request->input('salary_min'))))
            ->when($request->filled('salary_max'), fn ($query) => $query->whereHas('candidate', fn ($inner) => $inner->where('expected_salary', '<=', $request->input('salary_max'))))
            ->when($request->filled('skill'), fn ($query) => $query->whereHas('candidate.skills', fn ($inner) => $inner->where('name', 'like', '%'.$request->skill.'%')))
            ->when($request->filled('language'), fn ($query) => $query->whereHas('candidate.languages', fn ($inner) => $inner->where('name', 'like', '%'.$request->language.'%')))
            ->when($request->filled('education'), fn ($query) => $query->whereHas('candidate.education', function ($inner) use ($request) {
                $inner->where(function ($educationQuery) use ($request) {
                    $educationQuery->where('degree', 'like', '%'.$request->education.'%')
                        ->orWhere('institution', 'like', '%'.$request->education.'%');
                });
            }))
            ->orderByDesc('overall')
            ->orderByDesc('confidence');

        $scores = $scoresQuery->paginate(30)->withQueryString();
        $allScores = CandidateScore::where('job_id', $job->id)
            ->when($job->company_id, fn ($query) => $query->whereHas('candidate', fn ($candidate) => $candidate->where('company_id', $job->company_id)))
            ->orderByDesc('overall')
            ->get();

        return view('rankings.job', [
            'job' => $job->load('requiredSkills'),
            'scores' => $scores,
            'grouped' => $ranking->grouped($allScores),
            'jobs' => $tenant->scope(Job::query(), Auth::user())->latest()->get(['id', 'title', 'location']),
        ]);
    }

    public function rebuild(Job $job, AiCandidateRankingService $ranking, AuditService $audit, Request $request): RedirectResponse
    {
        $this->authorizeTenant($job);
        $scores = $ranking->rankJob($job);
        $audit->log(Auth::id(), 'AI_JOB_RANKING_REBUILD', 'jobs', (string) $job->id, ['scores' => $scores->count()], $request);

        return redirect()->route('rankings.job', $job)->with('status', 'AI ranking rebuilt for this job');
    }

    public function decision(Job $job, Candidate $candidate, Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($job);
        $this->authorizeCandidate($candidate);
        $data = $request->validate([
            'decision' => ['required', 'in:SHORTLIST,REJECT,KEEP_REVIEW,SCHEDULE_INTERVIEW'],
            'note' => ['nullable', 'string', 'max:3000'],
            'ai_feedback' => ['nullable', 'in:CORRECT,WRONG,NEEDS_REVIEW'],
            'feedback_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $score = CandidateScore::firstOrCreate([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
        ], [
            'overall' => 0,
            'technical' => 0,
            'experience' => 0,
            'education' => 0,
            'salary_fit' => 0,
            'location_fit' => 0,
            'availability' => 0,
            'notice_period_fit' => 0,
            'risk' => 100,
            'matching_percentage' => 0,
            'confidence' => 0,
            'recommendation' => 'Needs Review',
            'ranking_band' => 'WEAK',
        ]);

        DB::transaction(function () use ($job, $candidate, $score, $data) {
            $score->update([
                'recruiter_decision' => $data['decision'],
                'recruiter_decision_note' => $data['note'] ?? null,
                'recruiter_feedback' => $data['ai_feedback'] ?? $score->recruiter_feedback,
                'recruiter_feedback_note' => $data['feedback_note'] ?? $score->recruiter_feedback_note,
                'feedback_by' => Auth::id(),
                'feedback_at' => now(),
            ]);

            if (! empty($data['ai_feedback'])) {
                AiRecommendationFeedback::create([
                    'company_id' => $job->company_id,
                    'candidate_score_id' => $score->id,
                    'candidate_id' => $candidate->id,
                    'job_id' => $job->id,
                    'feedback' => $data['ai_feedback'],
                    'notes' => $data['feedback_note'] ?? null,
                    'created_by' => Auth::id(),
                ]);
            }

            if (in_array($data['decision'], ['SHORTLIST', 'REJECT', 'SCHEDULE_INTERVIEW'], true)) {
                $stage = match ($data['decision']) {
                    'SHORTLIST' => 'SHORTLISTED',
                    'SCHEDULE_INTERVIEW' => 'INTERVIEW_SCHEDULED',
                    default => 'REJECTED',
                };
                $application = CandidateApplication::firstOrCreate([
                    'candidate_id' => $candidate->id,
                    'job_id' => $job->id,
                ], [
                    'company_id' => $job->company_id,
                    'source' => 'AI Ranking',
                    'current_stage' => 'AI_REVIEWED',
                    'status' => 'ACTIVE',
                    'reviewed_by' => Auth::id(),
                ]);
                $fromStage = $application->current_stage;
                $application->update([
                    'current_stage' => $stage,
                    'status' => $stage === 'REJECTED' ? 'CLOSED' : 'ACTIVE',
                    'reviewed_by' => Auth::id(),
                    'ai_shortlisted_at' => $stage === 'SHORTLISTED' ? now() : $application->ai_shortlisted_at,
                    'ai_shortlisted_by' => $stage === 'SHORTLISTED' ? Auth::id() : $application->ai_shortlisted_by,
                    'notes' => $data['note'] ?? $application->notes,
                ]);
                PipelineStageHistory::create([
                    'company_id' => $job->company_id,
                    'candidate_application_id' => $application->id,
                    'candidate_id' => $candidate->id,
                    'job_id' => $job->id,
                    'from_stage' => $fromStage,
                    'to_stage' => $stage,
                    'updated_by' => Auth::id(),
                    'note' => $data['note'] ?? null,
                ]);
                $candidate->update(['status' => $stage === 'REJECTED' ? 'REJECTED' : ($stage === 'INTERVIEW_SCHEDULED' ? 'INTERVIEW' : 'SHORTLISTED')]);
            }
        });

        $audit->log(Auth::id(), 'AI_RANKING_RECRUITER_DECISION', 'candidate_scores', (string) $score->id, $data, $request);

        if ($data['decision'] === 'SCHEDULE_INTERVIEW') {
            return redirect()->route('interviews.create', ['candidate_id' => $candidate->id, 'job_id' => $job->id])
                ->with('status', 'Candidate moved to interview stage. Complete the interview schedule.');
        }

        return back()->with('status', 'Recruiter decision saved');
    }

    private function authorizeTenant(Job $job): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $job->company_id !== $user->company_id) {
            abort(404);
        }
    }

    private function authorizeCandidate(Candidate $candidate): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $candidate->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
