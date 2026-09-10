<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Mail\FeedbackInvitationMail;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class OrderController extends Controller
{
    /**
     * Allowable forward transitions for an order.
     *
     * @return array<string, array<int, string>>
     */
    private function transitions(): array
    {
        return [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
    }

    public function index(Request $request)
    {
        $status = $request->query('status');

        $orders = Order::with('payment')
            ->when($status && in_array($status, array_keys($this->transitions()), true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'currentStatus' => $status,
            'statuses' => array_keys($this->transitions()),
        ]);
    }

    public function show(Order $order)
    {
        $order->load('orderItems', 'payment', 'feedback');

        return view('admin.orders.show', [
            'order' => $order,
            'nextStatuses' => $this->transitions()[$order->status],
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $target = $request->validated('status');
        $allowed = $this->transitions()[$order->status];

        if (! in_array($target, $allowed, true)) {
            return back()->with('error', sprintf(
                'Cannot move an order from %s to %s.',
                $order->status,
                $target,
            ));
        }

        $order->update(['status' => $target]);

        if ($target === 'completed') {
            $this->sendFeedbackInvitation($order);
        }

        return back()->with('status', 'Order '.$order->order_number.' marked as '.$target.'.');
    }

    private function sendFeedbackInvitation(Order $order): void
    {
        $feedbackUrl = URL::temporarySignedRoute(
            'feedback.create',
            now()->addHours(24),
            ['token' => 'order-'.$order->id],
        );

        try {
            Mail::to($order->customer_email)
                ->send(new FeedbackInvitationMail($order->customer_name, 'order '.$order->order_number, $feedbackUrl));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
