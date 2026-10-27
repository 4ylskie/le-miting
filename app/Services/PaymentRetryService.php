<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentRetryService
{
    public function canRetry(Payment $payment): bool
    {
        if ($payment->status !== 'failed') {
            return false;
        }

        if ($payment->order_id !== null) {
            return $payment->order?->status === 'pending'
                && $this->amountMatches((float) $payment->amount, (float) $payment->order->total);
        }

        if ($payment->reservation_id !== null) {
            return $payment->reservation?->status === 'pending'
                && $this->amountMatches((float) $payment->amount, (float) $payment->reservation->total);
        }

        return false;
    }

    public function retry(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status !== 'failed') {
                throw $this->retryNotAvailableException();
            }

            $expectedAmount = $this->lockedParentAmount($payment);

            if (! $this->amountMatches((float) $payment->amount, $expectedAmount)) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment can no longer be retried because its amount no longer matches the pending transaction.',
                ]);
            }

            $payment->update([
                'provider_reference' => $this->newProviderReference(),
                'status' => 'pending',
                'paid_at' => null,
            ]);

            return $payment->refresh();
        });
    }

    private function lockedParentAmount(Payment $payment): float
    {
        if ($payment->order_id !== null) {
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            if ($order->status !== 'pending') {
                throw $this->retryNotAvailableException();
            }

            return (float) $order->total;
        }

        if ($payment->reservation_id !== null) {
            $reservation = $payment->reservation()->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'pending') {
                throw $this->retryNotAvailableException();
            }

            return (float) $reservation->reservationFoods()->sum(DB::raw('unit_price * quantity'));
        }

        throw $this->retryNotAvailableException();
    }

    private function amountMatches(float $actual, float $expected): bool
    {
        return abs($actual - $expected) < 0.01;
    }

    private function newProviderReference(): string
    {
        do {
            $reference = 'PAY-'.date('Y').'-'.Str::upper(Str::random(10));
        } while (Payment::query()->where('provider_reference', $reference)->exists());

        return $reference;
    }

    private function retryNotAvailableException(): ValidationException
    {
        return ValidationException::withMessages([
            'payment' => 'This payment can no longer be retried.',
        ]);
    }
}
