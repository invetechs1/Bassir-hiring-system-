<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CandidateAssessment;
use App\Services\AuditService;
use App\Services\FileSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class PortalAssessmentController extends Controller
{
    public function index(): View
    {
        $account = Auth::guard('candidate')->user();
        $assessments = CandidateAssessment::where('candidate_id', $account->candidate_id)
            ->with('assessment.job')
            ->latest()
            ->get();

        return view('portal.assessments.index', compact('assessments'));
    }

    public function show(CandidateAssessment $candidateAssessment): View
    {
        $this->authorize($candidateAssessment);
        $candidateAssessment->load('assessment.questions', 'answers');

        return view('portal.assessments.show', ['candidateAssessment' => $candidateAssessment]);
    }

    public function submit(Request $request, CandidateAssessment $candidateAssessment, AuditService $audit, FileSecurityService $fileSecurity): RedirectResponse
    {
        $this->authorize($candidateAssessment);
        if (! in_array($candidateAssessment->status, ['PENDING', 'IN_PROGRESS'], true)) {
            return back()->withErrors(['assessment' => 'This assessment has already been submitted.']);
        }

        $assessment = $candidateAssessment->assessment()->with('questions')->first();

        if (in_array($assessment->type, ['TEST', 'QUESTIONNAIRE'], true)) {
            $data = $request->validate(['answers' => ['required', 'array']]);
            $totalScore = 0;
            $maxScore = 0;
            $needsManualReview = false;

            DB::transaction(function () use ($assessment, $candidateAssessment, $data, &$totalScore, &$maxScore, &$needsManualReview) {
                foreach ($assessment->questions as $question) {
                    $maxScore += $question->points;
                    $answerText = $data['answers'][$question->id] ?? null;
                    $isCorrect = null;
                    $pointsAwarded = 0;

                    if ($question->question_type === 'MCQ') {
                        $isCorrect = $answerText !== null && trim((string) $answerText) === trim((string) $question->correct_answer);
                        $pointsAwarded = $isCorrect ? $question->points : 0;
                        $totalScore += $pointsAwarded;
                    } else {
                        $needsManualReview = true;
                    }

                    $candidateAssessment->answers()->create([
                        'assessment_question_id' => $question->id,
                        'answer_text' => $answerText,
                        'is_correct' => $isCorrect,
                        'points_awarded' => $pointsAwarded,
                    ]);
                }

                $candidateAssessment->update([
                    'status' => $needsManualReview ? 'SUBMITTED' : 'SCORED',
                    'score' => $totalScore,
                    'max_score' => $maxScore,
                    'submitted_at' => now(),
                ]);
            });
        } else {
            // DOCUMENT_VERIFICATION / BACKGROUND_CHECK — candidate uploads supporting document for staff review.
            $request->validate([
                'document' => ['required', 'file', 'max:'.((int) config('bassir.max_upload_kb', 10240)), 'mimes:pdf,jpg,jpeg,png'],
            ]);
            $file = $request->file('document');
            try {
                $fileSecurity->assertAllowedCv($file);
                $path = $file->store('private/cvs');
            } catch (Throwable $e) {
                return back()->withErrors(['document' => $e->getMessage()]);
            }
            $candidateAssessment->update([
                'status' => 'SUBMITTED',
                'document_path' => $path,
                'submitted_at' => now(),
            ]);
        }

        $audit->log(null, 'CANDIDATE_ASSESSMENT_SUBMIT', 'candidate_assessments', (string) $candidateAssessment->id, [], $request);

        return redirect()->route('portal.assessments.index')->with('status', 'Assessment submitted');
    }

    private function authorize(CandidateAssessment $candidateAssessment): void
    {
        $account = Auth::guard('candidate')->user();
        if ($candidateAssessment->candidate_id !== $account->candidate_id) {
            abort(404);
        }
    }
}
