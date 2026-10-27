<?php

namespace App\Services;

use App\Events\ReservationPaymentVerified;
use App\Mail\GoogleReviewInvitationMail;
use App\Mail\ReservationCancellationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\PaymentEligibility;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public const HOLD_MINUTES = 15;

    public const DURATION_MINUTES = 120;

    public function __construct(
        private ReservationSlots $slots,
        private ReservationMenuCandidates $menuCandidates,
        private PaymentEligibility $payments,
        private BusinessTime $businessTime,
    ) {}

    public function isTableSuitable(Table $table, int $guestCount): bool
    {
        $minimum = config('reservation.capacity_rules.'.$table->capacity);

        return $minimum !== null && $guestCount >= $minimum && $guestCount <= $table->capacity;
    }

    /** @return array{tables: Collection, has_suitable_tables: bool, party_too_large: bool} */
    public function tableAvailability(CarbonInterface $start, int $guestCount): array
    {
        $tables = Table::query()->orderBy('id')->get();
        $supported = $tables->filter(fn (Table $table): bool => config('reservation.capacity_rules.'.$table->capacity) !== null);
        $suitable = $supported->filter(fn (Table $table): bool => $this->isTableSuitable($table, $guestCount));
        $candidates = $suitable->filter(fn (Table $table): bool => $table->status === 'available');

        return [
            'tables' => $this->availableTablesForInterval($candidates, $start),
            'has_suitable_tables' => $suitable->isNotEmpty(),
            'party_too_large' => $supported->isNotEmpty() && $guestCount > $supported->max('capacity'),
        ];
    }

    /**
     * Read-only preview for suitable, available candidates; allocation still revalidates under locks.
     *
     * @param  Collection<int, Table>  $tables
     * @return Collection<int, Table>
     */
    public function availableTablesForInterval(Collection $tables, CarbonInterface $start, ?int $exceptReservationId = null): Collection
    {
        if ($tables->isEmpty()) {
            return $tables->values();
        }

        $start = $this->businessTime->local($start);
        $end = $start->addMinutes(self::DURATION_MINUTES);
        $query = $this->allocationReservations($exceptReservationId)
            ->whereIn('table_id', $tables->pluck('id'))
            ->whereDate('reservation_date', '<=', $end->toDateString());

        // Stored durations bound the date range without discarding longer historical intervals.
        $maximumDuration = (clone $query)->max('duration_minutes');
        if ($maximumDuration === null) {
            return $tables->values();
        }

        $reservations = $query
            ->whereDate('reservation_date', '>=', $start->subMinutes((int) $maximumDuration)->toDateString())
            ->select(['id', 'table_id', 'reservation_date', 'reservation_time', 'duration_minutes'])
            ->cursor();
        $blockedTableIds = [];
        foreach ($reservations as $reservation) {
            if ($reservation->reservation_date_time < $end && $reservation->end_date_time > $start) {
                $blockedTableIds[$reservation->table_id] = true;
            }
        }

        return $tables->reject(fn (Table $table): bool => isset($blockedTableIds[$table->id]))->values();
    }

    public function isTableAvailable(Table $table, CarbonInterface $start, ?int $exceptReservationId = null, bool $lock = false): bool
    {
        $end = $start->copy()->addMinutes(self::DURATION_MINUTES);

        $reservations = $this->allocationReservations($exceptReservationId)
            ->where('table_id', $table->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        return $reservations->every(fn (Reservation $reservation) => $reservation->end_date_time <= $start || $reservation->reservation_date_time >= $end);
    }

    /** @return Builder<Reservation> */
    private function allocationReservations(?int $exceptReservationId): Builder
    {
        return Reservation::query()
            ->when($exceptReservationId !== null, fn ($query) => $query->where('id', '!=', $exceptReservationId))
            ->whereIn('status', ['confirmed', 'completed', 'pending'])
            ->where(function ($query) {
                $query->whereIn('status', ['confirmed', 'completed'])
                    ->orWhere(fn ($pending) => $pending->where('status', 'pending')
                        ->where('created_at', '>=', now()->subMinutes(self::HOLD_MINUTES)));
            });
    }

    public function reassign(int $reservationId, int $destinationId, int $expectedTableId, string $expectedStatus): ?string
    {
        return DB::transaction(function () use ($reservationId, $destinationId, $expectedTableId, $expectedStatus): ?string {
            $tables = Table::query()->whereIn('id', [$expectedTableId, $destinationId])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->table_id !== $expectedTableId || $reservation->status !== $expectedStatus) {
                return 'This reservation has changed. Refresh the page before trying again.';
            }
            if ($reservation->checked_in_at !== null) {
                return 'A checked-in reservation cannot be reassigned to another table.';
            }
            if (! in_array($reservation->status, ['pending', 'confirmed'], true)) {
                return 'Only active reservations can be reassigned to another table.';
            }
            if ($destinationId === $expectedTableId) {
                return null;
            }

            $table = $tables->get($destinationId);
            if ($table === null || $table->status !== 'available') {
                throw ValidationException::withMessages(['table_id' => 'That table is not available. Choose an available table.']);
            }
            if (! $this->isTableSuitable($table, $reservation->guest_count)) {
                throw ValidationException::withMessages(['table_id' => 'That table is not suitable for this party size. Choose a suitable table.']);
            }
            if (! $this->isTableAvailable($table, $reservation->reservation_date_time, $reservation->id, lock: true)) {
                throw ValidationException::withMessages(['table_id' => 'That table is already reserved for this time. Choose another table.']);
            }

            $reservation->update(['table_id' => $table->id]);

            return null;
        });
    }

    /** A callback only attempts allocation on its first successful payment application. */
    public function confirmPaidReservation(int $reservationId): ?Reservation
    {
        return $this->transition($reservationId, 'confirmed', 'pending') === null
            ? Reservation::findOrFail($reservationId) : null;
    }

    /** @return array<string, array<int, string>> */
    public function transitions(): array
    {
        return ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled'], 'completed' => [], 'cancelled' => []];
    }

    /** Return a user-facing rejection, or null for the winning transition. */
    public function transition(int $reservationId, string $target, string $expectedStatus): ?string
    {
        return DB::transaction(function () use ($reservationId, $target, $expectedStatus): ?string {
            $original = Reservation::findOrFail($reservationId);
            $payments = $target !== 'cancelled' && $original->reservation_type === 'table_and_food'
                ? $this->payments->lockFor($original) : null;
            $table = $target === 'confirmed' ? Table::query()->lockForUpdate()->findOrFail($original->table_id) : null;
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== $expectedStatus || $reservation->reservation_type !== $original->reservation_type
                || ($table !== null && $reservation->table_id !== $table->id)) {
                return 'This reservation has changed. Refresh the page before trying again.';
            }
            if (! in_array($target, $this->transitions()[$reservation->status] ?? [], true)) {
                return 'Cannot move a reservation from '.$reservation->status.' to '.$target.'.';
            }
            if ($target === 'completed' && $reservation->checked_in_at === null) {
                return 'Check in this reservation before marking the visit completed.';
            }
            if ($target === 'cancelled' && $reservation->checked_in_at !== null) {
                return 'A checked-in reservation cannot be cancelled.';
            }
            if ($target !== 'cancelled' && $reservation->reservation_type === 'table_and_food'
                && ! $this->payments->isEligible($reservation, $payments)) {
                return PaymentEligibility::ERROR;
            }
            if ($target === 'confirmed' && ($table->status !== 'available'
                || ! $this->isTableSuitable($table, $reservation->guest_count)
                || ! $this->isTableAvailable($table, $reservation->reservation_date_time, $reservation->id, lock: true))) {
                return 'The table allocation conflict remains unresolved. The reservation, payment and assigned table have not been changed.';
            }

            $reservation->update(['status' => $target]);
            if ($target === 'confirmed') {
                $this->notifyConfirmation($reservation);
            } elseif ($target === 'cancelled') {
                $this->sendAfterCommit($reservation, $this->cancellationMail($reservation));
            } elseif ($target === 'completed') {
                $this->sendAfterCommit($reservation, new GoogleReviewInvitationMail(
                    $reservation->customer_name, 'reservation '.$reservation->reservation_number, config('restaurant.google_review_url'),
                ));
            }

            return null;
        });
    }

    private function notifyConfirmation(Reservation $reservation): void
    {
        if ($reservation->reservation_type === 'table_and_food') {
            DB::afterCommit(fn () => ReservationPaymentVerified::dispatch($reservation));
        } else {
            $this->sendAfterCommit($reservation, new ReservationConfirmationMail($reservation));
        }
    }

    private function sendAfterCommit(Reservation $reservation, Mailable $mail): void
    {
        DB::afterCommit(function () use ($reservation, $mail): void {
            try {
                Mail::to($reservation->customer_email)->send($mail);
            } catch (\Throwable $exception) {
                if ($mail instanceof ReservationCancellationMail) {
                    Log::warning('Reservation cancellation email send failed.', [
                        'reservation_id' => $reservation->id,
                        'reservation_number' => $reservation->reservation_number,
                        'exception_class' => $exception::class,
                    ]);
                } else {
                    report($exception);
                }
            }
        });
    }

    private function cancellationMail(Reservation $reservation): ReservationCancellationMail
    {
        $payments = Payment::query()->where('reservation_id', $reservation->id)->get();

        return new ReservationCancellationMail($reservation, $this->payments->isEligible($reservation, $payments));
    }

    public function createTableOnly(array $data): Reservation
    {
        $start = $this->reservationDateTime($data['reservation_date'], $data['reservation_time']);

        $this->slots->assertValid($start);

        return DB::transaction(function () use ($start, $data): Reservation {
            $table = $this->lockAndValidateTableForReservation(
                $data['table_id'],
                $data['guest_count'],
                $start,
            );

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

            $this->notifyConfirmation($reservation);

            return $reservation;
        });
    }

    public function createTableAndFood(array $data): Reservation
    {
        $start = $this->reservationDateTime($data['reservation_date'], $data['reservation_time']);

        $this->slots->assertValid($start);

        return DB::transaction(function () use ($start, $data): Reservation {
            $table = $this->lockAndValidateTableForReservation(
                $data['table_id'],
                $data['guest_count'],
                $start,
            );

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

            $foodItems = collect($data['food_items'])->keyBy('menu_item_id');
            $menuItems = $this->menuCandidates->query()
                ->whereIn('id', $foodItems->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($menuItems->count() !== $foodItems->count()) {
                throw ValidationException::withMessages([
                    'food_items' => 'One of the selected dishes is no longer available. Please choose another dish.',
                ]);
            }

            $total = 0;

            foreach ($foodItems as $foodItem) {
                $item = $menuItems->get($foodItem['menu_item_id']);

                $reservation->reservationFoods()->create([
                    'menu_item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $foodItem['quantity'],
                ]);

                $total += $item->price * $foodItem['quantity'];
            }

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation): ?Reservation
    {
        return DB::transaction(function () use ($reservation): ?Reservation {
            $reservation = Reservation::query()
                ->lockForUpdate()
                ->findOrFail($reservation->id);

            if (! $this->canBeCancelled($reservation)) {
                return null;
            }

            $reservation->update(['status' => 'cancelled']);
            $this->sendAfterCommit($reservation, $this->cancellationMail($reservation));

            return $reservation->refresh();
        });
    }

    public function canBeCancelled(Reservation $reservation): bool
    {
        return in_array($reservation->status, ['pending', 'confirmed'], true)
            && $reservation->checked_in_at === null
            && $reservation->reservation_date_time->greaterThan($this->businessTime->now());
    }

    public function checkIn(string $reservationNumber): Reservation
    {
        return DB::transaction(function () use ($reservationNumber): Reservation {
            $original = Reservation::where('reservation_number', $reservationNumber)->first();

            if (! $original) {
                throw ValidationException::withMessages([
                    'reservation_number' => 'No reservation was found for that reservation number.',
                ]);
            }

            $payments = $original->reservation_type === 'table_and_food' ? $this->payments->lockFor($original) : null;
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($original->id);

            if ($reservation->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'reservation_number' => 'Only confirmed reservations can be checked in.',
                ]);
            }

            if ($reservation->checked_in_at !== null) {
                throw ValidationException::withMessages([
                    'reservation_number' => 'This reservation is already checked in.',
                ]);
            }

            if (! $reservation->canCheckIn()) {
                throw ValidationException::withMessages([
                    'reservation_number' => 'Check-in is available from the scheduled start time (Malaysia time).',
                ]);
            }

            if ($reservation->reservation_type !== $original->reservation_type
                || ($reservation->reservation_type === 'table_and_food' && ! $this->payments->isEligible($reservation, $payments))) {
                throw ValidationException::withMessages(['reservation_number' => PaymentEligibility::ERROR]);
            }

            $reservation->checked_in_at = now();
            $reservation->save();

            return $reservation->refresh();
        });
    }

    private function lockAndValidateTableForReservation(int $tableId, int $guestCount, CarbonInterface $start): Table
    {
        $table = Table::query()
            ->lockForUpdate()
            ->findOrFail($tableId);

        if ($table->status !== 'available') {
            throw ValidationException::withMessages([
                'table_id' => 'That table is no longer available. Please select another table.',
            ]);
        }

        $this->validateGuestCount($table, $guestCount);
        $this->lockAndVerifyTableAvailable($table, $start);

        return $table;
    }

    private function lockAndVerifyTableAvailable(Table $table, CarbonInterface $start): void
    {
        if (! $this->isTableAvailable($table, $start, lock: true)) {
            throw ValidationException::withMessages([
                'table_id' => 'This table is no longer available for the selected time. Please choose another available table or time.',
            ]);
        }
    }

    private function reservationDateTime(string $date, string $time): CarbonImmutable
    {
        return $this->businessTime->reservationStart($date, $time);
    }

    private function validateGuestCount(Table $table, int $guestCount): void
    {
        if ($guestCount > $table->capacity) {
            throw ValidationException::withMessages([
                'guest_count' => 'This table only seats '.$table->capacity.' guests. Please choose a larger table.',
            ]);
        }

        if (! $this->isTableSuitable($table, $guestCount)) {
            throw ValidationException::withMessages([
                'table_id' => 'This table is not suitable for your party size. Please choose a suitable table.',
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
