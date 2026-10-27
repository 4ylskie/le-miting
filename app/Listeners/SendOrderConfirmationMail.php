<?php

namespace App\Listeners;

use App\Events\OrderPaymentVerified;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationMail
{
    public function handle(OrderPaymentVerified $event): void
    {
        $order = $event->order->loadMissing('orderItems', 'payment');

        try {
            Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
