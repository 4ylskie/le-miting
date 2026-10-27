@extends('layouts.master')

@section('title', 'Your Cart — Le Miting')

@section('content')
    <section class="lm-container lm-section--compact" aria-labelledby="cart-heading">
        <header class="lm-stack mb-(--lm-space-xl)">
            <p class="lm-eyebrow">Your order</p>
            <h1 id="cart-heading" class="lm-h1">Your Cart</h1>
        </header>
        @if (session('error'))
            <p class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">{{ session('error') }}</p>
        @endif
        @if ($errors->any())
            <div id="cart-errors" class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif
        @if ($items->isNotEmpty())
            <div class="grid items-start gap-(--lm-space-xl) lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="min-w-0">
                    <ul>
                        @foreach ($items as $id => $item)
                            <li class="lm-divider grid gap-(--lm-space-md) py-(--lm-space-lg)">
                                <div class="flex flex-col gap-(--lm-space-xs) sm:flex-row sm:justify-between sm:gap-(--lm-space-md)">
                                    <div class="min-w-0">
                                        <h2 class="lm-h3 wrap-anywhere">{{ $item['name'] }}</h2>
                                        @if ($item['available'])
                                            <p class="lm-meta lm-muted mt-(--lm-space-xs)">RM {{ number_format($item['unit_price'], 2) }} each</p>
                                        @else
                                            <p class="lm-meta lm-muted mt-(--lm-space-xs)">This item is no longer available. Remove it before checkout.</p>
                                        @endif
                                    </div>
                                    @if ($item['available'])
                                        <p class="lm-body shrink-0 tabular-nums"><span class="sr-only">Line total: </span>RM {{ number_format($item['unit_price'] * $item['quantity'], 2) }}</p>
                                    @else
                                        <p class="lm-status self-start" data-state="unavailable">Unavailable</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-end gap-(--lm-space-md)">
                                    <form method="POST" action="{{ route('cart.update', $id) }}" class="lm-actions items-end">
                                        @csrf
                                        @method('PATCH')
                                        <div class="lm-field w-24">
                                            <label for="quantity-{{ $id }}" class="lm-label">Quantity<span class="sr-only"> for {{ $item['name'] }}</span></label>
                                            <select id="quantity-{{ $id }}" name="quantity" class="lm-control" @if ($errors->has('quantity')) aria-invalid="true" aria-describedby="cart-errors" @endif>
                                                @if ($item['quantity'] > 10)
                                                    <option value="{{ $item['quantity'] }}" selected disabled>{{ $item['quantity'] }} (current)</option>
                                                @endif
                                                @for ($i = 1; $i <= 10; $i++)
                                                    <option value="{{ $i }}" @selected($item['quantity'] === $i)>{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <noscript><button type="submit" class="lm-button lm-button--secondary">Update quantity<span class="sr-only"> for {{ $item['name'] }}</span></button></noscript>
                                    </form>
                                    <form method="POST" action="{{ route('cart.remove', $id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="lm-button lm-button--quiet">Remove<span class="sr-only"> {{ $item['name'] }}</span></button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('cart.clear') }}" class="lm-divider pt-(--lm-space-sm)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="lm-button lm-button--quiet">Clear cart</button>
                    </form>
                </div>
                <aside class="lm-divider lm-stack pt-(--lm-space-lg)" aria-labelledby="cart-summary-heading">
                    <h2 id="cart-summary-heading" class="lm-h2">Order Summary</h2>
                    <dl class="lm-meta">
                        <div class="flex justify-between gap-(--lm-space-md)">
                            <dt>Items</dt>
                            <dd>{{ $items->count() }}</dd>
                        </div>
                        <div class="lm-divider mt-(--lm-space-sm) flex justify-between gap-(--lm-space-md) pt-(--lm-space-sm)">
                            <dt class="font-semibold">Total</dt>
                            <dd class="font-semibold tabular-nums">RM {{ number_format($total, 2) }}</dd>
                        </div>
                    </dl>
                    @if ($hasUnavailableItems)
                        <p class="lm-alert" data-state="unavailable" role="status">Remove unavailable items before checking out.</p>
                    @else
                        <a href="{{ route('checkout.create') }}" class="lm-button">Proceed to Checkout</a>
                    @endif
                    <a href="{{ route('menu.index') }}" class="lm-button lm-button--quiet self-start">Continue browsing menu</a>
                </aside>
            </div>
        @else
            <div class="lm-empty lm-stack items-start">
                <h2 class="lm-h2">Your order is empty.</h2>
                <p class="lm-body lm-muted">Explore the menu to add dishes.</p>
                <a href="{{ route('menu.index') }}" class="lm-button">Browse Menu</a>
            </div>
        @endif
    </section>
@endsection
