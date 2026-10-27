<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

class BusinessTime
{
    public function now(): CarbonImmutable
    {
        return Date::now(config('restaurant.timezone'))->toImmutable();
    }

    public function today(): string
    {
        return $this->now()->toDateString();
    }

    public function reservationStart(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' '.$time, config('restaurant.timezone'));
    }

    public function local(DateTimeInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(config('restaurant.timezone'));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function utcDayBounds(string $date): array
    {
        $start = $this->reservationStart($date, '00:00:00');

        return [$start->setTimezone('UTC'), $start->addDay()->setTimezone('UTC')];
    }
}
