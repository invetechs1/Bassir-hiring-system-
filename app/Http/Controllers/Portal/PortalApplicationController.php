<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CandidateApplication;
use App\Models\Job;
use App\Models\PipelineStageHistory;
use App\Services\AuditService;
use App\Services\FileSecurityService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class PortalApplicationController extends Controller
{
    public function apply(
        Request $request,
        string $company,
        string $job,
        AuditService $audit,
        NotificationService $notifications,
        FileSecurityService $fileSecurity
    ): RedirectResponse {
        $account = Auth::guard('candidate')->user();
        $job = Job::where('company_id', $account->company_id)
            ->where('public_slug', $job)
            ->where('approval_status', 'APPROVED')
            ->firstOrFail();

        $request->validate([
            'cv' => ['nullable', 'file', 'max:'.((int) config('bassir.max_upload_kb', 10240)), 'mimes:pdf,doc,docx'],
        ]);

        $candidate = $account->candidate;

        if ($request->hasFile('cv')) {
            $file = $request->file('cv');
            try {
                $fileSecurity->assertAllowedCv($file);
                $path = $file->store('private/cvs');
                $candidate->documents()->create([
                    'file_name' => $fileSecurity->safeOriginalName($file),
                    'mime_type' => $file->getMimeType(),
                    'storage_path' => $path,
                    'checksum' => hash_file('sha256', Storage::path($path)),
                    'scan_status' => 'COMPLETED',
                    'malware_scan_status' => $fileSecurity->malwareScan(Storage::path($path)),
                ]);
            } catch (Throwable $e) {
                return back()->withErrors(['cv' => $e->getMessage()]);
            }
        }

        $application = DB::transaction(function () use ($candidate, $job, $account) {
            $application = CandidateApplication::firstOrCreate([
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ], [
                'company_id' => $account->company_id,
                'source' => 'Candidate Portal',
                'current_stage' => 'APPLIED',
                'status' => 'ACTIVE',
            ]);

            PipelineStageHistory::firstOrCreate([
                'candidate_application_id' => $application->id,
                'to_stage' => 'APPLIED',
            ], [
                'company_id' => $account->company_id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'note' => 'Candidate applied via self-service portal.',
            ]);

            return $application;
        });

        $audit->log(null, 'PORTAL_APPLICATION_CREATE', 'candidate_applications', (string) $application->id, [
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
        ], $request);

        if ($application->wasRecentlyCreated) {
            $notifications->applicationReceived($application->load('job', 'candidate', 'company'));
        }

        return redirect()->route('portal.applications.show', $application)->with('status', 'Application submitted');
    }

    public function index(): View
    {
        $account = Auth::guard('candidate')->user();
        $applications = CandidateApplication::where('candidate_id', $account->candidate_id)
            ->with('job')
            ->latest('updated_at')
            ->get();

        return view('portal.dashboard.index', ['account' => $account, 'applications' => $applications]);
    }

    public function show(CandidateApplication $application): View
    {
        $this->authorize($application);
        $application->load('job', 'stageHistories', 'offers', 'assessments.assessment');

        return view('portal.dashboard.show', compact('application'));
    }

    private function authorize(CandidateApplication $application): void
    {
        $account = Auth::guard('candidate')->user();
        if ($application->candidate_id !== $account->candidate_id) {
            abort(404);
        }
    }
}
