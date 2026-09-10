<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationCancellationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reservation Cancelled — Le Miting',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-cancellation',
        );
    }
}
