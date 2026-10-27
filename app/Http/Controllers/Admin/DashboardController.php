<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\BusinessTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(BusinessTime $businessTime): View
    {
        return view('admin.dashboard.index', $this->summaryMetrics($businessTime));
    }

    public function summary(BusinessTime $businessTime): JsonResponse
    {
        return response()->json($this->summaryMetrics($businessTime))
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array{
     *     ordersToday: int,
     *     reservationsToday: int,
     *     revenueToday: float,
     *     pendingOrders: int,
     *     pendingReservations: int,
     *     availableTables: int
     * }
     */
    private function summaryMetrics(BusinessTime $businessTime): array
    {
        $today = $businessTime->today();
        [$start, $end] = $businessTime->utcDayBounds($today);

        return [
            'ordersToday' => Order::where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
            'reservationsToday' => Reservation::whereDate('reservation_date', $today)->count(),
            'revenueToday' => (float) Payment::where('status', 'paid')
                ->where('paid_at', '>=', $start)
                ->where('paid_at', '<', $end)
                ->sum('amount'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'pendingReservations' => Reservation::where('status', 'pending')->count(),
            'availableTables' => Table::where('status', 'available')->count(),
        ];
    }
}
