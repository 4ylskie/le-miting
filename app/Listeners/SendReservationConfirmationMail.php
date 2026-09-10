<?php

namespace App\Listeners;

use App\Events\ReservationPaymentVerified;
use App\Mail\ReservationConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendReservationConfirmationMail
{
    public function handle(ReservationPaymentVerified $event): void
    {
        $reservation = $event->reservation->loadMissing('reservationFoods', 'table', 'payment');

        Mail::to($reservation->customer_email)->send(new ReservationConfirmationMail($reservation));
    }
}
