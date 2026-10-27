<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Table;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TableManagementService
{
    public function __construct(private ReservationService $reservations, private BusinessTime $businessTime) {}

    /**
     * Own the MySQL transaction so its first consistent read occurs AFTER the table
     * and reservation locks. Never load payments before these locks or lock payments
     * here: callbacks acquire payment locks before waiting for this table.
     *
     * @param  array{table_number: string, capacity: int|string, status: string, expected_capacity: int|string, expected_status: string}  $data
     */
    public function update(int $tableId, array $data): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            if (DB::transactionLevel() !== 0) {
                throw new \LogicException('Table management must start its own transaction to read current payment commitments.');
            }
            $isolation = DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level;
            if (! in_array($isolation, ['REPEATABLE-READ', 'READ-COMMITTED'], true)) {
                throw new \LogicException('Table management requires READ COMMITTED or REPEATABLE READ isolation.');
            }
        }

        DB::transaction(function () use ($tableId, $data): void {
            $table = Table::query()->lockForUpdate()->findOrFail($tableId);
            if ((int) $table->capacity !== (int) $data['expected_capacity'] || $table->status !== $data['expected_status']) {
                throw ValidationException::withMessages(['status' => 'This table has changed. Refresh the page before trying again.']);
            }
            $capacity = (int) $data['capacity'];
            if (! array_key_exists($capacity, config('reservation.capacity_rules'))) {
                throw ValidationException::withMessages(['capacity' => 'Choose an approved seating capacity.']);
            }

            $capacityChanged = $capacity !== (int) $table->capacity;
            $activatingMaintenance = $data['status'] === 'maintenance' && $table->status === 'available';
            if ($capacityChanged || $activatingMaintenance) {
                $commitments = $table->reservations()->orderBy('id')->lockForUpdate()->get();
                $commitments->load(['payment' => fn ($query) => $query->where('status', 'paid')]);
                $now = $this->businessTime->now();
                $proposed = clone $table;
                $proposed->capacity = $capacity;

                foreach ($commitments as $reservation) {
                    if ($capacityChanged && $reservation->checked_in_at !== null && $reservation->status !== 'completed') {
                        throw ValidationException::withMessages(['capacity' => 'Capacity cannot change during an uncompleted checked-in visit.']);
                    }
                    if ($this->protectionReason($reservation, $now) === null) {
                        continue;
                    }
                    if ($activatingMaintenance) {
                        throw ValidationException::withMessages(['status' => 'This table has protected reservation commitments. Resolve them before activating maintenance.']);
                    }
                    if ($capacityChanged && ! $this->reservations->isTableSuitable($proposed, $reservation->guest_count)) {
                        throw ValidationException::withMessages(['capacity' => 'This capacity is unsuitable for an existing protected reservation.']);
                    }
                }
            }

            $table->update(['table_number' => $data['table_number'], 'capacity' => $capacity, 'status' => $data['status']]);
        });
    }

    /** Payment must be eager loaded; this is management protection, NOT allocation eligibility. */
    public function protectionReason(Reservation $reservation, CarbonInterface $now): ?string
    {
        if ($reservation->status === 'cancelled') {
            return null;
        }
        $start = $reservation->reservation_date_time;
        $end = $start->copy()->addMinutes($reservation->duration_minutes);

        if ($reservation->status === 'confirmed') {
            if ($reservation->checked_in_at !== null || ($start <= $now && $end > $now)) {
                return 'Active occupancy';
            }

            return $start > $now ? 'Future commitments' : 'Unresolved confirmations';
        }
        if ($reservation->status === 'completed') {
            return $end > $now ? ($start > $now ? 'Future commitments' : 'Active occupancy') : null;
        }
        if ($reservation->status === 'pending') {
            if ($reservation->payment?->status === 'paid') {
                return 'Paid pending — manual resolution';
            }
            if ($end > $now && $reservation->created_at >= $now->copy()->subMinutes(ReservationService::HOLD_MINUTES)) {
                return 'Valid pending holds';
            }
        }

        return null;
    }
}
