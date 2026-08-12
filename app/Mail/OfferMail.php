<?php

namespace App\Mail;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Offer $offer)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Job offer: '.$this->offer->job->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.offer', with: [
            'candidateName' => $this->offer->candidate->full_name,
            'jobTitle' => $this->offer->job->title,
            'salaryAmount' => $this->offer->salary_amount,
            'currency' => $this->offer->currency,
            'startDate' => $this->offer->start_date,
            'terms' => $this->offer->terms,
            'portalUrl' => route('portal.login', $this->offer->company->slug),
        ]);
    }
}
