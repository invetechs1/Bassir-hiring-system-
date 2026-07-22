<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Job;
use App\Models\PipelineStageHistory;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CandidateApplicationController extends Controller
{
    public const STAGES = [
        'APPLIED' => 'Applied',
        'AI_REVIEWED' => 'AI Reviewed',
        'SHORTLISTED' => 'Shortlisted',
        'PHONE_SCREENING' => 'Phone Screening',
        'INTERVIEW_SCHEDULED' => 'Interview Scheduled',
        'INTERVIEWED' => 'Interviewed',
        'OFFER_SENT' => 'Offer Sent',
        'HIRED' => 'Hired',
        'REJECTED' => 'Rejected',
        'WITHDRAWN' => 'Withdrawn',
    ];

    public function index(Request $request, TenantService $tenant): View
    {
        $user = Auth::user();
        $applications = $tenant->scope(CandidateApplication::with(['candidate.scores', 'job', 'reviewer']), $user)
            ->when($request->filled('job_id'), fn ($query) => $query->where('job_id', $request->integer('job_id')))
            ->when($request->filled('stage'), fn ($query) => $query->where('current_stage', $request->stage))
            ->latest('updated_at')
            ->get();

        return view('applications.index', [
            'applications' => $applications,
            'groupedApplications' => $applications->groupBy('current_stage'),
            'stages' => self::STAGES,
            'jobs' => $tenant->scope(Job::query(), $user)->orderByDesc('created_at')->get(['id', 'title', 'location']),
            'candidates' => $tenant->scope(Candidate::query(), $user)->orderBy('full_name')->take(300)->get(['id', 'full_name', 'title']),
        ]);
    }

    public function store(Request $request, TenantService $tenant, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $candidate = Candidate::findOrFail($data['candidate_id']);
        $job = Job::findOrFail($data['job_id']);
        $this->authorizeEntities($candidate, $job);

        $companyId = $candidate->company_id ?: $job->company_id ?: $tenant->defaultCompanyId(Auth::user());
        if ($candidate->company_id && $job->company_id && $candidate->company_id !== $job->company_id) {
            return back()->withErrors(['job_id' => 'Candidate and job must belong to the same company.']);
        }

        $application = DB::transaction(function () use ($candidate, $job, $companyId, $data) {
            $application = CandidateApplication::firstOrCreate([
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ], [
                'company_id' => $companyId,
                'source' => $data['source'] ?? 'Internal',
                'current_stage' => 'APPLIED',
                'status' => 'ACTIVE',
                'reviewed_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            PipelineStageHistory::firstOrCreate([
                'candidate_application_id' => $application->id,
                'to_stage' => 'APPLIED',
            ], [
                'company_id' => $companyId,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'updated_by' => Auth::id(),
                'note' => $data['notes'] ?? 'Application created.',
            ]);

            return $application;
        });

        $audit->log(Auth::id(), 'APPLICATION_CREATE', 'candidate_applications', (string) $application->id, [
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
        ], $request);

        return redirect()->route('applications.index')->with('status', 'Application added to recruitment pipeline');
    }

    public function updateStage(Request $request, CandidateApplication $application, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($application);
        $data = $request->validate([
            'current_stage' => ['required', 'in:'.implode(',', array_keys(self::STAGES))],
            'note' => ['nullable', 'string', 'max:3000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $fromStage = $application->current_stage;
        DB::transaction(function () use ($application, $fromStage, $data) {
            $application->update([
                'current_stage' => $data['current_stage'],
                'status' => in_array($data['current_stage'], ['HIRED', 'REJECTED', 'WITHDRAWN'], true) ? 'CLOSED' : 'ACTIVE',
                'reviewed_by' => Auth::id(),
                'notes' => $data['note'] ?? $application->notes,
            ]);

            PipelineStageHistory::create([
                'company_id' => $application->company_id,
                'candidate_application_id' => $application->id,
                'candidate_id' => $application->candidate_id,
                'job_id' => $application->job_id,
                'from_stage' => $fromStage,
                'to_stage' => $data['current_stage'],
                'updated_by' => Auth::id(),
                'note' => $data['note'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);

            if ($application->candidate) {
                $application->candidate->update(['status' => $this->candidateStatusForStage($data['current_stage'])]);
            }
        });

        $audit->log(Auth::id(), 'APPLICATION_STAGE_CHANGE', 'candidate_applications', (string) $application->id, [
            'from_stage' => $fromStage,
            'to_stage' => $data['current_stage'],
        ], $request);

        return back()->with('status', 'Pipeline stage updated');
    }

    private function authorizeEntities(Candidate $candidate, Job $job): void
    {
        $user = Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        if ($candidate->company_id !== $user->company_id || $job->company_id !== $user->company_id) {
            abort(404);
        }
    }

    private function authorizeTenant(CandidateApplication $application): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $application->company_id !== $user->company_id) {
            abort(404);
        }
    }

    private function candidateStatusForStage(string $stage): string
    {
        return match ($stage) {
            'AI_REVIEWED' => 'REVIEWED',
            'SHORTLISTED', 'PHONE_SCREENING' => 'SHORTLISTED',
            'INTERVIEW_SCHEDULED', 'INTERVIEWED' => 'INTERVIEW',
            'OFFER_SENT' => 'OFFER',
            'HIRED' => 'HIRED',
            'REJECTED', 'WITHDRAWN' => 'REJECTED',
            default => 'NEW',
        };
    }
}
