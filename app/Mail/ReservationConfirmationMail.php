<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReservationConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reservation Confirmed — Le Miting',
        );
    }

    public function content(): Content
    {
        $qrSvg = QrCode::size(180)->generate($this->reservation->reservation_number);
        $qrDataUri = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);

        $with = [
            'qrDataUri' => $qrDataUri,
            'cancelUrl' => URL::signedRoute(
                'reservation.cancel-form',
                ['reservation' => $this->reservation->id],
                now()->addHours(24),
            ),
        ];

        if ($this->reservation->reservation_type === 'table_and_food') {
            $with['receiptUrl'] = URL::signedRoute(
                'reservation.receipt',
                ['reservation' => $this->reservation->id],
                now()->addHours(24),
            );
        }

        return new Content(
            view: 'emails.reservation-confirmation',
            with: $with,
        );
    }
}
