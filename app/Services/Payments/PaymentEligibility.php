<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentEligibility
{
    public const ERROR = 'A verified paid payment matching this transaction is required. Missing, invalid or ambiguous payment records require review.';

    /**
     * Acquire financial rows before any payable/table locks, including callbacks.
     *
     * @return Collection<int, Payment>
     */
    public function lockFor(Order|Reservation $parent): Collection
    {
        return Payment::query()
            ->where($parent instanceof Order ? 'order_id' : 'reservation_id', $parent->id)
            ->orderBy('id')->lockForUpdate()->get();
    }

    /** @param Collection<int, Payment> $payments */
    public function isEligible(Order|Reservation $parent, Collection $payments): bool
    {
        if ($payments->count() !== 1) {
            return false;
        }

        $payment = $payments->first();
        $associated = $parent instanceof Order
            ? $payment->order_id === $parent->id && $payment->reservation_id === null
            : $payment->reservation_id === $parent->id && $payment->order_id === null;
        $amount = $parent instanceof Order ? (float) $parent->total
            : round((float) $parent->reservationFoods()->sum(DB::raw('unit_price * quantity')), 2);

        return $associated && $payment->status === 'paid' && $payment->paid_at !== null
            && $payment->currency === 'MYR'
            && abs((float) $payment->amount - $amount) <= 0.01;
    }
}
