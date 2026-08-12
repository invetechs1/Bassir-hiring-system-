<?php

namespace App\Http\Controllers;

use App\Models\CandidateApplication;
use App\Models\Offer;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(TenantService $tenant): View
    {
        return view('offers.index', [
            'offers' => $tenant->scope(Offer::with(['candidate', 'job']), Auth::user())->latest()->get(),
        ]);
    }

    public function create(CandidateApplication $application, TenantService $tenant): View
    {
        $this->authorizeTenant($application, $tenant);

        return view('offers.create', ['application' => $application->load('candidate', 'job')]);
    }

    public function store(Request $request, CandidateApplication $application, TenantService $tenant, AuditService $audit): RedirectResponse
    {
        $this->authorizeTenant($application, $tenant);
        $data = $request->validate([
            'salary_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'start_date' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'string', 'max:80'],
            'terms' => ['nullable', 'string', 'max:4000'],
        ]);

        $offer = Offer::create($data + [
            'company_id' => $application->company_id,
            'candidate_application_id' => $application->id,
            'candidate_id' => $application->candidate_id,
            'job_id' => $application->job_id,
            'currency' => $data['currency'] ?? 'SAR',
            'status' => 'PENDING_APPROVAL',
            'created_by' => Auth::id(),
        ]);

        $audit->log(Auth::id(), 'OFFER_CREATE', 'offers', (string) $offer->id, [], $request);

        return redirect()->route('offers.index')->with('status', 'Offer drafted and awaiting approval');
    }

    public function approve(Offer $offer, AuditService $audit): RedirectResponse
    {
        $offer->update(['status' => 'APPROVED', 'approved_by' => Auth::id(), 'approved_at' => now()]);
        $audit->log(Auth::id(), 'OFFER_APPROVE', 'offers', (string) $offer->id, [], request());

        return back()->with('status', 'Offer approved');
    }

    public function send(Offer $offer, AuditService $audit, NotificationService $notifications): RedirectResponse
    {
        if ($offer->status !== 'APPROVED') {
            return back()->withErrors(['offer' => 'Only approved offers can be sent.']);
        }

        $offer->update(['status' => 'SENT', 'sent_at' => now()]);
        $offer->application()->update(['current_stage' => 'OFFER_SENT']);
        $offer->candidate?->update(['status' => 'OFFER']);

        $notifications->offerSent($offer->load('candidate', 'job', 'company'));
        $audit->log(Auth::id(), 'OFFER_SEND', 'offers', (string) $offer->id, [], request());

        return back()->with('status', 'Offer sent to candidate');
    }

    private function authorizeTenant(CandidateApplication $application, TenantService $tenant): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $application->company_id !== $tenant->defaultCompanyId($user)) {
            abort(404);
        }
    }
}
