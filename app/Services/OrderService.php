<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createFromCart(array $data): Order
    {
        $cart = session()->get('cart', ['items' => []]);

        if (empty($cart['items'])) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $items = collect($cart['items'])->values();

        $unavailable = $items->filter(fn ($item) => ! MenuItem::where('id', $item['menu_item_id'])
            ->where('is_available', true)
            ->exists());

        if ($unavailable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Some items in your cart are no longer available. Please review your cart.',
            ]);
        }

        return DB::transaction(function () use ($data, $items) {
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
                $menuItem = MenuItem::where('is_available', true)->findOrFail($cartItem['menu_item_id']);

                $order->orderItems()->create([
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $cartItem['quantity'],
                ]);

                $total += $menuItem->price * $cartItem['quantity'];
            }

            $order->update(['total' => round($total, 2)]);

            session()->forget('cart');

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
