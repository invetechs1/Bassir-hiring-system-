<?php

namespace App\Http\Controllers;

use App\Mail\AssessmentAssignedMail;
use App\Models\Assessment;
use App\Models\CandidateApplication;
use App\Models\CandidateAssessment;
use App\Models\Job;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class AssessmentController extends Controller
{
    public function index(TenantService $tenant): View
    {
        return view('assessments.index', [
            'assessments' => $tenant->scope(Assessment::with('job'), Auth::user())->withCount('candidateAssessments')->latest()->get(),
            'jobs' => $tenant->scope(Job::query(), Auth::user())->orderBy('title')->get(),
        ]);
    }

    public function create(TenantService $tenant): View
    {
        return view('assessments.create', [
            'jobs' => $tenant->scope(Job::query(), Auth::user())->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request, TenantService $tenant, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'job_id' => ['nullable', 'exists:jobs,id'],
            'type' => ['required', 'in:TEST,QUESTIONNAIRE,DOCUMENT_VERIFICATION,BACKGROUND_CHECK'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'questions' => ['nullable', 'array'],
            'questions.*.question_text' => ['nullable', 'string', 'max:2000'],
            'questions.*.question_type' => ['nullable', 'in:MCQ,TEXT'],
            'questions.*.options' => ['nullable', 'string', 'max:1000'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:255'],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $assessment = Assessment::create([
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
            'job_id' => $data['job_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'passing_score' => $data['passing_score'] ?? null,
            'created_by' => Auth::id(),
        ]);

        foreach ($data['questions'] ?? [] as $index => $row) {
            if (empty($row['question_text'])) {
                continue;
            }
            $assessment->questions()->create([
                'question_text' => $row['question_text'],
                'question_type' => $row['question_type'] ?? 'MCQ',
                'options' => ! empty($row['options']) ? array_values(array_filter(array_map('trim', explode(',', $row['options'])))) : null,
                'correct_answer' => $row['correct_answer'] ?? null,
                'points' => $row['points'] ?? 1,
                'sort_order' => $index,
            ]);
        }

        $audit->log(Auth::id(), 'ASSESSMENT_CREATE', 'assessments', (string) $assessment->id, [], $request);

        return redirect()->route('assessments.index')->with('status', 'Assessment created');
    }

    public function assign(Request $request, Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['candidate_application_id' => ['required', 'exists:candidate_applications,id']]);
        $application = CandidateApplication::with('candidate')->findOrFail($data['candidate_application_id']);

        $candidateAssessment = CandidateAssessment::firstOrCreate([
            'assessment_id' => $assessment->id,
            'candidate_application_id' => $application->id,
        ], [
            'company_id' => $assessment->company_id,
            'candidate_id' => $application->candidate_id,
            'status' => 'PENDING',
        ]);

        if ($candidateAssessment->wasRecentlyCreated && $application->candidate?->email) {
            try {
                Mail::to($application->candidate->email)->send(new AssessmentAssignedMail($assessment, $application->candidate));
            } catch (Throwable $e) {
                Log::warning('Assessment assignment email failed', ['error' => $e->getMessage()]);
            }
        }

        $audit->log(Auth::id(), 'ASSESSMENT_ASSIGN', 'candidate_assessments', (string) $candidateAssessment->id, [], $request);

        return back()->with('status', 'Assessment assigned to candidate');
    }

    public function show(CandidateAssessment $candidateAssessment): View
    {
        $candidateAssessment->load('assessment.questions', 'answers', 'candidate', 'reviewer');

        return view('assessments.show', compact('candidateAssessment'));
    }

    public function review(Request $request, CandidateAssessment $candidateAssessment, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:SCORED,CLEARED,FLAGGED'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $candidateAssessment->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $candidateAssessment->notes,
            'score' => $data['score'] ?? $candidateAssessment->score,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $audit->log(Auth::id(), 'ASSESSMENT_REVIEW', 'candidate_assessments', (string) $candidateAssessment->id, $data, $request);

        return back()->with('status', 'Assessment review saved');
    }
}
