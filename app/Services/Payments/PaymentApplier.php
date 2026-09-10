<?php

namespace App\Services\Payments;

use App\Events\OrderPaymentVerified;
use App\Events\ReservationPaymentVerified;
use App\Models\Payment;

class PaymentApplier
{
    /**
     * Upsert a verified payment row and confirm its linked order or reservation.
     *
     * @param  array<string, mixed>  $data  reference, amount, currency
     */
    public function applyVerifiedPayment(array $data): Payment
    {
        $payment = Payment::firstOrNew(['provider_reference' => $data['reference']]);

        if (isset($data['amount'])) {
            $payment->amount = $data['amount'];
        }
        if (isset($data['currency'])) {
            $payment->currency = $data['currency'];
        }

        $payment->provider = 'fiuu';
        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->save();

        if ($payment->order && $payment->order->status !== 'confirmed') {
            $payment->order->update(['status' => 'confirmed']);
            $payment->order->refresh();

            OrderPaymentVerified::dispatch($payment->order);
        }

        if ($payment->reservation && $payment->reservation->status !== 'confirmed') {
            $payment->reservation->update(['status' => 'confirmed']);
            $payment->reservation->refresh();

            ReservationPaymentVerified::dispatch($payment->reservation);
        }

        return $payment;
    }

    public function applyFailedPayment(array $data): Payment
    {
        $payment = Payment::firstOrNew(['provider_reference' => $data['reference']]);
        $payment->provider = 'fiuu';
        $payment->status = 'failed';
        $payment->save();

        return $payment;
    }
}
