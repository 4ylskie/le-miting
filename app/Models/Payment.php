<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'reservation_id',
        'provider',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'raw_response',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_response' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $hasOrder = $payment->order_id !== null;
            $hasReservation = $payment->reservation_id !== null;

            if ($hasOrder === $hasReservation) {
                throw ValidationException::withMessages([
                    'payment' => 'A payment must belong to exactly one order or reservation.',
                ]);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
