<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSearchJob;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Job;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function summary(Request $request, TenantService $tenant): JsonResponse
    {
        $user = $request->user();
        if (! $user?->hasPermission('dashboard.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $candidateQuery = $tenant->scope(Candidate::query(), $user);
        $interviewQuery = $tenant->scope(Interview::query(), $user);
        $jobQuery = $tenant->scope(Job::query(), $user);
        $aiSearchQuery = $tenant->scope(AiSearchJob::query(), $user);

        $kpis = [
            'total_candidates' => (clone $candidateQuery)->count(),
            'new_candidates_30d' => (clone $candidateQuery)->whereDate('created_at', '>=', now()->subDays(30))->count(),
            'shortlisted' => (clone $candidateQuery)->where('status', 'SHORTLISTED')->count(),
            'rejected' => (clone $candidateQuery)->where('status', 'REJECTED')->count(),
            'scheduled_interviews' => (clone $interviewQuery)->where('status', 'SCHEDULED')->count(),
            'open_jobs' => (clone $jobQuery)->whereIn('approval_status', ['PENDING', 'APPROVED'])->count(),
            'ai_search_runs_30d' => (clone $aiSearchQuery)->whereDate('created_at', '>=', now()->subDays(30))->count(),
        ];

        $statusBreakdown = $tenant->scope(Candidate::query(), $user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $specializationBreakdown = $tenant->scope(Candidate::query(), $user)
            ->selectRaw('specialization, count(*) as total')
            ->groupBy('specialization')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return response()->json([
            'kpis' => $kpis,
            'status_breakdown' => $statusBreakdown,
            'specialization_breakdown' => $specializationBreakdown,
        ]);
    }
}
