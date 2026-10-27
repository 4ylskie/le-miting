<?php

use App\Events\OrderPaymentVerified;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PaymentRetryService;
use App\Services\Payments\PaymentApplier;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/** @param array<string, mixed> $attributes */
function popularReportOrder(array $attributes = [], string $name = 'Snapshot dish', int $quantity = 3): Order
{
    $order = Order::factory()->create([
        'status' => 'confirmed', 'total' => 30, 'created_at' => '2026-10-05 12:00:00', ...$attributes,
    ]);
    $order->orderItems()->create([
        'menu_item_id' => null, 'item_name' => $name, 'unit_price' => 10, 'quantity' => $quantity,
    ]);

    return $order;
}

test('popular items require accepted status and ADM05 financial eligibility', function (string $status, string $problem, bool $included) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC'));
    $order = popularReportOrder(['status' => $status, 'total' => $problem === 'within tolerance' ? 40 : 30]);
    if ($problem !== 'missing') {
        $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => $order->total, 'paid_at' => now()->subDay()]);
        match ($problem) {
            'pending', 'failed', 'refunded' => $payment->update(['status' => $problem]),
            'timestamp' => $payment->update(['paid_at' => null]),
            'currency' => $payment->update(['currency' => 'USD']),
            'amount' => $payment->update(['amount' => 29]),
            'within tolerance' => $payment->update(['amount' => 40.01]),
            'floating boundary' => $payment->update(['amount' => 30.01]),
            'wrong parent' => $payment->update(['order_id' => Order::factory()->create()->id]),
            'two paid' => Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 30]),
            'paid and failed' => Payment::factory()->create(['order_id' => $order->id, 'amount' => 30, 'status' => 'failed']),
            'paid and out of window' => Payment::factory()->create(['order_id' => $order->id, 'amount' => 30, 'created_at' => now()->subMonths(2)]),
            default => null,
        };
    }

    $items = app(ReportService::class)->sales(now()->subDays(7), now())['popular_items'];

    expect($items->pluck('total_quantity', 'item_name')->map(fn ($quantity) => (int) $quantity)->all())
        ->toBe($included ? ['Snapshot dish' => 3] : []);
})->with([
    'pending unpaid' => ['pending', 'pending', false],
    'failed' => ['confirmed', 'failed', false],
    'paid pending' => ['pending', 'paid', false],
    'paid confirmed' => ['confirmed', 'paid', true],
    'paid preparing' => ['preparing', 'paid', true],
    'paid ready' => ['ready', 'paid', true],
    'paid completed' => ['completed', 'paid', true],
    'cancelled unpaid' => ['cancelled', 'pending', false],
    'cancelled paid' => ['cancelled', 'paid', false],
    'refunded' => ['completed', 'refunded', false],
    'missing' => ['confirmed', 'missing', false],
    'two paid' => ['confirmed', 'two paid', false],
    'paid and failed' => ['confirmed', 'paid and failed', false],
    'out of window second payment' => ['confirmed', 'paid and out of window', false],
    'wrong parent' => ['confirmed', 'wrong parent', false],
    'missing timestamp' => ['confirmed', 'timestamp', false],
    'wrong currency' => ['confirmed', 'currency', false],
    'wrong amount' => ['confirmed', 'amount', false],
    'existing amount tolerance' => ['confirmed', 'within tolerance', true],
    'existing float boundary behavior' => ['confirmed', 'floating boundary', false],
]);

test('popular items batch candidates and complete payments without per order queries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC'));
    for ($index = 0; $index < 501; $index++) {
        $order = popularReportOrder();
        Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 30]);
    }
    Payment::factory()->create(['order_id' => $order->id, 'status' => 'failed', 'amount' => 30]);
    $queryLog = [];
    DB::listen(function ($query) use (&$queryLog): void {
        $queryLog[] = $query->sql;
    });

    $result = app(ReportService::class)->sales(now()->subDays(7), now());

    expect((int) $result['popular_items']->sole()->total_quantity)->toBe(1500);
    expect($result['total_orders'])->toBe(501);
    expect($queryLog)->toHaveCount(9);
    expect(collect($queryLog)->filter(fn ($sql) => str_contains($sql, 'limit 500')))->toHaveCount(2);
    expect(collect($queryLog)->filter(fn ($sql) => str_contains($sql, 'for update')))->toBeEmpty();
});

test('all report downloads reject guests and non administrators', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('admin.login'));

    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route($routeName))->assertForbidden();
})->with([
    'admin.reports.sales-weekly', 'admin.reports.sales-monthly',
    'admin.reports.reservations-weekly', 'admin.reports.reservations-monthly',
]);

test('popular items count a successful payment once after callback replay or retry', function (bool $retry) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC'));
    $order = popularReportOrder(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => 30, 'status' => $retry ? 'failed' : 'pending']);
    Event::fake([OrderPaymentVerified::class]);
    if ($retry) {
        $payment = app(PaymentRetryService::class)->retry($payment);
    }
    $callback = ['reference' => $payment->provider_reference, 'amount' => 30, 'currency' => 'MYR'];

    app(PaymentApplier::class)->applyVerifiedPayment($callback);
    app(PaymentApplier::class)->applyVerifiedPayment($callback);
    $result = app(ReportService::class)->sales(now()->subDays(7), now());

    expect((int) $result['popular_items']->sole()->total_quantity)->toBe(3);
    expect($order->fresh()->status)->toBe('confirmed');
    $this->assertDatabaseCount('payments', 1);
    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);
})->with(['callback replay' => false, 'failed then retried' => true]);

test('popular items use creation boundaries regardless of payment dates', function (string $created, string $paid, bool $included) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC'));
    $order = popularReportOrder(['created_at' => $created]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 30, 'paid_at' => $paid]);

    $items = app(ReportService::class)->sales(now()->subDays(7), now())['popular_items'];

    expect($items->count())->toBe($included ? 1 : 0);
})->with([
    'lower inclusive' => ['2026-10-03 12:00:00', '2026-10-04 12:00:00', true],
    'before lower paid inside' => ['2026-10-03 11:59:59', '2026-10-04 12:00:00', false],
    'before upper' => ['2026-10-10 11:59:59', '2026-10-10 11:59:59', true],
    'upper exclusive' => ['2026-10-10 12:00:00', '2026-10-10 12:00:00', false],
    'after upper' => ['2026-10-10 12:00:01', '2026-10-10 12:00:01', false],
    'paid after window' => ['2026-10-05 12:00:00', '2026-10-11 12:00:00', true],
]);

test('popular items aggregate historical snapshots and rank the top eight deterministically', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC'));
    $item = MenuItem::factory()->create(['name' => 'Original menu name']);
    foreach (['Zulu', 'India', 'Hotel', 'Golf', 'Foxtrot', 'Echo', 'Delta', 'Charlie', 'Bravo', 'Alpha'] as $name) {
        $order = popularReportOrder([], $name, 2);
        $order->orderItems()->update(['menu_item_id' => $item->id]);
        Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 30]);
    }
    $extra = popularReportOrder([], 'Zulu', 3);
    Payment::factory()->paid()->create(['order_id' => $extra->id, 'amount' => 30]);
    $item->update(['name' => 'Renamed menu name']);
    $item->delete();
    $before = DB::table('order_items')->orderBy('id')->get();

    $items = app(ReportService::class)->sales(now()->subDays(7), now())['popular_items'];

    expect($items->pluck('item_name')->all())->toBe(['Zulu', 'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf']);
    expect($items->pluck('total_quantity')->map(fn ($quantity) => (int) $quantity)->all())->toBe([5, 2, 2, 2, 2, 2, 2, 2]);
    expect(DB::table('order_items')->orderBy('id')->get())->toEqual($before);
});

test('sales PDFs describe an empty qualifying population accurately', function (bool $hasOrders) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC'));
    if ($hasOrders) {
        popularReportOrder(['status' => 'pending']);
    }
    $data = app(ReportService::class)->sales(now()->subDays(7), now());

    expect($data['popular_items'])->toBeEmpty();
    expect($data['total_orders'])->toBe($hasOrders ? 1 : 0);
    $this->view('admin.reports.pdfs.sales', [
        'data' => $data, 'period' => 'weekly', 'periodLabel' => 'Last 7 days',
        'from' => now()->subDays(7), 'generatedAt' => now(),
    ])->assertSee('No qualifying sold items were recorded in this period.')
        ->assertDontSee('No orders were recorded in this period.');
    $this->actingAs($this->admin)->get(route('admin.reports.sales-weekly'))->assertOk()->assertDownload('weekly-sales-report.pdf');
})->with(['no orders' => false, 'only ineligible orders' => true]);

test('an administrator can view the reports page listing all four reports', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Weekly Sales')
        ->assertSee('Monthly Sales')
        ->assertSee('Weekly Reservations')
        ->assertSee('Monthly Reservations');
});

test('the sales reports download as PDFs', function () {
    $order = Order::factory()->create(['status' => 'completed', 'created_at' => now()->subDay()]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 45.50]);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 3]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales-weekly'))
        ->assertOk()
        ->assertDownload('weekly-sales-report.pdf');

    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales-monthly'))
        ->assertOk()
        ->assertDownload('monthly-sales-report.pdf');
});

test('the reservation reports download as PDFs', function () {
    Reservation::factory()->create(['status' => 'completed', 'created_at' => now()->subDay()]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.reservations-weekly'))
        ->assertOk()
        ->assertDownload('weekly-reservations-report.pdf');

    $this->actingAs($this->admin)
        ->get(route('admin.reports.reservations-monthly'))
        ->assertOk()
        ->assertDownload('monthly-reservations-report.pdf');
});
