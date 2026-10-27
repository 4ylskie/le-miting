<?php

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\MySqlTestDatabaseGuard;
use Tests\NonTransactionalDatabase;

uses(NonTransactionalDatabase::class);

/** @return array<string, mixed> */
function schemaPaymentRow(Order|Reservation $parent, string $status = 'paid', string $reference = 'SCHEMA-001'): array
{
    return [
        'order_id' => $parent instanceof Order ? $parent->id : null,
        'reservation_id' => $parent instanceof Reservation ? $parent->id : null,
        'provider' => 'fiuu', 'provider_reference' => $reference, 'status' => $status,
        'amount' => '40.00', 'currency' => 'MYR', 'raw_response' => '{"status":"'.$status.'"}',
        'paid_at' => $status === 'paid' ? '2026-10-09 03:00:00' : null,
        'created_at' => '2026-10-09 03:00:00', 'updated_at' => '2026-10-09 03:00:00',
    ];
}

function paymentSchemaRepair(): object
{
    return require database_path('migrations/2026_10_09_155139_repair_payment_parent_integrity.php');
}

/** Rebuild only the authenticated dedicated test schema, outside a transaction. */
function paymentSchemaSandbox(Closure $run, bool $legacy = true, bool $paymentForeignKeys = true): void
{
    MySqlTestDatabaseGuard::assertEnvironment(app());
    MySqlTestDatabaseGuard::assertConnection(DB::connection());
    if (DB::transactionLevel() !== 0) {
        throw new LogicException('Payment schema tests must not run inside a transaction.');
    }

    $originalFailure = null;
    try {
        Schema::dropAllTables();
        if ($legacy) {
            foreach (['2026_09_08_145033_create_tables_table.php', '2026_09_08_145034_create_orders_table.php', '2026_09_08_145045_create_reservations_table.php'] as $file) {
                (require database_path('migrations/'.$file))->up();
            }
            Schema::create('payments', function (Blueprint $table) use ($paymentForeignKeys): void {
                $table->id();
                $table->foreignId('order_id')->nullable();
                $table->foreignId('reservation_id')->nullable();
                if ($paymentForeignKeys) {
                    $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
                    $table->foreign('reservation_id')->references('id')->on('reservations')->nullOnDelete();
                }
                $table->string('provider')->default('fiuu');
                $table->string('provider_reference')->unique();
                $table->string('status')->default('pending');
                $table->decimal('amount', 10, 2);
                $table->string('currency')->default('MYR');
                $table->text('raw_response')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
        $run();
    } catch (Throwable $exception) {
        $originalFailure = $exception;

        throw $exception;
    } finally {
        try {
            MySqlTestDatabaseGuard::assertConnection(DB::connection());
            Schema::dropAllTables();
            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('Failed to restore the isolated test schema.');
            }
        } catch (Throwable $restoreFailure) {
            if ($originalFailure === null) {
                throw $restoreFailure;
            }
        }
    }
}

test('database accepts exactly one valid payment parent across financial statuses', function (string $kind, string $status) {
    $parent = $kind === 'order' ? Order::factory()->create() : Reservation::factory()->create();
    $row = schemaPaymentRow($parent, $status);

    DB::table('payments')->insert($row);

    $this->assertDatabaseHas('payments', ['provider_reference' => 'SCHEMA-001', 'status' => $status, 'order_id' => $row['order_id'], 'reservation_id' => $row['reservation_id']]);
})->with(['order', 'reservation'])->with(['pending', 'paid', 'failed', 'refunded']);

test('database rejects missing or dual parents on raw insert and update', function (string $association, string $operation) {
    $order = Order::factory()->create();
    $reservation = Reservation::factory()->create();
    $row = schemaPaymentRow($order);
    $ids = $association === 'neither' ? ['order_id' => null, 'reservation_id' => null] : ['order_id' => $order->id, 'reservation_id' => $reservation->id];
    if ($operation === 'update') {
        DB::table('payments')->insert($row);
    }
    $before = DB::table('payments')->get()->toArray();

    expect(fn () => $operation === 'insert' ? DB::table('payments')->insert(array_merge($row, $ids)) : DB::table('payments')->update($ids))
        ->toThrow(QueryException::class);

    expect(DB::table('payments')->get()->toArray())->toEqual($before);
})->with(['neither', 'both'])->with(['insert', 'update']);

test('database rejects nonexistent parent identifiers', function (string $kind, string $operation) {
    $parent = $kind === 'order' ? Order::factory()->create() : Reservation::factory()->create();
    $row = schemaPaymentRow($parent);
    if ($operation === 'update') {
        DB::table('payments')->insert($row);
    }
    $before = DB::table('payments')->get()->toArray();

    expect(fn () => $operation === 'insert'
        ? DB::table('payments')->insert(array_merge($row, [$kind.'_id' => 999999]))
        : DB::table('payments')->update([$kind.'_id' => 999999]))->toThrow(QueryException::class);

    expect(DB::table('payments')->get()->toArray())->toEqual($before);
})->with(['order', 'reservation'])->with(['insert', 'update']);

test('parent deletion preserves every associated payment status', function (string $kind, string $status, string $operation) {
    $parent = $kind === 'order' ? Order::factory()->create(['status' => 'completed']) : Reservation::factory()->create(['status' => 'completed']);
    DB::table('payments')->insert(schemaPaymentRow($parent, $status));
    $before = DB::table('payments')->first();

    expect(fn () => $operation === 'eloquent' ? $parent->delete() : DB::table($parent->getTable())->where('id', $parent->id)->delete())
        ->toThrow(QueryException::class);

    $this->assertModelExists($parent);
    expect(DB::table('payments')->first())->toEqual($before);
})->with(['order', 'reservation'])->with(['pending', 'paid', 'failed', 'refunded'])->with(['eloquent', 'raw']);

test('a table cascade cannot delete a reservation with financial history', function () {
    $table = Table::factory()->create(['capacity' => 4]);
    $reservation = Reservation::factory()->create(['table_id' => $table->id]);
    DB::table('payments')->insert(schemaPaymentRow($reservation));

    expect(fn () => $table->delete())->toThrow(QueryException::class);

    $this->assertModelExists($table);
    $this->assertModelExists($reservation);
    $this->assertDatabaseHas('payments', ['reservation_id' => $reservation->id, 'status' => 'paid']);
});

test('parent primary key changes cannot detach a payment', function (string $kind) {
    $parent = $kind === 'order' ? Order::factory()->create() : Reservation::factory()->create();
    DB::table('payments')->insert(schemaPaymentRow($parent));

    expect(fn () => DB::table($parent->getTable())->where('id', $parent->id)->update(['id' => 999999]))->toThrow(QueryException::class);

    $this->assertModelExists($parent);
    $this->assertDatabaseHas('payments', [$kind.'_id' => $parent->id]);
})->with(['order', 'reservation']);

test('a complete fresh migration succeeds on an isolated empty database', function () {
    paymentSchemaSandbox(function () {
        expect(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]))->toBe(0);
        expect(Schema::hasTable('payments'))->toBeTrue();
        expect(Schema::hasTable('reservations'))->toBeTrue();
        expect(Schema::getForeignKeys('payments'))->toHaveCount(2);
        expect(DB::table('information_schema.table_constraints')->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'payments')->where('CONSTRAINT_NAME', 'payments_exactly_one_parent')
            ->where('CONSTRAINT_TYPE', 'CHECK')->value('ENFORCED'))->toBe('YES');
        expect((int) DB::scalar('SELECT @@session.foreign_key_checks'))->toBe(1);
        $order = Order::factory()->create();
        DB::table('payments')->insert(schemaPaymentRow($order));
        expect(fn () => $order->delete())->toThrow(QueryException::class);
    }, legacy: false);
});

test('legacy repair preserves rows references audit data and uniqueness and is repeatable', function () {
    paymentSchemaSandbox(function () {
        $order = Order::factory()->create();
        $reservation = Reservation::factory()->create();
        foreach (['pending', 'paid', 'failed', 'refunded'] as $status) {
            foreach ([$order, $reservation] as $index => $parent) {
                DB::table('payments')->insert(schemaPaymentRow($parent, $status, 'LEGACY-'.$status.'-'.$index));
            }
        }
        $before = DB::table('payments')->orderBy('id')->get()->toArray();

        paymentSchemaRepair()->up();
        paymentSchemaRepair()->up();

        expect(DB::table('payments')->orderBy('id')->get()->toArray())->toEqual($before);
        expect(Schema::getForeignKeys('payments'))->toHaveCount(2);
        expect((int) DB::scalar('SELECT @@session.foreign_key_checks'))->toBe(1);
        foreach (Schema::getForeignKeys('payments') as $foreignKey) {
            expect($foreignKey['on_delete'])->toBeIn(['restrict', 'no action']);
            expect($foreignKey['on_update'])->toBeIn(['restrict', 'no action']);
        }
        expect(fn () => $order->delete())->toThrow(QueryException::class);
        expect(fn () => $reservation->delete())->toThrow(QueryException::class);
        expect(fn () => DB::table('payments')->insert(schemaPaymentRow($order, 'paid', 'LEGACY-paid-0')))->toThrow(QueryException::class);
        expect(fn () => DB::table('payments')->update(['order_id' => null, 'reservation_id' => null]))->toThrow(QueryException::class);
    });
});

test('repair refuses invalid historical associations before changing rows or constraints', function (string $invalid) {
    paymentSchemaSandbox(function () use ($invalid) {
        $order = Order::factory()->create();
        $reservation = Reservation::factory()->create();
        $ids = match ($invalid) {
            'neither' => ['order_id' => null, 'reservation_id' => null],
            'both' => ['order_id' => $order->id, 'reservation_id' => $reservation->id],
            'missing order' => ['order_id' => 999999, 'reservation_id' => null],
            'missing reservation' => ['order_id' => null, 'reservation_id' => 999999],
        };
        DB::table('payments')->insert(array_merge(schemaPaymentRow($order), $ids));
        $before = DB::table('payments')->get()->toArray();
        $schema = DB::select('SHOW CREATE TABLE payments');

        expect(fn () => paymentSchemaRepair()->up())->toThrow(RuntimeException::class, 'existing payments have missing, dual or nonexistent parents');

        expect(DB::table('payments')->get()->toArray())->toEqual($before);
        expect(DB::select('SHOW CREATE TABLE payments'))->toEqual($schema);
    }, paymentForeignKeys: false);
})->with(['neither', 'both', 'missing order', 'missing reservation']);

test('a partially created payment table can resume without replacement', function () {
    paymentSchemaSandbox(function () {
        $order = Order::factory()->create();
        DB::table('payments')->insert(schemaPaymentRow($order));
        $before = DB::table('payments')->get()->toArray();

        (require database_path('migrations/2026_09_08_145044_create_payments_table.php'))->up();
        paymentSchemaRepair()->up();

        expect(DB::table('payments')->get()->toArray())->toEqual($before);
        expect(fn () => $order->delete())->toThrow(QueryException::class);
    }, paymentForeignKeys: false);
});

test('financial schema rollbacks fail without removing data or protection', function (string $file) {
    $order = Order::factory()->create();
    DB::table('payments')->insert(schemaPaymentRow($order));

    expect(fn () => (require database_path('migrations/'.$file))->down())->toThrow(LogicException::class);

    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
    expect(fn () => $order->delete())->toThrow(QueryException::class);
})->with([
    '2026_09_08_145044_create_payments_table.php',
    '2026_09_16_123958_enforce_payments_have_exactly_one_parent.php',
    '2026_10_09_155139_repair_payment_parent_integrity.php',
]);
