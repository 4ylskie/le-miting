<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentProcessor $payments,
    ) {}

    public function create(Request $request)
    {
        $cart = session()->get('cart', ['items' => []]);

        $items = collect($cart['items'] ?? [])->values();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart is empty. Add some dishes before checking out.');
        }

        $total = $items->reduce(fn ($carry, $item) => $carry + ($item['unit_price'] * $item['quantity']), 0.0);

        return view('customer.checkout.create', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->orders->createFromCart($request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'fiuu',
            'provider_reference' => 'PAY-'.date('Y').'-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => 'MYR',
        ]);

        return redirect()->away($this->payments->prepare($payment)['url']);
    }
}
