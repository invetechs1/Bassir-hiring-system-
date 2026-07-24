<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
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
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(Request $request, TenantService $tenant): View
    {
        $archived = $request->boolean('archived');
        $candidates = $tenant->scope(Candidate::query(), Auth::user())
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->with(['skills', 'languages', 'scores'])
            ->when($request->q, fn ($query) => $query->where(function ($inner) use ($request) {
                $inner->where('full_name', 'like', "%{$request->q}%")
                    ->orWhere('email', 'like', "%{$request->q}%")
                    ->orWhere('phone', 'like', "%{$request->q}%")
                    ->orWhere('title', 'like', "%{$request->q}%");
            }))
            ->when($request->status, fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('candidates.index', compact('candidates', 'archived'));
    }

    public function create(): View
    {
        return view('candidates.create', [
            'specializations' => Specialization::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCandidateRequest $request, DuplicateDetectionService $duplicates, AuditService $audit, TenantService $tenant, CandidateQualityService $quality): RedirectResponse|JsonResponse
    {
        $companyId = $tenant->defaultCompanyId(Auth::user());
        $data = $request->validated();
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

    public function update(UpdateCandidateRequest $request, Candidate $candidate, DuplicateDetectionService $duplicates, AuditService $audit, CandidateQualityService $quality): RedirectResponse
    {
        $this->authorizeTenant($candidate);
        $data = $request->validated();

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

    public function destroy(Candidate $candidate, AuditService $audit, Request $request): RedirectResponse
    {
        $this->authorizeTenant($candidate);
        $candidate->delete(); // soft delete (archive) — recoverable
        $audit->log(Auth::id(), 'CANDIDATE_ARCHIVE', 'candidates', (string) $candidate->id, [], $request);

        return redirect()->route('candidates.index')->with('status', 'Candidate archived. You can restore it from the archived view.');
    }

    public function restore(int $candidate, AuditService $audit, Request $request): RedirectResponse
    {
        $model = Candidate::withTrashed()->findOrFail($candidate);
        $this->authorizeTenant($model);
        $model->restore();
        $audit->log(Auth::id(), 'CANDIDATE_RESTORE', 'candidates', (string) $model->id, [], $request);

        return redirect()->route('candidates.show', $model)->with('status', 'Candidate restored.');
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
