<?php

namespace App\Services;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\MockPaymentService;
use App\Services\Payments\PaymentEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Throwable;

class DemoTransactionGenerator
{
    /** @return array<string, list<array<string, mixed>>> */
    protected function verifiedCatalog(Connection $connection): array
    {
        $transfer = new DemoCatalogTransfer;
        $file = storage_path('app/private/demo-catalog.json');
        if (! is_file($file)) {
            throw new RuntimeException('Approved catalog bundle is missing.');
        }
        $bundle = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $bundle['rows'] = $transfer->readRows($connection->getPdo());
        foreach ($bundle['assets'] as $path => &$asset) {
            if (! preg_match('#^menu/[a-z0-9]+\.jpg$#', $path)) {
                throw new RuntimeException('Unexpected catalog asset path.');
            }
            $file = storage_path('app/public/'.$path);
            if (! is_file($file) || hash_file('sha256', $file) !== $asset['sha256']) {
                throw new RuntimeException('Catalog image checksum mismatch.');
            }
            $asset['bytes'] = base64_encode(file_get_contents($file));
        }
        unset($asset);
        $transfer->assertApproved($bundle, config_path('reservation.php'));

        return $bundle['rows'];
    }

    protected function privateDirectory(): string
    {
        return storage_path('app/private');
    }

    /** Inspection is read-only. Execution requires a second explicit CLI option. */
    public function run(bool $execute = false): array
    {
        $connection = $this->verifiedConnection();
        $this->assertEmpty($connection);
        $catalog = $this->verifiedCatalog($connection);
        $manifest = (new DemoTransactionManifest)->build($catalog);
        $this->validate($manifest, $catalog);
        $marker = $this->privateDirectory().'/demo02-v1.started.json';
        if (file_exists($marker)) {
            throw new RuntimeException('Prior attempt exists; reconcile read-only. Do not retry.');
        }
        if (! $execute) {
            return $manifest;
        }
        if (Date::now('UTC')->lessThan(CarbonImmutable::parse(DemoTransactionManifest::ANCHOR))) {
            throw new RuntimeException('Execution before the fixed reference instant would create future payment events.');
        }
        if ($connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
            throw new RuntimeException('Generator requires ownership of its transaction.');
        }
        $pdo = $connection->getPdo();
        $lockName = $connection->getDatabaseName().':demo02-v1';
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Another generation attempt owns the lock.');
        }
        $clock = Date::getTestNow();
        $commitAttempted = false;
        try {
            $this->verifiedConnection();
            $this->assertEmpty($connection);
            $this->verifiedCatalog($connection);
            $protected = $this->protectedFingerprints($connection);
            $this->writeExclusive($marker, ['state' => 'started', 'manifest' => $manifest,
                'sha256' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)), 'protected' => $protected]);
            $connection->beginTransaction();
            $this->verifiedConnection();
            $this->assertEmpty($connection, lock: true);
            foreach (['menu_categories', 'menu_items', 'tables', 'users'] as $table) {
                $connection->table($table)->orderBy('id')->lockForUpdate()->get();
            }
            $this->verifiedCatalog($connection);
            $parents = [];
            foreach ($manifest['events'] as $event) {
                Date::setTestNow(CarbonImmutable::parse($event['at'], 'UTC'));
                $row = $manifest['orders'][$event['key']] ?? $manifest['reservations'][$event['key']];
                $this->applyEvent($event, $row, $parents);
            }
            Date::setTestNow(CarbonImmutable::parse(DemoTransactionManifest::ANCHOR)->utc());
            $this->verifyResult($connection, $manifest);
            if ($this->protectedFingerprints($connection) !== $protected) {
                throw new RuntimeException('Protected demo data changed; refusing commit.');
            }
            $this->verifiedCatalog($connection);
            $commitAttempted = true;
            $this->commit($connection);
            $this->verifyResult($connection, $manifest);
            if ($this->protectedFingerprints($connection) !== $protected) {
                throw new RuntimeException('Post-commit protected-data mismatch.');
            }
            $this->writeExclusive($this->privateDirectory().'/demo02-v1.completed.json',
                ['state' => 'verified', 'manifest_sha256' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
                    'counts' => DemoTransactionManifest::COUNTS, 'protected' => $protected]);

            return $manifest;
        } catch (Throwable $exception) {
            if (! $commitAttempted && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            throw new RuntimeException($commitAttempted
                ? 'Commit was attempted. Outcome requires read-only reconciliation; no automatic retry or cleanup.'
                : 'Generation failed before commit. Retain the attempt record and inspect before any retry.', previous: $exception);
        } finally {
            Date::setTestNow($clock);
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
            if ((int) $release->fetchColumn() !== 1) {
                throw new RuntimeException('Could not confirm execution-lock release; inspect before further action.');
            }
        }
    }

    protected function commit(Connection $connection): void
    {
        $connection->commit();
    }

    private function writeExclusive(string $path, array $data): void
    {
        $stream = @fopen($path, 'x+b');
        if ($stream === false) {
            throw new RuntimeException('Attempt record already exists or cannot be created.');
        }
        try {
            $bytes = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('Could not durably record the attempt.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function assertEmpty(Connection $connection, bool $lock = false): void
    {
        (new DemoCatalogTransfer)->assertTargetSchema($connection);
        foreach (array_keys(DemoTransactionManifest::COUNTS) as $table) {
            $query = $connection->table($table);
            $nonempty = $lock ? $query->lockForUpdate()->first() !== null : $query->exists();
            if ($nonempty) {
                throw new RuntimeException('Nonempty transaction destination; no overwrite permitted.');
            }
        }
        if ($connection->table('users')->count() !== 1 || $connection->table('users')->where('role', 'admin')->count() !== 1) {
            throw new RuntimeException('Exactly one existing demo administrator is required.');
        }
    }

    /** @return array<string, string> */
    public function protectedFingerprints(Connection $connection): array
    {
        $hashes = [];
        foreach (array_diff((new DemoCatalogTransfer)->assertTargetSchema($connection), array_keys(DemoTransactionManifest::COUNTS)) as $table) {
            $rows = $connection->table($table)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->all();
            sort($rows, SORT_STRING);
            $hashes[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }

        return $hashes;
    }

    /** Reject edits to the versioned manifest and validate allocation before inserting. */
    public function validate(array $manifest, array $catalog): void
    {
        if ($manifest !== (new DemoTransactionManifest)->build($catalog)) {
            throw new RuntimeException('Manifest differs from the versioned scenario definition.');
        }
        $clock = Date::getTestNow();
        try {
            $tables = array_column($catalog['tables'], null, 'id');
            $intervals = [];
            foreach ($manifest['reservations'] as $row) {
                Date::setTestNow(CarbonImmutable::parse($row['created_at'], 'UTC'));
                $tableRow = $tables[$row['table_id']] ?? throw new RuntimeException('Missing table.');
                $table = new Table;
                $table->setRawAttributes($tableRow);
                $start = app(BusinessTime::class)->reservationStart($row['reservation_date'], $row['reservation_time']);
                app(ReservationSlots::class)->assertValid($start);
                if ($tableRow['created_at'] > $row['created_at'] || $table->status !== 'available'
                    || ! app(ReservationService::class)->isTableSuitable($table, $row['guest_count'])) {
                    throw new RuntimeException('Manifest violates table suitability or chronology.');
                }
                foreach ($intervals[$row['table_id']] ?? [] as [$otherStart, $otherEnd]) {
                    if ($start < $otherEnd && $start->addMinutes(120) > $otherStart) {
                        throw new RuntimeException('Manifest contains overlapping appointments.');
                    }
                }
                $intervals[$row['table_id']][] = [$start, $start->addMinutes(120)];
            }
            $anchor = CarbonImmutable::parse(DemoTransactionManifest::ANCHOR)->utc()->toDateTimeString();
            foreach ($manifest['events'] as $event) {
                if ($event['at'] > $anchor) {
                    throw new RuntimeException('Manifest contains an event after its reference instant.');
                }
            }
        } finally {
            Date::setTestNow($clock);
        }
    }

    /** Replay application lifecycle services; only new fictional identifiers are replaced. */
    protected function applyEvent(array $event, array $row, array &$parents): void
    {
        $key = $event['key'];
        $isOrder = str_starts_with($key, 'O');
        if ($event['action'] === 'create') {
            $data = array_intersect_key($row, array_flip(['customer_name', 'customer_email', 'customer_phone',
                'order_type', 'table_id', 'guest_count', 'reservation_date', 'reservation_time']));
            if ($isOrder) {
                $cart = new class($row['lines']) extends CartService
                {
                    public function __construct(private array $lines) {}

                    public function orderItems(): array
                    {
                        return $this->lines;
                    }
                };
                $parent = (new OrderService($cart, app(PaymentEligibility::class)))->createFromCart($data + ['notes' => 'Fictional demonstration; historical prices use the restored catalog.']);
                $parent->update(['order_number' => $row['number']]);
            } else {
                $service = app(ReservationService::class);
                $data += ['special_request' => 'Fictional demonstration; not a live booking.', 'food_items' => $row['lines']];
                $parent = $row['reservation_type'] === 'table_only' ? $service->createTableOnly($data) : $service->createTableAndFood($data);
                $parent->update(['reservation_number' => $row['number']]);
            }
            $parents[$key] = $parent;
            if ($row['payment_status'] !== null) {
                Payment::create(['order_id' => $isOrder ? $parent->id : null, 'reservation_id' => $isOrder ? null : $parent->id,
                    'provider' => 'fiuu', 'provider_reference' => 'PAY-DEMO02-'.$key.'-1', 'status' => 'pending',
                    'amount' => number_format($row['total_cents'] / 100, 2, '.', ''), 'currency' => 'MYR']);
            }

            return;
        }
        $parent = $parents[$key]->refresh();
        if (in_array($event['action'], ['success', 'failure', 'duplicate', 'retry'])) {
            $payment = $parent->payment()->firstOrFail();
            if ($event['action'] === 'retry') {
                $payment = app(PaymentRetryService::class)->retry($payment);
                $payment->update(['provider_reference' => 'PAY-DEMO02-'.$key.'-2']);
            } else {
                app(MockPaymentService::class)->processDemoOutcome($payment, $event['action'] === 'failure' ? 'failure' : 'success');
            }
        } elseif ($event['action'] === 'check_in') {
            app(ReservationService::class)->checkIn($parent->reservation_number);
        } else {
            $error = $isOrder ? app(OrderService::class)->transition($parent->id, $event['action'], $parent->status)
                : app(ReservationService::class)->transition($parent->id, $event['action'], $parent->status);
            if ($error !== null) {
                throw new RuntimeException('Scenario transition rejected: '.$key.' '.$event['action'].'.');
            }
        }
    }

    /** Verify snapshots, payments and independently calculated reports before commit. */
    public function verifyResult(Connection $connection, array $manifest): void
    {
        foreach (DemoTransactionManifest::COUNTS as $table => $count) {
            if ($connection->table($table)->count() !== $count) {
                throw new RuntimeException('Unexpected generated record count: '.$table);
            }
        }
        foreach ($manifest['orders'] + $manifest['reservations'] as $key => $row) {
            $isOrder = str_starts_with($key, 'O');
            $parent = $isOrder ? Order::where('order_number', $row['number'])->firstOrFail()
                : Reservation::where('reservation_number', $row['number'])->firstOrFail();
            if ($parent->status !== $row['status'] || $parent->created_at->format('Y-m-d H:i:s') !== $row['created_at']
                || (int) round((float) $parent->total * 100) !== $row['total_cents']) {
                throw new RuntimeException('Parent state or total mismatch: '.$key);
            }
            if (! $isOrder && $parent->checked_in_at?->format('Y-m-d H:i:s') !== $row['checked_in_at']) {
                throw new RuntimeException('Check-in chronology mismatch: '.$key);
            }
            $fields = ['customer_name', 'customer_email', 'customer_phone'];
            $fields = array_merge($fields, $isOrder ? ['order_type'] : ['table_id', 'guest_count', 'duration_minutes', 'reservation_type', 'reservation_time']);
            foreach ($fields as $field) {
                if ((string) $parent->{$field} !== (string) $row[$field]) {
                    throw new RuntimeException('Parent field mismatch: '.$key.' '.$field);
                }
            }
            if (! $isOrder && $parent->reservation_date->toDateString() !== $row['reservation_date']) {
                throw new RuntimeException('Appointment date mismatch: '.$key);
            }
            $lines = ($isOrder ? $parent->orderItems : $parent->reservationFoods)->map(fn ($line) => [
                'menu_item_id' => (int) $line->menu_item_id, 'item_name' => $line->item_name,
                'unit_cents' => (int) round((float) $line->unit_price * 100), 'quantity' => (int) $line->quantity,
            ])->sortBy('menu_item_id')->values()->all();
            if ($lines !== collect($row['lines'])->sortBy('menu_item_id')->values()->all()) {
                throw new RuntimeException('Snapshot mismatch: '.$key);
            }
            $payments = Payment::where($isOrder ? 'order_id' : 'reservation_id', $parent->id)->get();
            if ($payments->count() !== ($row['payment_status'] === null ? 0 : 1)) {
                throw new RuntimeException('Payment relationship mismatch: '.$key);
            }
            if ($row['payment_status'] !== null) {
                $payment = $payments->sole();
                if ($payment->status !== $row['payment_status'] || $payment->currency !== 'MYR'
                    || $payment->provider_reference !== 'PAY-DEMO02-'.$key.($row['retry'] ? '-2' : '-1')
                    || $payment->created_at->format('Y-m-d H:i:s') !== $row['created_at']
                    || (int) round((float) $payment->amount * 100) !== $row['total_cents']
                    || $payment->paid_at?->format('Y-m-d H:i:s') !== ($row['payment_status'] === 'paid' ? $row['payment_at'] : null)
                    || ($row['payment_status'] === 'paid' && ! app(PaymentEligibility::class)->isEligible($parent, $payments))) {
                    throw new RuntimeException('Payment integrity mismatch: '.$key);
                }
            }
        }
        $anchor = CarbonImmutable::parse(DemoTransactionManifest::ANCHOR)->utc();
        foreach ([7, 30] as $days) {
            $sales = app(ReportService::class)->sales($anchor->subDays($days), $anchor);
            $sales['revenue_cents'] = (int) round((float) $sales['total_revenue'] * 100);
            unset($sales['total_revenue']);
            $sales['popular_items'] = $sales['popular_items']->mapWithKeys(fn ($r) => [$r->item_name => (int) $r->total_quantity])->all();
            $reservations = app(ReportService::class)->reservations($anchor->subDays($days), $anchor);
            $reservations['guest_count'] = (int) $reservations['guest_count'];
            $reservations['popular_times'] = $reservations['popular_times']->mapWithKeys(fn ($r) => [$r->time_slot => (int) $r->reservation_count])->all();
            if ($sales != $manifest['expected'][$days]['sales'] || $reservations != $manifest['expected'][$days]['reservations']) {
                throw new RuntimeException('Report results differ from manifest predictions.');
            }
        }
        $dashboard = app(DashboardController::class)->index(app(BusinessTime::class))->getData();
        $dashboard['revenueToday'] = (int) round((float) $dashboard['revenueToday'] * 100);
        if (array_intersect_key($dashboard, $manifest['expected']['dashboard']) !== $manifest['expected']['dashboard']) {
            throw new RuntimeException('Dashboard results differ from manifest predictions.');
        }
    }

    protected function verifiedConnection(): Connection
    {
        if (PHP_SAPI !== 'cli' || ! app()->environment('demo')) {
            throw new RuntimeException('Transactions require the isolated demo CLI environment.');
        }
        app(DemoEnvironmentGuard::class)->verify(app());
        if (! app('db.factory') instanceof DemoGuardedConnectionFactory) {
            throw new RuntimeException('Demo connection factory missing.');
        }
        $connection = app('db')->connection('mysql');
        $guard = new DemoDatabaseGuard;
        $guard->assertConfiguration($connection->getConfig());
        $guard->assertPdo($connection->getPdo());

        return $connection;
    }
}
