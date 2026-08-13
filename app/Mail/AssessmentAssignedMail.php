<?php

namespace App\Mail;

use App\Models\Assessment;
use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Assessment $assessment, public Candidate $candidate)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Next step: '.$this->assessment->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.assessment-assigned', with: [
            'candidateName' => $this->candidate->full_name,
            'assessmentTitle' => $this->assessment->title,
            'description' => $this->assessment->description,
            'portalUrl' => route('portal.login', $this->assessment->company->slug),
        ]);
    }
}
