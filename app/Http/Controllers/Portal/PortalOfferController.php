<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OnboardingRecord;
use App\Models\PipelineStageHistory;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalOfferController extends Controller
{
    public function show(Offer $offer): View
    {
        $this->authorizeOffer($offer);

        return view('portal.offers.show', ['offer' => $offer->load('job')]);
    }

    public function accept(Offer $offer, AuditService $audit): RedirectResponse
    {
        $this->authorizeOffer($offer);
        if (! in_array($offer->status, ['SENT'], true)) {
            return back()->withErrors(['offer' => 'This offer is no longer awaiting a response.']);
        }

        DB::transaction(function () use ($offer) {
            $offer->update(['status' => 'ACCEPTED', 'responded_at' => now()]);
            $offer->application->update(['current_stage' => 'HIRED', 'status' => 'CLOSED']);
            PipelineStageHistory::create([
                'company_id' => $offer->company_id,
                'candidate_application_id' => $offer->candidate_application_id,
                'candidate_id' => $offer->candidate_id,
                'job_id' => $offer->job_id,
                'from_stage' => 'OFFER_SENT',
                'to_stage' => 'HIRED',
                'note' => 'Candidate accepted the offer via the self-service portal.',
            ]);
            $offer->candidate->update(['status' => 'HIRED']);

            $onboarding = OnboardingRecord::create([
                'company_id' => $offer->company_id,
                'candidate_id' => $offer->candidate_id,
                'candidate_application_id' => $offer->candidate_application_id,
                'offer_id' => $offer->id,
                'status' => 'IN_PROGRESS',
                'started_at' => now(),
            ]);
            foreach ([
                ['title' => 'Upload signed offer letter', 'type' => 'DOCUMENT_UPLOAD'],
                ['title' => 'Upload government ID', 'type' => 'DOCUMENT_UPLOAD'],
                ['title' => 'Acknowledge company policies', 'type' => 'ACKNOWLEDGEMENT'],
                ['title' => 'Complete payroll/bank details form', 'type' => 'TASK'],
            ] as $index => $task) {
                $onboarding->tasks()->create($task + ['sort_order' => $index]);
            }
        });

        $audit->log(null, 'OFFER_ACCEPTED', 'offers', (string) $offer->id, [], request());

        return back()->with('status', 'Offer accepted — welcome aboard! Onboarding tasks have been created for you.');
    }

    public function decline(Offer $offer, AuditService $audit): RedirectResponse
    {
        $this->authorizeOffer($offer);
        if (! in_array($offer->status, ['SENT'], true)) {
            return back()->withErrors(['offer' => 'This offer is no longer awaiting a response.']);
        }

        DB::transaction(function () use ($offer) {
            $offer->update(['status' => 'DECLINED', 'responded_at' => now()]);
            $offer->application->update(['current_stage' => 'WITHDRAWN', 'status' => 'CLOSED']);
            PipelineStageHistory::create([
                'company_id' => $offer->company_id,
                'candidate_application_id' => $offer->candidate_application_id,
                'candidate_id' => $offer->candidate_id,
                'job_id' => $offer->job_id,
                'from_stage' => 'OFFER_SENT',
                'to_stage' => 'WITHDRAWN',
                'note' => 'Candidate declined the offer via the self-service portal.',
            ]);
        });

        $audit->log(null, 'OFFER_DECLINED', 'offers', (string) $offer->id, [], request());

        return back()->with('status', 'Offer declined.');
    }

    private function authorizeOffer(Offer $offer): void
    {
        $account = Auth::guard('candidate')->user();
        if ($offer->candidate_id !== $account->candidate_id) {
            abort(404);
        }
    }
}
