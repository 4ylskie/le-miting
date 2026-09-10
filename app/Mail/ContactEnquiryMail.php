<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactEnquiryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $enquiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [config('restaurant.email')],
            subject: 'New Enquiry from '.$this->name.' — Le Miting',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-enquiry',
        );
    }
}
