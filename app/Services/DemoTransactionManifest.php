<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

class DemoTransactionManifest
{
    public const VERSION = 'demo02-v1';

    public const ANCHOR = '2026-10-14T12:30:00+08:00';

    public const COUNTS = ['orders' => 36, 'order_items' => 108, 'reservations' => 24, 'reservation_foods' => 36, 'payments' => 48];

    private const BASKETS = [
        'A' => [4 => 2, 16 => 2, 13 => 1], 'B' => [5 => 1, 7 => 1, 17 => 2],
        'C' => [3 => 2, 19 => 1, 18 => 2], 'D' => [1 => 2, 29 => 1, 34 => 2],
        'E' => [31 => 1, 21 => 1, 35 => 1], 'F' => [22 => 1, 27 => 1, 33 => 1],
        'L' => [4 => 2, 16 => 2, 13 => 1], 'M' => [5 => 1, 7 => 1, 17 => 2],
        'N' => [3 => 2, 14 => 1, 18 => 2],
    ];

    /**
     * Current catalog prices are explicitly fictional historical snapshots.
     *
     * @param  array<string, list<array<string, mixed>>>  $catalog
     * @return array<string, mixed>
     */
    public function build(array $catalog): array
    {
        $anchor = CarbonImmutable::parse(self::ANCHOR)->utc();
        $orders = $reservations = [];
        foreach (range(1, 36) as $n) {
            $status = match (true) {
                in_array($n, [10, 11, 20, 29]) => 'pending',
                in_array($n, [12, 30, 35, 36]) => 'cancelled',
                in_array($n, [3, 4, 5, 17]) => 'confirmed',
                in_array($n, [6, 7, 18]) => 'preparing',
                in_array($n, [8, 9, 19]) => 'ready', default => 'completed',
            };
            $payment = in_array($n, [10, 29, 35]) ? 'pending' : (in_array($n, [11, 20, 36]) ? 'failed' : 'paid');
            $created = match (true) {
                $n <= 12 => $anchor->subHours(4)->addMinutes($n * 10),
                $n <= 20 => $anchor->subDays(1 + ($n - 13) % 6),
                $n === 21 => $anchor->subDays(7)->subMinutes(2),
                $n <= 30 => $anchor->subDays(8 + ($n - 22) * 2),
                $n === 31 => $anchor->subDays(30)->subMinutes(2),
                default => $anchor->subDays(31 + ($n - 32) % 4),
            };
            $basket = $n <= 20 ? 'ABCDEF'[($n - 1) % 6] : 'LMN'[($n - 21) % 3];
            $key = sprintf('O%02d', $n);
            $orders[$key] = $this->scenario($key, $created, $status, $payment,
                $this->lines($catalog, $basket, 1, $created), in_array($n, [2, 22]),
                in_array($n, [21, 31]) ? 4 : 5) + ['order_type' => $n % 2 ? 'dine-in' : 'takeaway'];
        }
        $createdDays = [34, 21, 12, 4, 2, 1, 2, 3, 4, 5, 10, 1, 33, 16, 3, 1, 1, 0, 2, 0, 0, 0, 1, 2];
        $visitDays = [-33, -20, -10, -3, 0, 0, 1, 2, 3, 4, -8, 1, -32, -15, -2, 0, 0, 1, 2, 1, 1, 1, 1, 2];
        $hours = [10, 10, 12, 10, 8, 12, 10, 12, 12, 10, 10, 10, 12, 12, 12, 10, 12, 12, 12, 10, 10, 12, 12, 12];
        $tables = [1, 2, 3, 4, 11, 6, 7, 8, 9, 10, 5, 11, 10, 8, 7, 11, 9, 11, 1, 2, 3, 4, 5, 6];
        $guests = [4, 2, 3, 4, 1, 3, 6, 5, 8, 7, 2, 2, 10, 8, 4, 2, 6, 3, 4, 1, 2, 3, 4, 6];
        foreach (range(1, 24) as $n) {
            $i = $n - 1;
            $key = sprintf('R%02d', $n);
            $created = match ($n) {
                18, 22 => $anchor->subHour(), 20 => $anchor->subMinutes(5),
                21 => $anchor->subMinutes(10), default => $anchor->subDays($createdDays[$i]),
            };
            $start = $anchor->setTimezone('Asia/Kuala_Lumpur')->addDays($visitDays[$i])->setTime($hours[$i], 0);
            $status = match (true) {
                in_array($n, [1, 2, 3, 4, 5, 13, 14, 15, 16]) => 'completed',
                in_array($n, [11, 12, 24]) => 'cancelled',
                $n >= 20 && $n <= 23 => 'pending', default => 'confirmed',
            };
            $payment = $n <= 12 ? null : (in_array($n, [20, 22]) ? 'pending' : (in_array($n, [21, 23]) ? 'failed' : 'paid'));
            $lines = $n <= 12 ? [] : $this->lines($catalog, 'ABCDEF'[($n - 13) % 6], (int) ceil($guests[$i] / 2), $created);
            $row = $this->scenario($key, $created, $status, $payment, $lines, $n === 19, $n === 18 ? 20 : 1);
            $row += ['reservation_type' => $n <= 12 ? 'table_only' : 'table_and_food',
                'table_id' => $tables[$i], 'guest_count' => $guests[$i], 'duration_minutes' => 120,
                'reservation_date' => $start->toDateString(), 'reservation_time' => $start->format('H:i:s')];
            $row['checked_in_at'] = $status === 'completed' || in_array($n, [6, 17]) ? $start->utc()->addMinutes(5)->toDateTimeString() : null;
            $row['finished_at'] = $status === 'completed' ? $start->utc()->addMinutes(110)->toDateTimeString()
                : ($status === 'cancelled' ? $created->addHours(2)->toDateTimeString() : null);
            $reservations[$key] = $row;
        }
        $manifest = ['version' => self::VERSION, 'anchor' => self::ANCHOR,
            'catalog_sha256' => DemoCatalogTransfer::APPROVED_SHA256, 'counts' => self::COUNTS,
            'orders' => $orders, 'reservations' => $reservations];
        $manifest['events'] = $this->events($manifest);
        $manifest['expected'] = $this->expected($manifest);

        return $manifest;
    }

    /** @return array<string, mixed> */
    private function scenario(string $key, CarbonImmutable $created, string $status, ?string $payment, array $lines, bool $retry, int $delay): array
    {
        return ['key' => $key, 'number' => 'DEMO02-'.$key, 'created_at' => $created->toDateTimeString(),
            'status' => $status, 'payment_status' => $payment, 'retry' => $retry,
            'payment_at' => in_array($payment, ['paid', 'failed']) ? $created->addMinutes($retry ? 8 : $delay)->toDateTimeString() : null,
            'lines' => $lines, 'total_cents' => array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines)),
            'customer_name' => 'Fictional Demo Guest '.$key, 'customer_email' => strtolower($key).'@example.test',
            'customer_phone' => 'DEMO-'.$key];
    }

    /** @return list<array<string, mixed>> */
    private function lines(array $catalog, string $basket, int $multiplier, CarbonImmutable $created): array
    {
        $items = array_column($catalog['menu_items'], null, 'id');
        $categories = array_column($catalog['menu_categories'], null, 'id');
        $lines = [];
        foreach (self::BASKETS[$basket] as $id => $quantity) {
            $item = $items[$id] ?? throw new RuntimeException('Manifest: missing menu item.');
            if (! $item['is_available'] || $item['deleted_at'] !== null || ! $categories[$item['menu_category_id']]['is_active']
                || CarbonImmutable::parse($item['created_at'], 'UTC')->greaterThan($created)) {
                throw new RuntimeException('Manifest: historically ineligible menu item.');
            }
            $lines[] = ['menu_item_id' => $id, 'item_name' => $item['name'],
                'unit_cents' => (int) round((float) $item['price'] * 100), 'quantity' => $quantity * $multiplier];
        }

        return $lines;
    }

    /** @return list<array{key: string, action: string, at: string}> */
    private function events(array $manifest): array
    {
        $events = [];
        foreach ($manifest['orders'] + $manifest['reservations'] as $key => $row) {
            $created = CarbonImmutable::parse($row['created_at'], 'UTC');
            $events[] = ['key' => $key, 'action' => 'create', 'at' => $row['created_at']];
            if ($row['retry']) {
                $events[] = ['key' => $key, 'action' => 'failure', 'at' => $created->addMinute()->toDateTimeString()];
                $events[] = ['key' => $key, 'action' => 'retry', 'at' => $created->addMinutes(3)->toDateTimeString()];
            }
            if ($row['payment_at'] !== null) {
                $events[] = ['key' => $key, 'action' => $row['payment_status'] === 'paid' ? 'success' : 'failure', 'at' => $row['payment_at']];
            }
            if (str_starts_with($key, 'O')) {
                $paid = CarbonImmutable::parse($row['payment_at'] ?? $row['created_at'], 'UTC');
                $steps = match ($row['status']) {
                    'preparing' => ['preparing'], 'ready' => ['preparing', 'ready'],
                    'completed' => ['preparing', 'ready', 'completed'], 'cancelled' => ['cancelled'], default => [],
                };
                foreach ($steps as $i => $status) {
                    $events[] = ['key' => $key, 'action' => $status, 'at' => $paid->addMinutes(($i + 1) * 10)->toDateTimeString()];
                }
            } else {
                if ($row['checked_in_at'] !== null) {
                    $events[] = ['key' => $key, 'action' => 'check_in', 'at' => $row['checked_in_at']];
                }
                if ($row['finished_at'] !== null) {
                    $events[] = ['key' => $key, 'action' => $row['status'], 'at' => $row['finished_at']];
                }
            }
            if (in_array($key, ['O03', 'R18'])) {
                $events[] = ['key' => $key, 'action' => 'duplicate', 'at' => CarbonImmutable::parse($row['payment_at'], 'UTC')->addMinute()->toDateTimeString()];
            }
        }
        usort($events, fn ($a, $b) => [$a['at'], $a['key'], $a['action']] <=> [$b['at'], $b['key'], $b['action']]);

        return $events;
    }

    /** Predictions computed independently of database report queries; money is integer cents. */
    public function expected(array $manifest): array
    {
        $anchor = CarbonImmutable::parse(self::ANCHOR)->utc();
        $today = $anchor->setTimezone('Asia/Kuala_Lumpur')->startOfDay()->utc()->toDateTimeString();
        $all = $manifest['orders'] + $manifest['reservations'];
        $expected = ['dashboard' => ['ordersToday' => count(array_filter($manifest['orders'], fn ($r) => $r['created_at'] >= $today)),
            'reservationsToday' => count(array_filter($manifest['reservations'], fn ($r) => $r['reservation_date'] === '2026-10-14')),
            'revenueToday' => array_sum(array_map(fn ($r) => $r['payment_status'] === 'paid' && $r['payment_at'] >= $today ? $r['total_cents'] : 0, $all)),
            'pendingOrders' => count(array_filter($manifest['orders'], fn ($r) => $r['status'] === 'pending')),
            'pendingReservations' => count(array_filter($manifest['reservations'], fn ($r) => $r['status'] === 'pending')), 'availableTables' => 11]];
        foreach ([7, 30] as $days) {
            $from = $anchor->subDays($days)->toDateTimeString();
            $until = $anchor->toDateTimeString();
            $orders = array_filter($manifest['orders'], fn ($r) => $r['created_at'] >= $from && $r['created_at'] < $until);
            $reservations = array_filter($manifest['reservations'], fn ($r) => $r['created_at'] >= $from && $r['created_at'] < $until);
            $popular = $times = [];
            foreach ($orders as $row) {
                if ($row['payment_status'] === 'paid' && ! in_array($row['status'], ['pending', 'cancelled'])) {
                    foreach ($row['lines'] as $line) {
                        $popular[$line['item_name']] = ($popular[$line['item_name']] ?? 0) + $line['quantity'];
                    }
                }
            }
            uksort($popular, fn ($a, $b) => $popular[$b] <=> $popular[$a] ?: strcmp($a, $b));
            foreach ($reservations as $row) {
                $slot = substr($row['reservation_time'], 0, 5);
                $times[$slot] = ($times[$slot] ?? 0) + 1;
            }
            arsort($times);
            $expected[$days] = ['sales' => ['total_orders' => count($orders),
                'completed_orders' => count(array_filter($orders, fn ($r) => $r['status'] === 'completed')),
                'cancelled_orders' => count(array_filter($orders, fn ($r) => $r['status'] === 'cancelled')),
                'revenue_cents' => array_sum(array_map(fn ($r) => $r['payment_status'] === 'paid' && $r['payment_at'] >= $from && $r['payment_at'] < $until ? $r['total_cents'] : 0, $manifest['orders'])),
                'popular_items' => array_slice($popular, 0, 8, true)],
                'reservations' => ['total_reservations' => count($reservations),
                    'completed_reservations' => count(array_filter($reservations, fn ($r) => $r['status'] === 'completed')),
                    'cancelled_reservations' => count(array_filter($reservations, fn ($r) => $r['status'] === 'cancelled')),
                    'guest_count' => array_sum(array_column($reservations, 'guest_count')), 'popular_times' => $times]];
        }

        return $expected;
    }
}
