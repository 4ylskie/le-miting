<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $customerName,
        public string $transactionLabel,
        public string $feedbackUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How was your visit to Le Miting?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.feedback-invitation',
        );
    }
}
