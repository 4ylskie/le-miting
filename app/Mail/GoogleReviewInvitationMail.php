<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleReviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $transactionLabel,
        public string $googleReviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Share your experience with Le Miting',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.google-review-invitation',
        );
    }
}
