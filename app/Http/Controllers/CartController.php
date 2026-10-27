<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\MenuItem;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(): View
    {
        $summary = $this->carts->orderSummary();

        return view('customer.cart.index', [
            'items' => $summary['items'],
            'total' => $summary['total'],
            'hasUnavailableItems' => $summary['has_unavailable_items'],
        ]);
    }

    public function add(AddCartItemRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $item = MenuItem::query()
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->find($validated['menu_item_id']);

        if (! $item) {
            throw ValidationException::withMessages([
                'menu_item_id' => 'This menu item is no longer available.',
            ]);
        }

        $this->carts->addOrderItem($item, $validated['quantity']);

        return back()->with('success', $item->name.' added to cart.');
    }

    public function update(UpdateCartItemRequest $request, int $id): RedirectResponse
    {
        $this->carts->updateOrderItem($id, $request->validated('quantity'));

        return back();
    }

    public function remove(Request $request, int $id): RedirectResponse
    {
        $this->carts->removeOrderItem($id);

        return back();
    }

    public function clear(): RedirectResponse
    {
        $this->carts->clearOrder();

        return redirect()->route('cart.index');
    }

    public static function count(): int
    {
        return collect(session()->get('cart.order.items', session()->get('cart.items', [])))->sum('quantity');
    }
}
