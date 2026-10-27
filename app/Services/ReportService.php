<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\PaymentEligibility;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private PaymentEligibility $payments) {}

    /**
     * @return array{total_orders: int, completed_orders: int, cancelled_orders: int, total_revenue: string, popular_items: Collection<int, object>}
     */
    public function sales(CarbonInterface $from, CarbonInterface $until): array
    {
        $from = $from->toImmutable()->setTimezone('UTC');
        $until = $until->toImmutable()->setTimezone('UTC');
        $orders = Order::where('created_at', '>=', $from)->where('created_at', '<', $until);
        $eligibleOrderIds = [];

        (clone $orders)->select(['id', 'total'])
            ->whereIn('status', ['confirmed', 'preparing', 'ready', 'completed'])
            ->chunkById(500, function (EloquentCollection $candidates) use (&$eligibleOrderIds): void {
                $payments = Payment::whereIn('order_id', $candidates->modelKeys())
                    ->get(['id', 'order_id', 'reservation_id', 'status', 'paid_at', 'currency', 'amount'])
                    ->groupBy('order_id');

                foreach ($candidates as $order) {
                    if ($this->payments->isEligible($order, $payments->get($order->id, new EloquentCollection))) {
                        $eligibleOrderIds[] = $order->id;
                    }
                }
            });

        $popularItems = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $from)
            ->where('orders.created_at', '<', $until)
            ->whereIntegerInRaw('orders.id', $eligibleOrderIds)
            ->selectRaw('order_items.item_name, SUM(order_items.quantity) as total_quantity')
            ->groupBy('order_items.item_name')
            ->orderByDesc('total_quantity')
            ->orderBy('order_items.item_name')
            ->limit(8)
            ->get();

        return [
            'total_orders' => (clone $orders)->count(),
            'completed_orders' => (clone $orders)->where('status', 'completed')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'total_revenue' => Payment::whereNotNull('order_id')
                ->where('status', 'paid')
                ->where('paid_at', '>=', $from)
                ->where('paid_at', '<', $until)
                ->sum('amount'),
            'popular_items' => $popularItems,
        ];
    }

    /**
     * @return array{total_reservations: int, completed_reservations: int, cancelled_reservations: int, guest_count: int, popular_times: Collection<int, object>}
     */
    public function reservations(CarbonInterface $from, CarbonInterface $until): array
    {
        $from = $from->toImmutable()->setTimezone('UTC');
        $until = $until->toImmutable()->setTimezone('UTC');
        $reservations = Reservation::where('created_at', '>=', $from)->where('created_at', '<', $until);

        return [
            'total_reservations' => (clone $reservations)->count(),
            'completed_reservations' => (clone $reservations)->where('status', 'completed')->count(),
            'cancelled_reservations' => (clone $reservations)->where('status', 'cancelled')->count(),
            'guest_count' => (clone $reservations)->sum('guest_count'),
            'popular_times' => (clone $reservations)
                ->selectRaw('substr(reservation_time, 1, 5) as time_slot, count(*) as reservation_count')
                ->groupBy('time_slot')
                ->orderByDesc('reservation_count')
                ->limit(5)
                ->get(),
        ];
    }
}
