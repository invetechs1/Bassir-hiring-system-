<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalJobController extends Controller
{
    public function index(Request $request, TenantService $tenant): JsonResponse
    {
        if (! $request->user()?->hasPermission('job.read')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'approval_status' => ['nullable', 'in:DRAFT,PENDING,APPROVED,CLOSED'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $rows = $tenant->scope(Job::query(), $request->user())
            ->with('requiredSkills')
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where(function ($inner) use ($q) {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('department', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            }))
            ->when($data['approval_status'] ?? null, fn ($query, $status) => $query->where('approval_status', $status))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 50));

        return response()->json($rows);
    }
}
