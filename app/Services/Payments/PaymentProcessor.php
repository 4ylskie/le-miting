<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentProcessor
{
    /**
     * Build the payment page URL for a pending payment.
     *
     * @return array{url: string}
     */
    public function prepare(Payment $payment): array;

    /**
     * Verify and apply a payment callback payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): Payment;
}
