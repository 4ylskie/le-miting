<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ReservationSlots
{
    public const INTERVAL_MINUTES = 30;

    public function __construct(private BusinessTime $businessTime) {}

    /** @return list<string>|null Null means hours have not been verified/configured. */
    public function forDate(string $date): ?array
    {
        $hours = config('restaurant.opening_hours');

        if ($hours === null) {
            return null;
        }

        $day = $this->businessTime->reservationStart($date, '00:00');
        $window = $hours[$day->dayOfWeekIso] ?? null;

        if ($window === null) {
            return [];
        }

        $start = $this->businessTime->reservationStart($date, $window['open']);
        $close = $this->businessTime->reservationStart($date, $window['close']);
        $start = $start->addMinutes((self::INTERVAL_MINUTES - $start->minute % self::INTERVAL_MINUTES) % self::INTERVAL_MINUTES);
        $now = $this->businessTime->now();
        $slots = [];

        while ($start->copy()->addMinutes(ReservationService::DURATION_MINUTES)->lessThanOrEqualTo($close)) {
            if ($start->greaterThan($now)) {
                $slots[] = $start->format('H:i');
            }
            $start = $start->addMinutes(self::INTERVAL_MINUTES);
        }

        return $slots;
    }

    public function assertValid(CarbonInterface $start): void
    {
        $start = $this->businessTime->local($start);

        if ($start->lessThanOrEqualTo($this->businessTime->now())) {
            throw ValidationException::withMessages(['reservation_time' => 'The reservation must be in the future.']);
        }

        if ($start->minute % self::INTERVAL_MINUTES !== 0 || $start->second !== 0) {
            throw ValidationException::withMessages(['reservation_time' => 'Choose a start time on the hour or half hour.']);
        }

        $slots = $this->forDate($start->toDateString());
        if ($slots !== null && ! in_array($start->format('H:i'), $slots, true)) {
            throw ValidationException::withMessages(['reservation_time' => 'Choose an available start time within opening hours for a full two-hour visit.']);
        }
    }
}
