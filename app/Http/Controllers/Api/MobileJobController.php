<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\AuditService;
use App\Services\CandidateScoringService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileJobController extends Controller
{
    public function index(Request $request, TenantService $tenant): JsonResponse
    {
        if (! $request->user()?->hasPermission('job.read')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $maxPageSize = max(10, (int) config('bassir.mobile_api_max_page_size', 100));
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'approval_status' => ['nullable', 'in:DRAFT,PENDING,APPROVED,CLOSED'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:'.$maxPageSize],
        ]);

        $rows = $tenant->scope(Job::query(), $request->user())
            ->withCount('scores')
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where(function ($inner) use ($q) {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('department', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            }))
            ->when($data['approval_status'] ?? null, fn ($query, $status) => $query->where('approval_status', $status))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 25));

        return response()->json($rows);
    }

    public function show(Request $request, Job $job): JsonResponse
    {
        if (! $request->user()?->hasPermission('job.read')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->user() && ! $request->user()->isSuperAdmin() && $job->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $job->load('requiredSkills');
        $topCandidates = $job->scores()
            ->with('candidate')
            ->orderByDesc('overall')
            ->take(20)
            ->get();

        return response()->json([
            'job' => $job,
            'top_candidates' => $topCandidates,
        ]);
    }

    public function match(Job $job, CandidateScoringService $scoring, AuditService $audit, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('job.match')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if (! $user->isSuperAdmin() && $job->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Reuse existing web matching action indirectly by scoring candidates here for mobile command.
        $job->load('requiredSkills');
        $candidates = \App\Models\Candidate::with(['skills', 'languages'])
            ->when($job->company_id, fn ($query) => $query->where('company_id', $job->company_id))
            ->whereNotIn('status', ['REJECTED', 'BLACKLISTED'])
            ->take(200)
            ->get();

        foreach ($candidates as $candidate) {
            $score = $scoring->score($candidate, $job);
            $job->scores()->updateOrCreate(
                ['candidate_id' => $candidate->id, 'job_id' => $job->id],
                $score
            );
        }

        $audit->log($user->id, 'MOBILE_JOB_MATCH', 'jobs', (string) $job->id, ['count' => $candidates->count()], $request);

        return response()->json(['message' => 'Matching completed']);
    }
}
