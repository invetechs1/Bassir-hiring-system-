<?php

namespace App\Mail;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterviewInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Interview $interview, public bool $isReminder = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->isReminder ? 'Reminder: ' : '').'Interview invitation for '.($this->interview->job?->title ??'your application'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.interview-invite', with: [
            'candidateName' => $this->interview->candidate->full_name,
            'jobTitle' => $this->interview->job?->title ??'the role',
            'startsAt' => $this->interview->starts_at,
            'channel' => $this->interview->channel,
            'meetingLink' => $this->interview->meeting_link,
            'isReminder' => $this->isReminder,
        ]);
    }
}
