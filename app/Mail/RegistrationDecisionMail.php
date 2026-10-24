<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $decision
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->decision === 'approved'
            ? 'TabangNow registration approved'
            : 'TabangNow registration declined');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-decision');
    }

    public function attachments(): array
    {
        return [];
    }
}
