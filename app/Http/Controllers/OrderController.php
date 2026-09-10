<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OrderController extends Controller
{
    public function __construct(private ReceiptService $receipts) {}

    public function confirmation(Order $order)
    {
        abort_unless($order->status === 'confirmed', 404);

        $order->load('orderItems', 'payment');

        return view('customer.orders.confirmation', [
            'order' => $order,
        ]);
    }

    public function receipt(Request $request, Order $order)
    {
        if (! URL::hasValidSignature($request)) {
            return response()->view('errors.invalid-link', [], 403);
        }

        abort_unless($order->payment && $order->payment->status === 'paid', 404);

        return $this->receipts->generateReceipt($order)
            ->download('receipt-'.$order->order_number.'.pdf');
    }
}
