<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /**
     * Allowable forward transitions for an order.
     *
     * @return array<string, array<int, string>>
     */
    private function transitions(): array
    {
        return $this->orders->transitions();
    }

    public function index(Request $request)
    {
        $statuses = array_keys($this->transitions());
        $status = $request->query('status');
        $status = in_array($status, $statuses, true) ? $status : null;

        $orders = Order::with('payment')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->appends(array_merge($request->query(), ['status' => $status]));

        return view('admin.orders.index', [
            'orders' => $orders,
            'currentStatus' => $status,
            'statuses' => $statuses,
        ]);
    }

    public function show(Order $order)
    {
        $order->load('orderItems', 'payment');

        return view('admin.orders.show', [
            'order' => $order,
            'nextStatuses' => $this->transitions()[$order->status],
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $target = $request->validated('status');
        $error = $this->orders->transition($order->id, $target, $request->validated('expected_status'));
        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('status', 'Order '.$order->order_number.' marked as '.$target.'.');
    }
}
