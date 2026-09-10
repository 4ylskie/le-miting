<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;

class ReservationPaymentVerified
{
    use Dispatchable;

    public function __construct(public Reservation $reservation) {}
}
