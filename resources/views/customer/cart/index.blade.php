@extends('layouts.master')

@section('title', 'Your Cart — Le Miting')

@section('content')
    <section class="py-12" aria-label="Cart">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-brand-800">Your Cart</h1>

            @if ($items->count() > 0)
                <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <ul class="space-y-4">
                            @foreach ($items as $id => $item)
                                <li class="flex flex-col gap-4 rounded-xl border border-brand-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex-1">
                                        <h2 class="font-semibold text-text">{{ $item['name'] }}</h2>
                                        <p class="mt-1 text-sm text-text-muted">RM {{ number_format($item['unit_price'], 2) }} each</p>
                                    </div>

                                    <form method="POST" action="{{ route('cart.update', $id) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label for="quantity-{{ $id }}" class="sr-only">Quantity for {{ $item['name'] }}</label>
                                        <select
                                            id="quantity-{{ $id }}"
                                            name="quantity"
                                            class="rounded-md border border-brand-200 px-3 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                                            onchange="this.form.submit()"
                                        >
                                            @for ($i = 1; $i <= 10; $i++)
                                                <option value="{{ $i }}" @selected($item['quantity'] === $i)>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </form>

                                    <p class="font-semibold text-text">
                                        RM {{ number_format($item['unit_price'] * $item['quantity'], 2) }}
                                    </p>

                                    <form method="POST" action="{{ route('cart.remove', $id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-md px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-6">
                            <form method="POST" action="{{ route('cart.clear') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-semibold text-text-muted underline hover:text-red-600">
                                    Clear cart
                                </button>
                            </form>
                        </div>
                    </div>

                    <aside class="lg:col-span-1" aria-label="Order summary">
                        <div class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-semibold text-text">Order Summary</h2>
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-text-muted">Items</dt>
                                    <dd class="font-medium text-text">{{ $items->count() }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-brand-100 pt-3">
                                    <dt class="font-semibold text-text">Total</dt>
                                    <dd class="font-bold text-primary">RM {{ number_format($total, 2) }}</dd>
                                </div>
                            </dl>
                            <a
                                href="{{ route('checkout.create') }}"
                                class="mt-6 inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Proceed to Checkout
                            </a>
                            <a href="{{ route('menu.index') }}" class="mt-3 inline-flex w-full items-center justify-center text-sm font-semibold text-brand-700 hover:text-brand-800">
                                Continue browsing menu
                            </a>
                        </div>
                    </aside>
                </div>
            @else
                <div class="py-20 text-center">
                    <span class="text-6xl" aria-hidden="true">🛒</span>
                    <h2 class="mt-6 text-xl font-semibold text-text">Your order is empty.</h2>
                    <p class="mt-2 text-text-muted">Explore the menu to add dishes.</p>
                    <a
                        href="{{ route('menu.index') }}"
                        class="mt-8 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        Browse Menu
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection