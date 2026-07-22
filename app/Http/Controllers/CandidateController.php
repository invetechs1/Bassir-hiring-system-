<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Specialization;
use App\Models\Tag;
use App\Services\AuditService;
use App\Services\CandidateQualityService;
use App\Services\DuplicateDetectionService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(Request $request, TenantService $tenant): View
    {
        $candidates = $tenant->scope(Candidate::query(), Auth::user())
            ->with(['skills', 'languages', 'scores'])
            ->when($request->q, fn ($query) => $query->where(function ($inner) use ($request) {
                $inner->where('full_name', 'like', "%{$request->q}%")
                    ->orWhere('email', 'like', "%{$request->q}%")
                    ->orWhere('phone', 'like', "%{$request->q}%")
                    ->orWhere('title', 'like', "%{$request->q}%");
            }))
            ->when($request->status, fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return view('candidates.index', compact('candidates'));
    }

    public function create(): View
    {
        return view('candidates.create', [
            'specializations' => Specialization::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, DuplicateDetectionService $duplicates, AuditService $audit, TenantService $tenant, CandidateQualityService $quality): RedirectResponse|JsonResponse
    {
        $companyId = $tenant->defaultCompanyId(Auth::user());
        $data = $this->validated($request, $companyId);
        $data['company_id'] = $companyId;
        if ($data['consent_status'] === 'CONSENTED') {
            $data['consent_captured_at'] = now()->toDateString();
            $data['consent_captured_by'] = Auth::id();
            $data['contact_allowed'] = true;
        }
        $data['duplicate_hash'] = $duplicates->hash($data);

        $existing = $tenant->scope(Candidate::query(), Auth::user())
            ->where(function ($query) use ($data) {
                $query->where('duplicate_hash', $data['duplicate_hash'])
                    ->when($data['email'] ?? null, fn ($q) => $q->orWhere('email', $data['email']))
                    ->when($data['phone'] ?? null, fn ($q) => $q->orWhere('phone', $data['phone']));
            })
            ->first();
        if ($existing) {
            return back()->withErrors(['email' => 'Possible duplicate candidate exists.']);
        }

        $candidate = DB::transaction(function () use ($data, $request) {
            $candidate = Candidate::create($data);
            $this->syncList($candidate, 'skills', $request->input('skills', ''));
            $this->syncList($candidate, 'languages', $request->input('languages', ''));
            $candidate->sources()->create([
                'source_type' => $request->input('source_type', 'Manual Import'),
                'source_url' => $request->input('source_url'),
                'consent_note' => $candidate->consent_status === 'CONSENTED' ? 'Consent recorded.' : 'Consent pending.',
                'consent_captured_at' => $candidate->consent_status === 'CONSENTED' ? now() : null,
                'consent_captured_by' => $candidate->consent_status === 'CONSENTED' ? Auth::id() : null,
                'contact_allowed' => $candidate->consent_status === 'CONSENTED',
            ]);

            return $candidate;
        });
        $freshCandidate = $candidate->fresh(['skills', 'languages', 'education', 'certifications', 'experience', 'documents', 'interviews.feedback', 'scores']);
        if ($freshCandidate) {
            $quality->update($freshCandidate);
        }
        $audit->log(Auth::id(), 'CANDIDATE_CREATE', 'candidates', (string) $candidate->id, [], $request);

        return redirect()->route('candidates.show', $candidate)->with('status', 'Candidate created');
    }

    public function show(Candidate $candidate): View
    {
        $this->authorizeTenant($candidate);
        $candidate->load([
            'skills',
            'languages',
            'documents',
            'sources',
            'scores',
            'notes',
            'communications',
            'tags',
            'talentPools',
            'experience',
            'education',
            'certifications',
        ]);
        return view('candidates.show', compact('candidate'));
    }

    public function edit(Candidate $candidate): View
    {
        $this->authorizeTenant($candidate);
        $candidate->load('skills', 'languages');

        return view('candidates.edit', [
            'candidate' => $candidate,
            'specializations' => Specialization::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Candidate $candidate, DuplicateDetectionService $duplicates, AuditService $audit, CandidateQualityService $quality): RedirectResponse
    {
        $this->authorizeTenant($candidate);
        $data = $this->validated($request, $candidate->company_id, $candidate->id);

        // Capture consent metadata when a candidate is newly marked as consented.
        if ($data['consent_status'] === 'CONSENTED' && $candidate->consent_status !== 'CONSENTED') {
            $data['consent_captured_at'] = now()->toDateString();
            $data['consent_captured_by'] = Auth::id();
            $data['contact_allowed'] = true;
        }
        $data['duplicate_hash'] = $duplicates->hash($data);

        DB::transaction(function () use ($candidate, $data, $request) {
            $candidate->update($data);
            $candidate->skills()->delete();
            $candidate->languages()->delete();
            $this->syncList($candidate, 'skills', $request->input('skills', ''));
            $this->syncList($candidate, 'languages', $request->input('languages', ''));
        });

        $fresh = $candidate->fresh(['skills', 'languages', 'education', 'certifications', 'experience', 'documents', 'interviews.feedback', 'scores']);
        if ($fresh) {
            $quality->update($fresh);
        }
        $audit->log(Auth::id(), 'CANDIDATE_UPDATE', 'candidates', (string) $candidate->id, [], $request);

        return redirect()->route('candidates.show', $candidate)->with('status', 'Candidate updated');
    }

    public function action(Request $request, Candidate $candidate, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($candidate);
        $data = $request->validate([
            'status' => ['nullable', 'in:NEW,REVIEWED,SHORTLISTED,INTERVIEW,OFFER,HIRED,REJECTED,BLACKLISTED'],
            'note' => ['nullable', 'string', 'max:3000'],
            'tags' => ['nullable', 'string'],
            'communication_channel' => ['nullable', 'in:Email,WhatsApp,Phone,SMS,LinkedIn,Other'],
            'communication_direction' => ['nullable', 'in:OUTBOUND,INBOUND'],
            'communication_subject' => ['nullable', 'string', 'max:180'],
            'communication_body' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($candidate, $data) {
            if (! empty($data['status'])) {
                $candidate->update(['status' => $data['status']]);
            }
            if (! empty($data['note'])) {
                $candidate->notes()->create(['author_id' => Auth::id(), 'body' => $data['note']]);
            }
            if (! empty($data['communication_channel']) && ! empty($data['communication_direction']) && ! empty($data['communication_body'])) {
                $candidate->communications()->create([
                    'channel' => $data['communication_channel'],
                    'direction' => $data['communication_direction'],
                    'subject' => $data['communication_subject'] ?? null,
                    'body' => $data['communication_body'],
                    'sent_at' => now(),
                ]);
            }
            foreach ($this->split($data['tags'] ?? '') as $tagName) {
                $candidate->tags()->syncWithoutDetaching(Tag::firstOrCreate(['name' => $tagName]));
            }
        });
        $audit->log(Auth::id(), 'CANDIDATE_ACTION', 'candidates', (string) $candidate->id, $data, $request);

        return back()->with('status', 'Candidate updated');
    }

    private function validated(Request $request, ?int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', $this->tenantUniqueRule('email', $companyId, $ignoreId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin_url' => ['nullable', 'url', $this->tenantUniqueRule('linkedin_url', $companyId, $ignoreId)],
            'title' => ['required', 'string', 'max:120'],
            'current_company' => ['nullable', 'string', 'max:160'],
            'specialization' => ['required', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['nullable', 'string', 'max:40'],
            'notice_period' => ['nullable', 'string', 'max:80'],
            'recruiter_rating' => ['nullable', 'integer', 'min:0', 'max:100'],
            'consent_status' => ['required', 'in:CONSENTED,PENDING,WITHDRAWN'],
            'status' => ['nullable', 'in:NEW,REVIEWED,SHORTLISTED,INTERVIEW,OFFER,HIRED,REJECTED,BLACKLISTED'],
        ]);
    }

    private function tenantUniqueRule(string $column, ?int $companyId, ?int $ignoreId = null)
    {
        $rule = Rule::unique('candidates', $column)->where(function ($query) use ($companyId) {
            return is_null($companyId) ? $query->whereNull('company_id') : $query->where('company_id', $companyId);
        });

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }

    private function syncList(Candidate $candidate, string $relation, string $value): void
    {
        foreach ($this->split($value) as $name) {
            $candidate->{$relation}()->firstOrCreate(['name' => $name]);
        }
    }

    private function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value))));
    }

    private function authorizeTenant(Candidate $candidate): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $candidate->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
