<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentProcessor $payments,
        private CartService $carts,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $summary = $this->carts->orderSummary();
        $items = $summary['items'];

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart is empty. Add some dishes before checking out.');
        }

        if ($summary['has_unavailable_items']) {
            return redirect()->route('cart.index')
                ->with('error', 'Remove unavailable items from your cart before checking out.');
        }

        return view('customer.checkout.create', [
            'items' => $items,
            'total' => $summary['total'],
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->orders->createFromCart($request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => config('payment.driver') === 'stripe' ? 'stripe' : 'fiuu',
            'provider_reference' => 'PAY-'.date('Y').'-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => 'MYR',
        ]);

        return redirect()->away($this->payments->prepare($payment)['url']);
    }
}
