<?php

use App\Console\Commands\GenerateDemoTransactions;
use App\Events\OrderPaymentVerified;
use App\Mail\OrderConfirmationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\BusinessTime;
use App\Services\DemoCatalogTransfer;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoTransactionGenerator;
use App\Services\DemoTransactionManifest;
use App\Services\Payments\PaymentEligibility;
use App\Services\ReceiptService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\MySqlTestDatabaseGuard;
use Tests\NonTransactionalDatabase;

uses(NonTransactionalDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 04:30:00', 'UTC'));
});

/** Replace only demo identity/catalog pin boundaries, never the protected test connection. */
class TransactionGeneratorUnderTest extends DemoTransactionGenerator
{
    public ?string $failure = null;

    protected function verifiedConnection(): Connection
    {
        MySqlTestDatabaseGuard::assertConnection(DB::connection());

        return DB::connection();
    }

    protected function verifiedCatalog(Connection $connection): array
    {
        return (new DemoCatalogTransfer)->readRows($connection->getPdo(), true);
    }

    protected function privateDirectory(): string
    {
        return Storage::disk('demo-attempts')->path('');
    }

    protected function applyEvent(array $event, array $row, array &$parents): void
    {
        parent::applyEvent($event, $row, $parents);
        if ($this->failure === 'during' && $event['key'] === 'O01') {
            throw new RuntimeException('Injected interruption.');
        }
    }

    protected function commit(Connection $connection): void
    {
        parent::commit($connection);
        if ($this->failure === 'after_commit') {
            throw new RuntimeException('Simulated lost commit acknowledgement.');
        }
    }
}

function transactionCatalog(): TransactionGeneratorUnderTest
{
    Http::preventStrayRequests();
    Storage::fake('demo-attempts');
    config(['mail.default' => 'array', 'payment.driver' => 'mock',
        'restaurant.opening_hours' => array_fill(1, 7, ['open' => '08:00', 'close' => '16:00'])]);
    User::factory()->create(['role' => 'admin']);
    foreach (range(1, 6) as $id) {
        MenuCategory::factory()->create(['id' => $id, 'is_active' => true]);
    }
    $items = [1 => ['Satay Skewers', 12], 3 => ['Roti Canai', 6], 4 => ['Nasi Lemak', 15],
        5 => ['Rendang', 22], 7 => ['Nasi Goreng', 14.5], 13 => ['Cendol', 8], 14 => ['Kuih Lapis', 6.5],
        16 => ['Teh Tarik', 5], 17 => ['Bandung', 5.5], 18 => ['Kopi O', 4], 19 => ['Roti Telur', 7.5],
        20 => ['Ulam-ulaman', 10], 21 => ['Begedil Kentang', 10], 22 => ['Ayam Masak Lemak Cili Api', 18],
        23 => ['Ayam Goreng Berempah', 16], 24 => ['Ikan Kembung Goreng', 15], 25 => ['Sambal Udang Petai', 20],
        26 => ['Kangkung Belacan', 12], 27 => ['Sayur Lodeh', 12], 28 => ['Sambal Telur', 10],
        29 => ['Ikan Bakar', 20], 30 => ['Ayam Percik', 18], 31 => ['Sup Ayam', 14], 32 => ['Sup Daging', 18],
        33 => ['Bubur Kacang Hijau', 10], 34 => ['Sirap Limau', 10], 35 => ['Teh O', 10]];
    foreach (range(1, 35) as $id) {
        [$name, $price] = $items[$id] ?? ['Unavailable fixture '.$id, 10];
        MenuItem::factory()->create(['id' => $id, 'menu_category_id' => 1 + $id % 6, 'name' => $name,
            'price' => $price, 'is_available' => isset($items[$id]), 'created_at' => $id >= 20 ? '2026-10-06 15:20:00' : ($id === 19 ? '2026-09-30 15:19:00' : '2026-09-08 14:55:00')]);
    }
    foreach ([4, 2, 4, 4, 4, 6, 6, 8, 8, 10, 3] as $i => $capacity) {
        Table::factory()->create(['id' => $i + 1, 'table_number' => 'T'.($i + 1), 'capacity' => $capacity,
            'status' => 'available', 'created_at' => '2026-09-08 14:55:00']);
    }

    return new TransactionGeneratorUnderTest;
}

test('inspection is deterministic read only and calculates the approved reports from its fixed anchor', function () {
    $generator = transactionCatalog();
    $before = $generator->protectedFingerprints(DB::connection());
    $manifest = $generator->run();

    expect($generator->run())->toBe($manifest);
    expect($manifest['anchor'])->toBe('2026-10-14T12:30:00+08:00');
    expect($manifest['orders'])->toHaveCount(36);
    expect($manifest['reservations'])->toHaveCount(24);
    expect($manifest['events'])->toHaveCount(201);
    expect($manifest['expected']['dashboard']['revenueToday'])->toBe(50400);
    expect($manifest['expected'][7]['sales']['revenue_cents'])->toBe(78100);
    expect($manifest['expected'][30]['sales']['revenue_cents'])->toBe(116800);
    expect($manifest['expected'][7]['reservations']['guest_count'])->toBe(71);
    expect($manifest['expected'][30]['reservations']['guest_count'])->toBe(86);
    expect($generator->protectedFingerprints(DB::connection()))->toBe($before);
    foreach (array_keys(DemoTransactionManifest::COUNTS) as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Storage::disk('demo-attempts')->assertMissing('demo02-v1.started.json');
    Http::assertNothingSent();
});

test('generation replays the full supported lifecycle and preserves catalog admin and nontransaction tables', function () {
    $generator = transactionCatalog();
    Mail::fake();
    $before = $generator->protectedFingerprints(DB::connection());
    $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
    $manifest = $generator->run(true);

    expect(now()->toDateTimeString())->toBe('2026-11-01 00:00:00');
    foreach (DemoTransactionManifest::COUNTS as $table => $count) {
        $this->assertDatabaseCount($table, $count);
    }
    expect(Payment::where('status', 'paid')->count())->toBe(38);
    expect(Payment::where('status', 'pending')->count())->toBe(5);
    expect(Payment::where('status', 'failed')->count())->toBe(5);
    expect(Order::where('status', 'completed')->count())->toBe(18);
    expect(Order::where('order_type', 'dine-in')->count())->toBe(18);
    expect(Reservation::where('status', 'completed')->count())->toBe(9);
    expect(Reservation::whereNotNull('checked_in_at')->count())->toBe(11);
    foreach (Payment::where('status', 'paid')->get() as $payment) {
        $parent = $payment->order_id ? $payment->order : $payment->reservation;
        expect(app(PaymentEligibility::class)->isEligible($parent, $parent->payment()->get()))->toBeTrue();
        expect(($payment->order_id !== null) xor ($payment->reservation_id !== null))->toBeTrue();
        expect(array_keys($payment->raw_response))->toBe(['reference', 'amount', 'status', 'currency']);
    }
    expect(Payment::where('provider_reference', 'like', '%-2')->count())->toBe(3);
    expect($generator->protectedFingerprints(DB::connection()))->toBe($before);
    $this->travelTo(CarbonImmutable::parse(DemoTransactionManifest::ANCHOR)->utc());
    $generator->verifyResult(DB::connection(), $manifest);
    $late = Reservation::where('reservation_number', 'DEMO02-R18')->firstOrFail();
    expect($late->status)->toBe('confirmed');
    expect($late->payment->paid_at->diffInMinutes($late->created_at, true))->toBe(20.0);
    foreach (['R20' => false, 'R21' => false, 'R22' => true, 'R23' => true] as $key => $available) {
        $reservation = Reservation::where('reservation_number', 'DEMO02-'.$key)->firstOrFail();
        $start = app(BusinessTime::class)->reservationStart($reservation->reservation_date->format('Y-m-d'), $reservation->reservation_time);
        expect(app(ReservationService::class)->isTableAvailable($reservation->table, $start))->toBe($available);
    }
    $held = Reservation::where('reservation_number', 'DEMO02-R20')->firstOrFail();
    foreach ([899 => false, 900 => false, 901 => true] as $seconds => $available) {
        $this->travelTo($held->created_at->toImmutable()->addSeconds($seconds));
        expect(app(ReservationService::class)->isTableAvailable($held->table, $held->reservation_date_time))->toBe($available);
    }
    Mail::assertSent(OrderConfirmationMail::class, 30);
    Mail::assertSent(ReservationConfirmationMail::class, 20);
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'Nonempty');
    Storage::disk('demo-attempts')->assertExists('demo02-v1.started.json');
    Storage::disk('demo-attempts')->assertExists('demo02-v1.completed.json');
    Http::assertNothingSent();
});

test('interruption rolls back rows retains the attempt and restores the clock without retry', function () {
    $generator = transactionCatalog();
    $before = $generator->protectedFingerprints(DB::connection());
    $generator->failure = 'during';
    $clock = Date::getTestNow();

    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'before commit');
    foreach (array_keys(DemoTransactionManifest::COUNTS) as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect(Date::getTestNow())->toEqual($clock);
    expect($generator->protectedFingerprints(DB::connection()))->toBe($before);
    Storage::disk('demo-attempts')->assertExists('demo02-v1.started.json');
    Storage::disk('demo-attempts')->assertMissing('demo02-v1.completed.json');
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'Prior attempt');
});

test('lost commit acknowledgement keeps committed data and blocks automatic retry', function () {
    $generator = transactionCatalog();
    $generator->failure = 'after_commit';
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'read-only reconciliation');
    foreach (DemoTransactionManifest::COUNTS as $table => $count) {
        $this->assertDatabaseCount($table, $count);
    }
    Storage::disk('demo-attempts')->assertMissing('demo02-v1.completed.json');
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'Nonempty');
});

test('real generator rejects every nondemo environment without writing', function (string $environment) {
    app()->instance('env', $environment);
    try {
        expect(fn () => (new DemoTransactionGenerator)->run(true))->toThrow(RuntimeException::class, 'demo CLI');
    } finally {
        app()->instance('env', 'testing');
    }
    $this->assertDatabaseCount('orders', 0);
})->with(['local', 'testing', 'production']);

test('nonempty destination is refused without changing the existing order', function () {
    $generator = transactionCatalog();
    $order = Order::factory()->create();
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'Nonempty');
    $this->assertModelExists($order);
    $this->assertDatabaseCount('orders', 1);
});

test('altered scenarios and historically impossible menu items fail before insertion', function () {
    $generator = transactionCatalog();
    $catalog = (new DemoCatalogTransfer)->readRows(DB::connection()->getPdo(), true);
    $manifest = $generator->run();
    $manifest['reservations']['R18']['guest_count'] = 4;
    expect(fn () => $generator->validate($manifest, $catalog))->toThrow(RuntimeException::class, 'versioned');
    MenuItem::findOrFail(4)->forceFill(['created_at' => '2026-10-14 04:30:00'])->save();
    expect(fn () => $generator->run())->toThrow(RuntimeException::class, 'historically');
    $this->assertDatabaseCount('orders', 0);
});

test('execution before the anchor is refused but inspection remains available', function () {
    $generator = transactionCatalog();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 04:29:59', 'UTC'));
    expect($generator->run()['version'])->toBe('demo02-v1');
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'future payment events');
    Storage::disk('demo-attempts')->assertMissing('demo02-v1.started.json');
    $this->assertDatabaseCount('orders', 0);
});

test('independent guarded MySQL sessions cannot generate concurrently', function () {
    $generator = transactionCatalog();
    $other = app('db.factory')->make(DB::connection()->getConfig(), 'demo02-contender');
    MySqlTestDatabaseGuard::assertConnection($other);
    expect($other->selectOne('SELECT CONNECTION_ID() AS id')->id)->not->toBe(DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
    expect($other->selectOne('SELECT GET_LOCK(?, 0) AS acquired', ['le_miting_test:demo02-v1'])->acquired)->toBe(1);
    try {
        expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'owns the lock');
        $this->assertDatabaseCount('orders', 0);
        Storage::disk('demo-attempts')->assertMissing('demo02-v1.started.json');
    } finally {
        $other->selectOne('SELECT RELEASE_LOCK(?)', ['le_miting_test:demo02-v1']);
        $other->disconnect();
    }
});

test('capacity and current menu candidate restrictions cannot be bypassed by the manifest', function () {
    $generator = transactionCatalog();
    Table::findOrFail(11)->update(['capacity' => 2]);
    expect(fn () => $generator->run())->toThrow(RuntimeException::class, 'suitability');
    Table::findOrFail(11)->update(['capacity' => 3]);
    MenuItem::findOrFail(4)->update(['is_available' => false]);
    expect(fn () => $generator->run())->toThrow(RuntimeException::class, 'historically');
    $this->assertDatabaseCount('reservations', 0);
});

test('command defaults to inspection and never accepts force or an alternate database', function () {
    $command = new GenerateDemoTransactions;
    $command->setLaravel(app());
    expect($command->getDefinition()->getOption('execute')->getDefault())->toBeFalse();
    expect($command->getDefinition()->hasOption('database'))->toBeFalse();
    expect($command->getDefinition()->hasOption('force'))->toBeFalse();
    $this->artisan('demo:transactions')->expectsOutputToContain('demo CLI')->assertFailed();
    $guard = new DemoEnvironmentGuard;
    $event = new CommandStarting('demo:transactions',
        new ArgvInput(['artisan', 'demo:transactions']), new NullOutput);
    $guard->protectCommand($event);
    $this->assertDatabaseCount('orders', 0);
});

test('partial post commit interruption permits repeatable read only reconciliation without replaying callbacks', function () {
    $generator = transactionCatalog();
    Mail::fake();
    $manifest = $generator->run();
    $protected = $generator->protectedFingerprints(DB::connection());
    $callbacksReached = 0;
    Event::listen(OrderPaymentVerified::class, function () use (&$callbacksReached): void {
        $callbacksReached++;
        if ($callbacksReached === 2) {
            throw new RuntimeException('Injected post-commit interruption, not a simulated PHP memory fatal.');
        }
    });

    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'Commit was attempted');

    expect($callbacksReached)->toBe(2);
    $attempt = Storage::disk('demo-attempts')->get('demo02-v1.started.json');
    $transactions = collect(array_keys(DemoTransactionManifest::COUNTS))->mapWithKeys(
        fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()],
    )->all();
    $sentCount = Mail::sent(OrderConfirmationMail::class)->count();
    $this->travelTo(CarbonImmutable::parse(DemoTransactionManifest::ANCHOR)->utc());
    foreach (range(1, 2) as $attemptNumber) {
        $generator->verifyResult(DB::connection(), $manifest);
        foreach ($transactions as $table => $rows) {
            expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($rows);
        }
        expect($generator->protectedFingerprints(DB::connection()))->toBe($protected);
        expect(Storage::disk('demo-attempts')->get('demo02-v1.started.json'))->toBe($attempt);
        Storage::disk('demo-attempts')->assertMissing('demo02-v1.completed.json');
    }
    expect($callbacksReached)->toBe(2);
    Mail::assertSent(OrderConfirmationMail::class, $sentCount);
    expect(fn () => $generator->run(true))->toThrow(RuntimeException::class, 'Nonempty');
    Http::assertNothingSent();
});

test('completion marker does not certify receipt delivery when listeners report rendering failures', function () {
    $generator = transactionCatalog();
    Exceptions::fake();
    $protected = $generator->protectedFingerprints(DB::connection());
    $this->mock(ReceiptService::class, function ($mock): void {
        $mock->shouldReceive('generateReceipt')->times(30)
            ->andThrow(RuntimeException::class, 'Injected order receipt rendering failure.');
        $mock->shouldReceive('generateReservationReceipt')->times(8)
            ->andThrow(RuntimeException::class, 'Injected reservation receipt rendering failure.');
    });

    $manifest = $generator->run(true);

    Exceptions::assertReportedCount(38);
    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Injected order receipt rendering failure.');
    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Injected reservation receipt rendering failure.');
    $this->travelTo(CarbonImmutable::parse(DemoTransactionManifest::ANCHOR)->utc());
    $generator->verifyResult(DB::connection(), $manifest);
    expect($generator->protectedFingerprints(DB::connection()))->toBe($protected);
    Storage::disk('demo-attempts')->assertExists('demo02-v1.completed.json');
    Http::assertNothingSent();
});
