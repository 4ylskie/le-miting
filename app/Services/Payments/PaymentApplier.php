<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\OrderService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;

class PaymentApplier
{
    /**
     * Apply a verified callback to its existing payment and pending payable parent.
     *
     * @param  array<string, mixed>  $data  reference, amount, currency, raw_response
     */
    public function applyVerifiedPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $original = Payment::where('provider_reference', $data['reference'])->firstOrFail();
            $parent = $original->order_id !== null ? $original->order : $original->reservation;
            $payments = app(PaymentEligibility::class)->lockFor($parent);
            $payment = $payments->firstWhere('provider_reference', $data['reference']);
            abort_unless($payment, 404);

            if ($payment->status !== 'pending') {
                return $payment;
            }

            $payment->fill($this->verifiedAttributes($data));
            $payment->save();

            if ($payment->order_id !== null) {
                app(OrderService::class)->transition($payment->order_id, 'confirmed', 'pending');
            }

            if ($payment->reservation_id !== null) {
                app(ReservationService::class)->confirmPaidReservation($payment->reservation_id);
            }

            return $payment;
        });
    }

    public function applyFailedPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $payment = Payment::query()
                ->where('provider_reference', $data['reference'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'pending') {
                $payment->fill($this->failedAttributes($data));
                $payment->save();
            }

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function verifiedAttributes(array $data): array
    {
        $attributes = [
            'provider' => 'fiuu',
            'status' => 'paid',
            'paid_at' => now(),
        ];

        foreach (['amount', 'currency', 'raw_response'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function failedAttributes(array $data): array
    {
        $attributes = [
            'provider' => 'fiuu',
            'status' => 'failed',
        ];

        if (array_key_exists('raw_response', $data)) {
            $attributes['raw_response'] = $data['raw_response'];
        }

        return $attributes;
    }
}
