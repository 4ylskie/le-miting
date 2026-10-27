<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Services\ReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

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
        $with = [
            'cancelUrl' => URL::signedRoute(
                'reservation.cancel-form',
                ['reservation' => $this->reservation->id],
                now()->addHours(24),
            ),
        ];

        if ($this->hasPaidFoodPreorder()) {
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

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->hasPaidFoodPreorder()) {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => app(ReceiptService::class)->generateReservationReceipt($this->reservation)->output(),
                'receipt-'.$this->reservation->reservation_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }

    private function hasPaidFoodPreorder(): bool
    {
        return $this->reservation->reservation_type === 'table_and_food'
            && $this->reservation->payment?->status === 'paid';
    }
}
