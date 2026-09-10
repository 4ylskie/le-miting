<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public const HOLD_MINUTES = 15;

    public const DURATION_MINUTES = 120;

    public function availableTables(Carbon $start): array
    {
        $tables = Table::all();

        return collect($tables)->map(function (Table $table) use ($start) {
            return [
                'table' => $table,
                'available' => $this->isTableAvailable($table, $start),
            ];
        })->all();
    }

    public function isTableAvailable(Table $table, Carbon $start): bool
    {
        $end = $start->copy()->addMinutes(self::DURATION_MINUTES);

        $reservations = Reservation::where('table_id', $table->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query) {
                $query->where('status', 'confirmed')
                    ->orWhere(fn ($pending) => $pending->where('status', 'pending')
                        ->where('created_at', '>=', now()->subMinutes(self::HOLD_MINUTES)));
            })
            ->get();

        return $reservations->every(fn (Reservation $reservation) => $reservation->end_date_time <= $start || $reservation->reservation_date_time >= $end);
    }

    public function createTableOnly(array $data): Reservation
    {
        $start = $this->reservationDateTime($data['reservation_date'], $data['reservation_time']);
        $table = Table::findOrFail($data['table_id']);

        $this->validateGuestCount($table, $data['guest_count']);
        $this->assertFuture($start);

        return DB::transaction(function () use ($table, $start, $data) {
            $this->lockAndVerifyTableAvailable($table, $start);

            $reservation = Reservation::create([
                'table_id' => $table->id,
                'reservation_number' => $this->generateReservationNumber(),
                'reservation_type' => 'table_only',
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'reservation_date' => $data['reservation_date'],
                'reservation_time' => $data['reservation_time'],
                'guest_count' => $data['guest_count'],
                'duration_minutes' => self::DURATION_MINUTES,
                'status' => 'confirmed',
                'special_request' => $data['special_request'] ?? null,
            ]);

            return $reservation;
        });
    }

    public function createTableAndFood(array $data): Reservation
    {
        $start = $this->reservationDateTime($data['reservation_date'], $data['reservation_time']);
        $table = Table::findOrFail($data['table_id']);

        $this->validateGuestCount($table, $data['guest_count']);
        $this->assertFuture($start);

        return DB::transaction(function () use ($table, $start, $data) {
            $this->lockAndVerifyTableAvailable($table, $start);

            $reservation = Reservation::create([
                'table_id' => $table->id,
                'reservation_number' => $this->generateReservationNumber(),
                'reservation_type' => 'table_and_food',
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'reservation_date' => $data['reservation_date'],
                'reservation_time' => $data['reservation_time'],
                'guest_count' => $data['guest_count'],
                'duration_minutes' => self::DURATION_MINUTES,
                'status' => 'pending',
                'special_request' => $data['special_request'] ?? null,
            ]);

            $total = 0;

            foreach ($data['food_items'] as $foodItem) {
                $item = MenuItem::where('is_available', true)->findOrFail($foodItem['menu_item_id']);

                $reservation->reservationFoods()->create([
                    'menu_item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $foodItem['quantity'],
                ]);

                $total += $item->price * $foodItem['quantity'];
            }

            $reservation->total = round($total, 2);

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation): void
    {
        $reservation->update(['status' => 'cancelled']);
    }

    private function lockAndVerifyTableAvailable(Table $table, Carbon $start): void
    {
        Reservation::where('table_id', $table->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query) {
                $query->where('status', 'confirmed')
                    ->orWhere(fn ($pending) => $pending->where('status', 'pending')
                        ->where('created_at', '>=', now()->subMinutes(self::HOLD_MINUTES)));
            })
            ->lockForUpdate()
            ->get();

        if (! $this->isTableAvailable($table, $start)) {
            throw ValidationException::withMessages([
                'table_id' => 'This table is no longer available for the selected time. Please choose another available table or time.',
            ]);
        }
    }

    private function reservationDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time);
    }

    private function assertFuture(Carbon $start): void
    {
        if ($start->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'reservation_time' => 'The reservation must be in the future.',
            ]);
        }
    }

    private function validateGuestCount(Table $table, int $guestCount): void
    {
        if ($guestCount > $table->capacity) {
            throw ValidationException::withMessages([
                'guest_count' => 'This table only seats '.$table->capacity.' guests. Please choose a larger table.',
            ]);
        }
    }

    private function generateReservationNumber(): string
    {
        do {
            $number = 'RES-'.date('Y').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (Reservation::where('reservation_number', $number)->exists());

        return $number;
    }
}
