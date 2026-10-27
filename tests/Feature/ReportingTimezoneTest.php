<?php

use App\Mail\OrderConfirmationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReceiptService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

test('dashboard uses Malaysian midnight for timestamp metrics and local service dates', function (string $utc, int $orders, int $reservations, float $revenue) {
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    $admin = User::factory()->create(['role' => 'admin']);
    foreach (['2026-10-09 15:59:59', '2026-10-09 16:00:00', '2026-10-10 15:59:59', '2026-10-10 16:00:00'] as $timestamp) {
        $order = Order::factory()->create(['created_at' => $timestamp]);
        Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 10, 'paid_at' => $timestamp]);
    }
    Reservation::factory()->count(2)->create(['reservation_date' => '2026-10-09']);
    $visit = Reservation::factory()->withFood()->create(['reservation_date' => '2026-10-10', 'created_at' => '2026-10-01 00:00:00']);
    Reservation::factory()->count(3)->create(['reservation_date' => '2026-10-11']);
    Payment::factory()->forReservation($visit)->paid()->create(['amount' => 30, 'paid_at' => '2026-10-09 16:00:00']);
    Payment::factory()->create(['order_id' => $order->id, 'status' => 'failed', 'amount' => 500, 'paid_at' => '2026-10-09 16:00:00']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('ordersToday', $orders)
        ->assertViewHas('reservationsToday', $reservations)
        ->assertViewHas('revenueToday', fn ($value) => (float) $value === $revenue);
})->with([
    'before midnight' => ['2026-10-09 15:59:59', 1, 2, 10.0],
    'midnight' => ['2026-10-09 16:00:00', 2, 1, 50.0],
    'last second' => ['2026-10-10 15:59:59', 2, 1, 50.0],
    'following midnight' => ['2026-10-10 16:00:00', 1, 3, 10.0],
]);

test('sales report routes preserve elapsed bounds and other metrics while filtering popular items', function (string $period, string $endText, string $startText, int $days) {
    $end = CarbonImmutable::parse($endText, 'UTC');
    $start = CarbonImmutable::parse($startText, 'UTC');
    $this->travelTo($end);
    $admin = User::factory()->create(['role' => 'admin']);
    foreach ([
        [$start->subSecond(), 'completed', 300, 8],
        [$start, 'completed', 10, 2],
        [$end->subSecond(), 'cancelled', 20, 3],
        [$end, 'completed', 400, 9],
        [$end->addSecond(), 'completed', 500, 10],
    ] as [$timestamp, $status, $amount, $quantity]) {
        $order = Order::factory()->create(['created_at' => $timestamp, 'status' => $status, 'total' => $amount]);
        OrderItem::factory()->create(['order_id' => $order->id, 'item_name' => 'Window Dish', 'quantity' => $quantity]);
        Payment::factory()->paid()->create(['order_id' => $order->id, 'paid_at' => $timestamp, 'amount' => $amount]);
    }
    $oldOrder = Order::factory()->create(['created_at' => $start->subDay()]);
    Payment::factory()->paid()->create(['order_id' => $oldOrder->id, 'paid_at' => $start, 'amount' => 30]);
    $pendingOrder = Order::factory()->create(['created_at' => $start, 'status' => 'pending']);
    OrderItem::factory()->create(['order_id' => $pendingOrder->id, 'item_name' => 'Window Dish', 'quantity' => 4]);
    Payment::factory()->create(['order_id' => $pendingOrder->id, 'status' => 'failed', 'paid_at' => $start, 'amount' => 600]);
    $visit = Reservation::factory()->create();
    Payment::factory()->forReservation($visit)->paid()->create(['paid_at' => $start, 'amount' => 700]);
    $report = null;
    View::composer('admin.reports.pdfs.sales', function ($view) use (&$report): void {
        $report = $view->getData();
    });

    $this->actingAs($admin)->get(route('admin.reports.sales-'.$period))->assertOk()->assertDownload($period.'-sales-report.pdf');

    expect($report['from'])->toBeInstanceOf(CarbonImmutable::class);
    expect($report['from']->toDateTimeString())->toBe($startText);
    expect($report['generatedAt']->toDateTimeString())->toBe($endText);
    expect($report['from']->diffInSeconds($report['generatedAt']))->toBe((float) ($days * 86400));
    expect($report['data']['total_orders'])->toBe(3);
    expect($report['data']['completed_orders'])->toBe(1);
    expect($report['data']['cancelled_orders'])->toBe(1);
    expect((float) $report['data']['total_revenue'])->toBe(60.0);
    expect($report['data']['popular_items'])->toHaveCount(1);
    expect((int) $report['data']['popular_items']->first()->total_quantity)->toBe(2);
    $this->view('admin.reports.pdfs.sales', $report)
        ->assertSee($start->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, H:i:s').' MYT (inclusive)')
        ->assertSee($end->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, H:i:s').' MYT (exclusive)');
})->with([
    'weekly month end' => ['weekly', '2026-10-31 16:15:30', '2026-10-24 16:15:30', 7],
    'monthly month end' => ['monthly', '2026-10-31 16:15:30', '2026-10-01 16:15:30', 30],
    'weekly year end' => ['weekly', '2026-12-31 16:15:30', '2026-12-24 16:15:30', 7],
    'monthly year end' => ['monthly', '2026-12-31 16:15:30', '2026-12-01 16:15:30', 30],
]);

test('reservation report routes stay creation based within captured elapsed bounds', function (string $period, string $endText, string $startText, int $days) {
    $end = CarbonImmutable::parse($endText, 'UTC');
    $start = CarbonImmutable::parse($startText, 'UTC');
    $this->travelTo($end);
    $admin = User::factory()->create(['role' => 'admin']);
    foreach ([
        [$start->subSecond(), 'completed', 7, '11:00:00', '2026-10-15'],
        [$start, 'confirmed', 2, '11:00:00', '2050-01-01'],
        [$end->subSecond(), 'cancelled', 3, '11:00:00', '2000-01-01'],
        [$start->addSecond(), 'completed', 4, '12:00:00', '2026-10-15'],
        [$end, 'completed', 8, '11:00:00', '2026-10-15'],
        [$end->addSecond(), 'completed', 9, '11:00:00', '2026-10-15'],
    ] as [$timestamp, $status, $guests, $time, $date]) {
        Reservation::factory()->create([
            'created_at' => $timestamp, 'status' => $status, 'guest_count' => $guests,
            'reservation_time' => $time, 'reservation_date' => $date,
        ]);
    }
    $report = null;
    View::composer('admin.reports.pdfs.reservations', function ($view) use (&$report): void {
        $report = $view->getData();
    });

    $this->actingAs($admin)->get(route('admin.reports.reservations-'.$period))->assertOk()->assertDownload($period.'-reservations-report.pdf');

    expect($report['from']->toDateTimeString())->toBe($startText);
    expect($report['generatedAt']->toDateTimeString())->toBe($endText);
    expect($report['from']->diffInSeconds($report['generatedAt']))->toBe((float) ($days * 86400));
    expect($report['data']['total_reservations'])->toBe(3);
    expect($report['data']['completed_reservations'])->toBe(1);
    expect($report['data']['cancelled_reservations'])->toBe(1);
    expect((int) $report['data']['guest_count'])->toBe(9);
    expect($report['data']['popular_times'])->toHaveCount(2);
    expect($report['data']['popular_times']->first()->time_slot)->toBe('11:00');
    expect((int) $report['data']['popular_times']->first()->reservation_count)->toBe(2);
    $this->view('admin.reports.pdfs.reservations', $report)
        ->assertSee($start->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, H:i:s').' MYT (inclusive)')
        ->assertSee($end->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, H:i:s').' MYT (exclusive)');
})->with([
    'weekly month end' => ['weekly', '2026-10-31 16:15:30', '2026-10-24 16:15:30', 7],
    'monthly month end' => ['monthly', '2026-10-31 16:15:30', '2026-10-01 16:15:30', 30],
    'weekly year end' => ['weekly', '2026-12-31 16:15:30', '2026-12-24 16:15:30', 7],
    'monthly year end' => ['monthly', '2026-12-31 16:15:30', '2026-12-01 16:15:30', 30],
]);

test('report generation reuses its captured instant even when the clock advances between queries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 16:00:00', 'UTC'));
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create(['created_at' => '2026-10-10 16:00:00']);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'paid_at' => '2026-10-10 16:00:00', 'amount' => 90]);
    $advanced = false;
    DB::listen(function ($query) use (&$advanced): void {
        if (! $advanced && str_contains($query->sql, 'total_quantity')) {
            $advanced = true;
            $this->travelTo(CarbonImmutable::parse('2026-10-10 16:00:02', 'UTC'));
        }
    });
    $report = null;
    View::composer('admin.reports.pdfs.sales', function ($view) use (&$report): void {
        $report = $view->getData();
    });

    $this->actingAs($admin)->get(route('admin.reports.sales-weekly'))->assertOk();

    expect($advanced)->toBeTrue();
    expect($report['generatedAt']->toDateTimeString())->toBe('2026-10-10 16:00:00');
    expect($report['data']['total_orders'])->toBe(0);
    expect((float) $report['data']['total_revenue'])->toBe(0.0);
});

test('report service converts explicit local bounds to UTC without mutating them', function () {
    $from = CarbonImmutable::parse('2026-10-10 00:00:00', 'Asia/Kuala_Lumpur');
    $until = CarbonImmutable::parse('2026-10-11 00:00:00', 'Asia/Kuala_Lumpur');
    Order::factory()->create(['created_at' => '2026-10-09 16:00:00']);
    Order::factory()->create(['created_at' => '2026-10-10 16:00:00']);

    expect(app(ReportService::class)->sales($from, $until)['total_orders'])->toBe(1);
    expect($from->format('Y-m-d H:i:s e'))->toBe('2026-10-10 00:00:00 Asia/Kuala_Lumpur');
});

test('order timestamps display consistently in Malaysia without changing stored values', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 00:00:00', 'UTC'));
    $order = Order::factory()->create([
        'status' => 'confirmed', 'order_type' => 'takeaway', 'created_at' => '2026-10-09 16:05:00', 'total' => 20,
    ]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 20, 'paid_at' => '2026-10-09 16:06:00']);
    $before = DB::table('orders')->find($order->id);
    $paymentBefore = DB::table('payments')->where('order_id', $order->id)->first();
    $expected = '10 Oct 2026, 12:05 AM MYT';
    $admin = User::factory()->create(['role' => 'admin']);

    $this->get(URL::temporarySignedRoute('orders.confirmation', now('UTC')->addHours(24), ['order' => $order]))->assertSee($expected);
    $this->actingAs($admin)->get(route('admin.orders.index'))->assertSee($expected);
    $this->get(route('admin.orders.show', $order))->assertSee($expected);
    $mail = new OrderConfirmationMail($order);
    $mail->assertSeeInHtml($expected);
    $mail->assertSeeInHtml('12:30 AM MYT');
    $this->view('pdfs.receipt', ['order' => $order])->assertSee($expected);
    expect(app(ReceiptService::class)->generateReceipt($order)->output())->toStartWith('%PDF');
    expect(DB::table('orders')->find($order->id))->toEqual($before);
    expect(DB::table('payments')->where('order_id', $order->id)->first())->toEqual($paymentBefore);
});

test('reservation appointments remain local in pages email and receipt', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:00', 'UTC'));
    $reservation = Reservation::factory()->withFood()->confirmed()->create([
        'reservation_date' => '2026-10-10', 'reservation_time' => '11:00:00',
    ]);
    Payment::factory()->forReservation($reservation)->paid()->create();
    $before = DB::table('reservations')->find($reservation->id);

    $this->get(URL::temporarySignedRoute('reservation.confirmation', now('UTC')->addHours(24), ['reservation' => $reservation]))
        ->assertSee('11:00 AM')->assertSee('10 October 2026');
    (new ReservationConfirmationMail($reservation))->assertSeeInHtml('11:00 AM');
    $this->view('pdfs.reservation-receipt', ['reservation' => $reservation->load('table', 'reservationFoods', 'payment')])
        ->assertSee('11:00 AM')->assertSee('10 Oct 2026');
    expect(DB::table('reservations')->find($reservation->id))->toEqual($before);
});
