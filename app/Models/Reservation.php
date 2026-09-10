<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'table_id',
        'reservation_number',
        'reservation_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'reservation_date',
        'reservation_time',
        'guest_count',
        'duration_minutes',
        'status',
        'special_request',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'guest_count' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function reservationFoods(): HasMany
    {
        return $this->hasMany(ReservationFood::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public function getReservationDateTimeAttribute(): Carbon
    {
        return Carbon::parse($this->reservation_date->format('Y-m-d').' '.$this->reservation_time);
    }

    public function getEndDateTimeAttribute(): Carbon
    {
        return $this->reservation_date_time->copy()->addMinutes($this->duration_minutes);
    }

    public function getTotalAttribute(): float
    {
        return round($this->reservationFoods->sum(fn ($food) => $food->unit_price * $food->quantity), 2);
    }
}
