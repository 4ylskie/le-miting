<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', ['items' => []]);

        $items = collect($cart['items'] ?? [])->values();

        $total = $items->reduce(function ($carry, $item) {
            return $carry + ($item['unit_price'] * $item['quantity']);
        }, 0.0);

        return view('customer.cart.index', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $item = MenuItem::where('is_available', true)->findOrFail($validated['menu_item_id']);

        $cart = session()->get('cart', ['items' => []]);

        if (isset($cart['items'][$item->id])) {
            $cart['items'][$item->id]['quantity'] += $validated['quantity'];
        } else {
            $cart['items'][$item->id] = [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'unit_price' => (float) $item->price,
                'quantity' => $validated['quantity'],
            ];
        }

        session()->put('cart', $cart);

        return back()->with('success', $item->name.' added to cart.');
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $cart = session()->get('cart', ['items' => []]);

        if (isset($cart['items'][$id])) {
            $cart['items'][$id]['quantity'] = $validated['quantity'];
            session()->put('cart', $cart);
        }

        return back();
    }

    public function remove(Request $request, int $id)
    {
        $cart = session()->get('cart', ['items' => []]);

        unset($cart['items'][$id]);
        session()->put('cart', $cart);

        return back();
    }

    public function clear()
    {
        session()->forget('cart');

        return redirect()->route('cart.index');
    }

    public static function count(): int
    {
        $cart = session()->get('cart', ['items' => []]);

        return collect($cart['items'] ?? [])->sum('quantity');
    }
}
