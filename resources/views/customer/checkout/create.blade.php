@extends('layouts.master')

@section('title', 'Checkout — Le Miting')

@section('content')
    <section class="py-12" aria-label="Checkout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-brand-800">Checkout</h1>

            <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
                @csrf

                <div class="lg:col-span-2">
                    <h2 class="text-lg font-semibold text-text">Your Details</h2>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-text">Name <span class="text-primary" aria-hidden="true">*</span></label>
                            <input
                                id="customer_name"
                                type="text"
                                name="customer_name"
                                value="{{ old('customer_name') }}"
                                required
                                autocomplete="name"
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('customer_name') ? 'border-red-400' : '' }}"
                            >
                            @error('customer_name')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_email" class="block text-sm font-medium text-text">Email <span class="text-primary" aria-hidden="true">*</span></label>
                            <input
                                id="customer_email"
                                type="email"
                                name="customer_email"
                                value="{{ old('customer_email') }}"
                                required
                                autocomplete="email"
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('customer_email') ? 'border-red-400' : '' }}"
                            >
                            @error('customer_email')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_phone" class="block text-sm font-medium text-text">Phone number <span class="text-primary" aria-hidden="true">*</span></label>
                            <input
                                id="customer_phone"
                                type="tel"
                                name="customer_phone"
                                value="{{ old('customer_phone') }}"
                                required
                                autocomplete="tel"
                                placeholder="01XXXXXXXXX"
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('customer_phone') ? 'border-red-400' : '' }}"
                            >
                            @error('customer_phone')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <span class="block text-sm font-medium text-text">Order type <span class="text-primary" aria-hidden="true">*</span></span>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach (['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway'] as $value => $label)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-brand-200 bg-white px-4 py-3 {{ old('order_type', 'dine_in') === $value ? 'border-primary bg-brand-50' : '' }}">
                                        <input
                                            type="radio"
                                            name="order_type"
                                            value="{{ $value }}"
                                            @checked(old('order_type', 'dine_in') === $value)
                                            class="h-4 w-4 text-primary focus:ring-primary"
                                        >
                                        <span class="text-sm font-medium text-text">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('order_type')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-text">Notes (optional)</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Allergies, portion requests, special instructions..."
                            >{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <aside aria-label="Order summary">
                    <div class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-text">Order Summary</h2>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($items as $item)
                                <li class="flex justify-between gap-3">
                                    <span class="text-text">{{ $item['quantity'] }} × {{ $item['name'] }}</span>
                                    <span class="shrink-0 font-medium text-text">RM {{ number_format($item['unit_price'] * $item['quantity'], 2) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4 flex justify-between border-t border-brand-100 pt-4">
                            <span class="font-semibold text-text">Total</span>
                            <span class="font-bold text-primary">RM {{ number_format($total, 2) }}</span>
                        </div>

                        <p class="mt-4 rounded-md bg-brand-50 px-3 py-2 text-xs text-text-muted">
                            You will pay with FIUU after reviewing your order. You are not charged yet.
                        </p>

                        <button
                            type="submit"
                            class="mt-6 inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            Continue to Payment
                        </button>
                        <a href="{{ route('cart.index') }}" class="mt-3 inline-flex w-full items-center justify-center text-sm font-semibold text-brand-700 hover:text-brand-800">
                            Back to cart
                        </a>
                    </div>
                </aside>
            </form>
        </div>
    </section>
@endsection