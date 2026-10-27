<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Collection;

class CartService
{
    /**
     * @return array{items: Collection<int, array{menu_item_id: int, name: string, unit_price: float|null, quantity: int, available: bool}>, total: float, has_unavailable_items: bool}
     */
    public function orderSummary(): array
    {
        $items = collect($this->items('order'));
        $menuItems = MenuItem::query()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->whereIn('id', $items->pluck('menu_item_id'))
            ->get()
            ->keyBy('id');

        $resolvedItems = $items->map(function (array $item) use ($menuItems): array {
            $menuItem = $menuItems->get($item['menu_item_id']);
            $available = $menuItem !== null && $menuItem->is_available;

            return [
                'menu_item_id' => $item['menu_item_id'],
                'name' => $menuItem?->name ?? 'Unavailable menu item',
                'unit_price' => $available ? (float) $menuItem->price : null,
                'quantity' => $item['quantity'],
                'available' => $available,
            ];
        });

        return [
            'items' => $resolvedItems,
            'total' => round($resolvedItems
                ->filter(fn (array $item): bool => $item['available'])
                ->sum(fn (array $item): float => $item['unit_price'] * $item['quantity']), 2),
            'has_unavailable_items' => $resolvedItems->contains(
                fn (array $item): bool => ! $item['available'],
            ),
        ];
    }

    /**
     * @return array<int, array{menu_item_id: int, quantity: int}>
     */
    public function orderItems(): array
    {
        return $this->items('order');
    }

    public function addOrderItem(MenuItem $menuItem, int $quantity): void
    {
        $items = $this->items('order');
        $item = $items[$menuItem->id] ?? [
            'menu_item_id' => $menuItem->id,
            'quantity' => 0,
        ];
        $item['quantity'] += $quantity;
        $items[$menuItem->id] = $item;

        $this->putItems('order', $items);
    }

    public function updateOrderItem(int $menuItemId, int $quantity): void
    {
        $items = $this->items('order');

        if (! isset($items[$menuItemId])) {
            return;
        }

        $items[$menuItemId]['quantity'] = $quantity;
        $this->putItems('order', $items);
    }

    public function removeOrderItem(int $menuItemId): void
    {
        $items = $this->items('order');
        unset($items[$menuItemId]);

        $this->putItems('order', $items);
    }

    public function clearOrder(): void
    {
        $this->clear('order');
    }

    public function clearReservation(): void
    {
        $this->clear('reservation');
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $items
     * @return array<int, array{menu_item_id: int, quantity: int}>
     */
    public function replaceReservationItems(array $items): array
    {
        $items = $this->normaliseItems($items);
        $this->putItems('reservation', $items);

        return array_values($items);
    }

    /**
     * @return array<int, array{menu_item_id: int, quantity: int}>
     */
    private function items(string $context): array
    {
        $cart = session()->get('cart', []);

        if ($context === 'order' && ! isset($cart['order']) && isset($cart['items'])) {
            $cart['order'] = ['items' => $this->normaliseItems($cart['items'])];
            unset($cart['items']);
            session()->put('cart', $cart);
        }

        return $this->normaliseItems($cart[$context]['items'] ?? []);
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $items
     */
    private function putItems(string $context, array $items): void
    {
        session()->put("cart.{$context}.items", $items);
    }

    private function clear(string $context): void
    {
        session()->forget("cart.{$context}");

        if (session()->get('cart') === []) {
            session()->forget('cart');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{menu_item_id: int, quantity: int}>
     */
    private function normaliseItems(array $items): array
    {
        $normalised = [];

        foreach ($items as $key => $item) {
            $menuItemId = (int) ($item['menu_item_id'] ?? $key);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($menuItemId < 1 || $quantity < 1) {
                continue;
            }

            $normalised[$menuItemId] = [
                'menu_item_id' => $menuItemId,
                'quantity' => $quantity,
            ];
        }

        return $normalised;
    }
}
