<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalCandidateController extends Controller
{
    public function index(Request $request, TenantService $tenant): JsonResponse
    {
        if (! $request->user()?->hasPermission('candidate.read')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:NEW,REVIEWED,SHORTLISTED,INTERVIEW,OFFER,HIRED,REJECTED,BLACKLISTED'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $rows = $tenant->scope(Candidate::query(), $request->user())
            ->with(['skills', 'languages', 'scores'])
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where(function ($inner) use ($q) {
                $inner->where('full_name', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('specialization', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 50));

        return response()->json($rows);
    }
}
