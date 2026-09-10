<?php

namespace App\Providers;

use App\Events\OrderPaymentVerified;
use App\Events\ReservationPaymentVerified;
use App\Listeners\SendOrderConfirmationMail;
use App\Listeners\SendReservationConfirmationMail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPaymentVerified::class => [
            SendOrderConfirmationMail::class,
        ],
        ReservationPaymentVerified::class => [
            SendReservationConfirmationMail::class,
        ],
    ];
}
