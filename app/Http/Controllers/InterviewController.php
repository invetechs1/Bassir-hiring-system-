<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\Job;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InterviewController extends Controller
{
    public function index(TenantService $tenant): View
    {
        return view('interviews.index', [
            'interviews' => $tenant->scope(Interview::with(['candidate', 'job']), Auth::user())->orderBy('starts_at')->paginate(30),
        ]);
    }

    public function create(Request $request, TenantService $tenant): View
    {
        return view('interviews.create', [
            'candidates' => $tenant->scope(Candidate::query(), Auth::user())->orderBy('full_name')->get(),
            'jobs' => $tenant->scope(Job::query(), Auth::user())->orderBy('title')->get(),
            'selectedCandidateId' => (int) $request->input('candidate_id'),
            'selectedJobId' => (int) $request->input('job_id'),
        ]);
    }

    public function store(Request $request, AuditService $audit, TenantService $tenant): RedirectResponse
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'exists:candidates,id'],
            'job_id' => ['nullable', 'exists:jobs,id'],
            'interview_type' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'channel' => ['nullable', 'string', 'max:80'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'in:SCHEDULED,COMPLETED,CANCELLED,NO_SHOW'],
        ]);
        $data['company_id'] = $tenant->defaultCompanyId(Auth::user());
        if (! Auth::user()?->isSuperAdmin()) {
            $candidateAllowed = Candidate::where('id', $data['candidate_id'])->where('company_id', $data['company_id'])->exists();
            $jobAllowed = empty($data['job_id']) || Job::where('id', $data['job_id'])->where('company_id', $data['company_id'])->exists();
            if (! $candidateAllowed || ! $jobAllowed) {
                abort(404);
            }
        }
        $interview = Interview::create($data);
        $audit->log(Auth::id(), 'INTERVIEW_CREATE', 'interviews', (string) $interview->id, $data, $request);

        return redirect()->route('interviews.index')->with('status', 'Interview scheduled');
    }

    public function feedback(Request $request, Interview $interview, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($interview);
        $data = $request->validate([
            'technical_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'hr_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'recommendation' => ['nullable', 'string', 'max:120'],
            'comments' => ['nullable', 'string', 'max:4000'],
        ]);
        $feedback = InterviewFeedback::create($data + [
            'interview_id' => $interview->id,
            'evaluator_id' => Auth::id(),
        ]);
        $audit->log(Auth::id(), 'INTERVIEW_FEEDBACK_CREATE', 'interview_feedback', (string) $feedback->id, $data, $request);

        return back()->with('status', 'Interview feedback saved');
    }

    private function authorizeTenant(Interview $interview): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $interview->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
