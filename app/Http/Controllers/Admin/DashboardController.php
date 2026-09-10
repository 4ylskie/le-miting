<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard.index', [
            'ordersToday' => Order::whereDate('created_at', today())->count(),
            'reservationsToday' => Reservation::whereDate('reservation_date', today())->count(),
            'revenueToday' => Payment::where('status', 'paid')
                ->whereDate('paid_at', today())
                ->sum('amount'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'pendingReservations' => Reservation::where('status', 'pending')->count(),
            'availableTables' => Table::where('status', 'available')->count(),
            'recentFeedback' => Feedback::with('order', 'reservation')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
