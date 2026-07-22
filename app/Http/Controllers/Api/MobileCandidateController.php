<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Tag;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCandidateController extends Controller
{
    public function index(Request $request, TenantService $tenant): JsonResponse
    {
        if (! $request->user()?->hasPermission('candidate.read')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $maxPageSize = max(10, (int) config('bassir.mobile_api_max_page_size', 100));
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:NEW,REVIEWED,SHORTLISTED,INTERVIEW,OFFER,HIRED,REJECTED,BLACKLISTED'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:'.$maxPageSize],
        ]);

        $perPage = (int) ($data['per_page'] ?? 25);
        $rows = $tenant->scope(Candidate::query(), $request->user())
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where(function ($inner) use ($q) {
                $inner->where('full_name', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('specialization', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->select([
                'id',
                'full_name',
                'title',
                'specialization',
                'city',
                'country',
                'years_experience',
                'expected_salary',
                'status',
                'consent_status',
                'created_at',
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json($rows);
    }

    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        if (! $request->user()?->hasPermission('candidate.read')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->user() && ! $request->user()->isSuperAdmin() && $candidate->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $candidate->load([
            'skills',
            'languages',
            'sources',
            'documents',
            'scores',
            'tags',
            'notes',
            'communications',
            'experience',
            'education',
            'certifications',
        ]);

        return response()->json([
            'candidate' => $candidate,
        ]);
    }

    public function updateStatus(Request $request, Candidate $candidate, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('candidate.write')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if (! $user->isSuperAdmin() && $candidate->company_id !== $user->company_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:NEW,REVIEWED,SHORTLISTED,INTERVIEW,OFFER,HIRED,REJECTED,BLACKLISTED'],
            'note' => ['nullable', 'string', 'max:3000'],
            'tags' => ['nullable', 'string', 'max:600'],
        ]);

        $candidate->update(['status' => $data['status']]);
        if (! empty($data['note'])) {
            $candidate->notes()->create([
                'author_id' => $user->id,
                'body' => $data['note'],
            ]);
        }
        foreach ($this->split($data['tags'] ?? '') as $tagName) {
            $candidate->tags()->syncWithoutDetaching(Tag::firstOrCreate(['name' => $tagName]));
        }

        $audit->log($user->id, 'MOBILE_CANDIDATE_STATUS_UPDATE', 'candidates', (string) $candidate->id, [
            'status' => $data['status'],
        ], $request);

        return response()->json(['message' => 'Candidate updated']);
    }

    private function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value))));
    }
}
