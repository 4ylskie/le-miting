<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @return array{total_orders: int, completed_orders: int, cancelled_orders: int, total_revenue: string, popular_items: Collection<int, object>}
     */
    public function sales(CarbonInterface $from): array
    {
        $orders = Order::where('created_at', '>=', $from);

        $popularItems = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('order_items.item_name, SUM(order_items.quantity) as total_quantity')
            ->groupBy('order_items.item_name')
            ->orderByDesc('total_quantity')
            ->limit(8)
            ->get();

        return [
            'total_orders' => (clone $orders)->count(),
            'completed_orders' => (clone $orders)->where('status', 'completed')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'total_revenue' => Payment::whereNotNull('order_id')
                ->where('status', 'paid')
                ->where('paid_at', '>=', $from)
                ->sum('amount'),
            'popular_items' => $popularItems,
        ];
    }

    /**
     * @return array{total_reservations: int, completed_reservations: int, cancelled_reservations: int, guest_count: int, popular_times: Collection<int, object>}
     */
    public function reservations(CarbonInterface $from): array
    {
        $reservations = Reservation::where('created_at', '>=', $from);

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

    /**
     * @return array{total_reviews: int, average_food: float, average_service: float, average_experience: float, distributions: Collection<string, Collection<int, int>>}
     */
    public function feedback(CarbonInterface $from): array
    {
        $reviews = Feedback::where('created_at', '>=', $from)->get();

        $distributions = collect(['food_rating', 'service_rating', 'experience_rating'])->mapWithKeys(
            fn (string $dimension) => [
                $dimension => collect(range(5, 1))->mapWithKeys(
                    fn (int $star) => [$star => $reviews->where($dimension, $star)->count()],
                ),
            ],
        );

        return [
            'total_reviews' => $reviews->count(),
            'average_food' => (float) $reviews->avg('food_rating'),
            'average_service' => (float) $reviews->avg('service_rating'),
            'average_experience' => (float) $reviews->avg('experience_rating'),
            'distributions' => $distributions,
        ];
    }
}
