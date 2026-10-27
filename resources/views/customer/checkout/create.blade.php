@extends('layouts.master')

@section('title', 'Checkout — Le Miting')

@section('content')
    <section class="lm-container lm-section--compact" aria-labelledby="checkout-heading">
        <header class="lm-stack mb-(--lm-space-xl)">
            <p class="lm-eyebrow">Your order</p>
            <h1 id="checkout-heading" class="lm-h1">Checkout</h1>
            <p class="lm-body lm-muted">Review your order and enter your details to continue.</p>
        </header>
        @if ($errors->any())
            <div class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('checkout.store') }}" class="grid items-start gap-(--lm-space-xl) lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            @csrf
            <div class="lm-stack min-w-0">
                <section class="lm-divider lm-stack pt-(--lm-space-lg)" aria-labelledby="details-heading">
                    <h2 id="details-heading" class="lm-h2">Your Details</h2>
                    <p class="lm-meta lm-muted">Name, email, phone number, and order type are required.</p>
                    @foreach ([
                        'customer_name' => ['label' => 'Name', 'type' => 'text', 'autocomplete' => 'name'],
                        'customer_email' => ['label' => 'Email', 'type' => 'email', 'autocomplete' => 'email'],
                        'customer_phone' => ['label' => 'Phone number', 'type' => 'tel', 'autocomplete' => 'tel'],
                    ] as $field => $input)
                        <div class="lm-field">
                            <label for="{{ $field }}" class="lm-label">{{ $input['label'] }}</label>
                            <input id="{{ $field }}" type="{{ $input['type'] }}" name="{{ $field }}" value="{{ old($field) }}" required autocomplete="{{ $input['autocomplete'] }}" class="lm-control" @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif>
                            @error($field)
                                <p id="{{ $field }}-error" class="lm-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </section>
                <fieldset class="lm-divider pt-(--lm-space-lg)">
                    <legend class="lm-h3 pr-(--lm-space-sm)">Order type</legend>
                    <div class="mt-(--lm-space-xs) flex flex-wrap gap-x-(--lm-space-xl) gap-y-(--lm-space-xs)">
                        @foreach (['dine-in' => 'Dine-in', 'takeaway' => 'Takeaway'] as $value => $label)
                            <label for="order-type-{{ $value }}" class="lm-choice">
                                <input id="order-type-{{ $value }}" type="radio" name="order_type" value="{{ $value }}" @checked(old('order_type', 'dine-in') === $value) required class="lm-radio" @if ($errors->has('order_type')) aria-invalid="true" aria-describedby="order-type-error" @endif>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('order_type')
                        <p id="order-type-error" class="lm-error" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>
                <div class="lm-field">
                    <label for="notes" class="lm-label">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="3" class="lm-control" @if ($errors->has('notes')) aria-invalid="true" aria-describedby="notes-error" @endif>{{ old('notes') }}</textarea>
                    @error('notes')
                        <p id="notes-error" class="lm-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <aside class="lm-divider lm-stack min-w-0 pt-(--lm-space-lg)" aria-labelledby="checkout-summary-heading">
                <h2 id="checkout-summary-heading" class="lm-h2">Order Summary</h2>
                <ul class="divide-y divide-line">
                    @foreach ($items as $item)
                        <x-transaction-item :name="$item['name']" :quantity="$item['quantity']" :unit-price="$item['unit_price']" />
                    @endforeach
                </ul>
                <dl class="lm-divider pt-(--lm-space-sm)">
                    <div class="lm-body flex justify-between gap-(--lm-space-md) font-semibold">
                        <dt>Total</dt>
                        <dd class="tabular-nums">RM {{ number_format($total, 2) }}</dd>
                    </div>
                </dl>
                <p class="lm-meta lm-muted">Continue to the payment page to complete your order.</p>
                <button type="submit" class="lm-button w-full">Proceed to Payment</button>
                <a href="{{ route('cart.index') }}" class="lm-button lm-button--quiet self-start">Back to cart</a>
            </aside>
        </form>
    </section>
@endsection
