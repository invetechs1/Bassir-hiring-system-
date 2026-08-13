<?php

namespace App\Services;

use App\Mail\ApplicationReceivedMail;
use App\Mail\InterviewInviteMail;
use App\Mail\OfferMail;
use App\Mail\RejectionMail;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Communication;
use App\Models\Interview;
use App\Models\Offer;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public function applicationReceived(CandidateApplication $application): void
    {
        $this->send(
            $application->candidate,
            new ApplicationReceivedMail($application),
            'application_received',
            'Application received: '.$application->job->title
        );
    }

    public function interviewInvite(Interview $interview, bool $isReminder = false): void
    {
        $this->send(
            $interview->candidate,
            new InterviewInviteMail($interview, $isReminder),
            $isReminder ? 'interview_reminder' : 'interview_invite',
            ($isReminder ? 'Reminder: ' : '').'Interview invitation for '.$interview->job->title
        );
    }

    public function offerSent(Offer $offer): void
    {
        $this->send(
            $offer->candidate,
            new OfferMail($offer),
            'offer_sent',
            'Job offer: '.$offer->job->title
        );
    }

    public function rejected(CandidateApplication $application): void
    {
        $this->send(
            $application->candidate,
            new RejectionMail($application),
            'rejection',
            'Update on your application: '.$application->job->title
        );
    }

    /**
     * Sends a mailable and always logs a Communication record — the pipeline action that
     * triggered this (stage change, offer send, etc.) must succeed even if mail delivery
     * fails (e.g. SMTP not configured yet), so failures are caught and logged, not thrown.
     */
    private function send(?Candidate $candidate, Mailable $mailable, string $template, string $subject): void
    {
        if (! $candidate || ! $candidate->email) {
            return;
        }

        $status = 'SENT';
        try {
            Mail::to($candidate->email)->send($mailable);
        } catch (Throwable $e) {
            $status = 'FAILED';
            Log::warning('Notification send failed', ['template' => $template, 'candidate_id' => $candidate->id, 'error' => $e->getMessage()]);
        }

        Communication::create([
            'candidate_id' => $candidate->id,
            'channel' => 'Email',
            'direction' => 'Outbound',
            'subject' => $subject,
            'body' => $template,
            'status' => $status,
            'template' => $template,
            'sent_at' => now(),
        ]);
    }
}
