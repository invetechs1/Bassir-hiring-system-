<?php

namespace App\Mail;

use App\Models\CandidateApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CandidateApplication $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Application received: '.$this->application->job->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.application-received', with: [
            'candidateName' => $this->application->candidate->full_name,
            'jobTitle' => $this->application->job->title,
            'companyName' => $this->application->company->name ?? 'the hiring team',
        ]);
    }
}
