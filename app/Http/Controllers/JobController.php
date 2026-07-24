<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Jobs\RankJobCandidates;
use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\Job;
use App\Models\Specialization;
use App\Services\AiInsightsService;
use App\Services\AuditService;
use App\Services\CandidateScoringService;
use App\Services\SalaryEstimatorService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request, TenantService $tenant): View
    {
        $archived = $request->boolean('archived');
        $jobs = $tenant->scope(Job::with('requiredSkills'), Auth::user())
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('jobs.index', compact('jobs', 'archived'));
    }

    public function create(): View
    {
        return view('jobs.create', [
            'specializations' => Specialization::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreJobRequest $request, AuditService $audit, TenantService $tenant): RedirectResponse
    {
        $data = $request->validated();
        $data['company_id'] = $tenant->defaultCompanyId(Auth::user());
        $data['recruiter_id'] = Auth::id();
        $data['employment_type'] = $data['employment_type'] ?? 'Full-time';
        $data['public_slug'] = $this->uniqueSlug($data['title'].' '.$data['location']);
        $job = Job::create($data);
        foreach ($this->split($request->input('required_skills', '')) as $name) {
            $job->requiredSkills()->firstOrCreate(['name' => $name]);
        }
        RankJobCandidates::dispatch($job->id); // sync inline by default; background with a queue
        $audit->log(Auth::id(), 'JOB_CREATE', 'jobs', (string) $job->id, [], $request);
        return redirect()->route('jobs.show', $job)->with('status', 'Job created');
    }

    public function show(Job $job): View
    {
        $this->authorizeTenant($job);
        $job->load('requiredSkills', 'scores.candidate');
        return view('jobs.show', compact('job'));
    }

    public function edit(Job $job): View
    {
        $this->authorizeTenant($job);
        $job->load('requiredSkills');

        return view('jobs.edit', [
            'job' => $job,
            'specializations' => Specialization::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateJobRequest $request, Job $job, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($job);
        $data = $request->validated();
        $data['employment_type'] = $data['employment_type'] ?? $job->employment_type ?? 'Full-time';
        $job->update($data);

        $job->requiredSkills()->delete();
        foreach ($this->split($request->input('required_skills', '')) as $name) {
            $job->requiredSkills()->firstOrCreate(['name' => $name]);
        }
        RankJobCandidates::dispatch($job->id); // sync inline by default; background with a queue
        $audit->log(Auth::id(), 'JOB_UPDATE', 'jobs', (string) $job->id, [], $request);

        return redirect()->route('jobs.show', $job)->with('status', 'Job updated');
    }

    public function destroy(Job $job, AuditService $audit, Request $request): RedirectResponse
    {
        $this->authorizeTenant($job);
        $job->delete(); // soft delete (archive) — recoverable
        $audit->log(Auth::id(), 'JOB_ARCHIVE', 'jobs', (string) $job->id, [], $request);

        return redirect()->route('jobs.index')->with('status', 'Job archived. You can restore it from the archived view.');
    }

    public function restore(int $job, AuditService $audit, Request $request): RedirectResponse
    {
        $model = Job::withTrashed()->findOrFail($job);
        $this->authorizeTenant($model);
        $model->restore();
        $audit->log(Auth::id(), 'JOB_RESTORE', 'jobs', (string) $model->id, [], $request);

        return redirect()->route('jobs.show', $model)->with('status', 'Job restored.');
    }

    public function match(
        Job $job,
        CandidateScoringService $scoring,
        AiInsightsService $insights,
        SalaryEstimatorService $salaryEstimator,
        AuditService $audit,
        Request $request
    ): RedirectResponse
    {
        $this->authorizeTenant($job);
        $job->load('requiredSkills');
        $candidates = Candidate::with(['skills', 'languages', 'education', 'certifications', 'experience'])
            ->when($job->company_id, fn ($query) => $query->where('company_id', $job->company_id))
            ->whereNotIn('status', ['REJECTED', 'BLACKLISTED'])
            ->take(200)
            ->get();
        DB::transaction(function () use ($candidates, $job, $scoring) {
            foreach ($candidates as $candidate) {
                $score = $scoring->score($candidate, $job);
                CandidateScore::updateOrCreate([
                    'candidate_id' => $candidate->id,
                    'job_id' => $job->id,
                ], $score);
            }
        });
        $scoreRows = CandidateScore::where('job_id', $job->id)
            ->when($job->company_id, fn ($query) => $query->whereHas('candidate', fn ($candidate) => $candidate->where('company_id', $job->company_id)))
            ->get();

        // Generate richer AI shortlisting intelligence for top-ranked candidates.
        $insightLimit = 12;
        foreach ($scoreRows->sortByDesc('overall')->take($insightLimit) as $score) {
            $candidate = $candidates->firstWhere('id', $score->candidate_id);
            if (! $candidate) {
                continue;
            }

            $ai = $insights->candidateInsight(
                [
                    'full_name' => $candidate->full_name,
                    'title' => $candidate->title,
                    'specialization' => $candidate->specialization,
                    'skills' => $candidate->skills->pluck('name')->all(),
                    'years_experience' => $candidate->years_experience,
                    'expected_salary' => $candidate->expected_salary,
                    'location' => trim(($candidate->city ?? '').' '.($candidate->country ?? '')),
                ],
                [
                    'title' => $job->title,
                    'required_skills' => $job->requiredSkills->pluck('name')->all(),
                    'location' => $job->location,
                ]
            );

            $salary = $salaryEstimator->estimate([
                'years_experience' => $candidate->years_experience,
                'skills' => $candidate->skills->pluck('name')->all(),
                'benchmark_min' => $job->salary_budget_min,
                'benchmark_max' => $job->salary_budget_max,
                'location' => $job->location,
            ]);

            $rationale = $score->rationale ?? [];
            $rationale['ai_summary'] = $ai['summary'];
            $rationale['strength_points'] = $ai['strength_points'] ?? [];
            $rationale['weakness_points'] = $ai['weakness_points'] ?? [];
            $rationale['best_roles'] = $ai['best_roles'] ?? [];
            $rationale['interview_questions'] = $ai['interview_questions'] ?? [];
            $rationale['risk_notes'] = $ai['risk_notes'] ?? [];
            $rationale['missing_skills'] = $ai['missing_skills'] ?? ($rationale['missing_skills'] ?? []);
            $rationale['salary_strategy'] = $salary['negotiation_recommendation'] ?? null;
            $rationale['confidence'] = $ai['confidence'] ?? ($rationale['confidence'] ?? 70);
            $rationale['human_review_required'] = true;
            $rationale['ai_disclaimer'] = $ai['ai_disclaimer'] ?? 'AI assists HR review only and must not be used as the sole hiring decision.';

            $score->update([
                'recommendation' => $ai['hiring_recommendation'] ?? $score->recommendation,
                'confidence' => $ai['confidence'] ?? $score->confidence,
                'prompt_version' => 'openai-v1',
                'rationale' => $rationale,
            ]);
        }
        $audit->log(Auth::id(), 'JOB_MATCH_PERSIST', 'jobs', (string) $job->id, ['count' => $candidates->count()], $request);
        return back()->with('status', 'Matching completed with AI shortlist intelligence');
    }

    private function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value))));
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'job';
        $slug = $base;
        $counter = 2;
        while (Job::where('public_slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function authorizeTenant(Job $job): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $job->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
