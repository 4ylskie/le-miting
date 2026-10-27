<?php

namespace App\Services;

use App\Events\OrderPaymentVerified;
use App\Mail\GoogleReviewInvitationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Payments\PaymentEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private CartService $carts, private PaymentEligibility $payments) {}

    /** @return array<string, array<int, string>> */
    public function transitions(): array
    {
        return [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['completed', 'cancelled'],
            'completed' => [], 'cancelled' => [],
        ];
    }

    /** Return a user-facing rejection, or null for the winning transition. */
    public function transition(int $orderId, string $target, string $expectedStatus): ?string
    {
        return DB::transaction(function () use ($orderId, $target, $expectedStatus): ?string {
            $original = Order::findOrFail($orderId);
            $payments = $target !== 'cancelled' ? $this->payments->lockFor($original) : null;
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== $expectedStatus) {
                return 'This order has changed. Refresh the page before trying again.';
            }
            if (! in_array($target, $this->transitions()[$order->status] ?? [], true)) {
                return 'Cannot move an order from '.$order->status.' to '.$target.'.';
            }
            if ($target !== 'cancelled' && ! $this->payments->isEligible($order, $payments)) {
                return PaymentEligibility::ERROR;
            }

            $order->update(['status' => $target]);
            if ($target === 'confirmed') {
                DB::afterCommit(fn () => OrderPaymentVerified::dispatch($order));
            } elseif ($target === 'completed') {
                DB::afterCommit(function () use ($order): void {
                    try {
                        Mail::to($order->customer_email)->send(new GoogleReviewInvitationMail(
                            $order->customer_name, 'order '.$order->order_number, config('restaurant.google_review_url'),
                        ));
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }

            return null;
        });
    }

    public function createFromCart(array $data): Order
    {
        $items = collect($this->carts->orderItems())->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        return DB::transaction(function () use ($data, $items) {
            $menuItems = MenuItem::query()
                ->where('is_available', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->whereIn('id', $items->pluck('menu_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($menuItems->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'cart' => 'Some items in your cart are no longer available. Please remove them before checking out.',
                ]);
            }

            $total = 0;

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'order_type' => $data['order_type'],
                'status' => 'pending',
                'total' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $cartItem) {
                $menuItem = $menuItems->get($cartItem['menu_item_id']);

                $order->orderItems()->create([
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $cartItem['quantity'],
                ]);

                $total += $menuItem->price * $cartItem['quantity'];
            }

            $order->update(['total' => round($total, 2)]);

            return $order->refresh();
        });
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Y').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
