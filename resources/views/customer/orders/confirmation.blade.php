@extends('layouts.master')

@section('title', 'Order Confirmed — Le Miting')

@section('content')
    <section class="py-12" aria-label="Order confirmed">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-3xl" aria-hidden="true">✓</span>
                <h1 class="mt-4 text-3xl font-bold text-brand-800">Order Confirmed</h1>
                <p class="mt-2 text-text-muted">
                    A confirmation email with your receipt has been sent to <strong>{{ $order->customer_email }}</strong>.
                </p>
            </div>

            <div class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8">
                <h2 class="text-lg font-semibold text-text">Order Details</h2>

                <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Order number</dt>
                        <dd class="mt-1 text-lg font-bold text-primary">{{ $order->order_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Order type</dt>
                        <dd class="mt-1 text-text">{{ ucfirst($order->order_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Order date &amp; time</dt>
                        <dd class="mt-1 text-text">{{ $order->created_at->format('d M Y, g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-800">Confirmed</span>
                        </dd>
                    </div>
                </dl>

                <div class="mt-8">
                    <h3 class="font-semibold text-text">Items</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($order->orderItems as $item)
                            <li class="flex justify-between">
                                <span class="text-text">{{ $item->quantity }} × {{ $item->item_name }}</span>
                                <span class="font-medium text-text">RM {{ number_format($item->unit_price * $item->quantity, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 flex justify-between border-t border-brand-100 pt-3 font-semibold text-text">
                        <span>Total</span>
                        <span class="text-primary">RM {{ number_format($order->total, 2) }}</span>
                    </p>
                </div>

                <div class="mt-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                    Payment status: <strong>Paid</strong> (via FIUU)
                </div>

                @if ($order->notes)
                    <p class="mt-4 text-sm text-text-muted">
                        <span class="font-semibold text-text">Notes:</span> {{ $order->notes }}
                    </p>
                @endif
            </div>

            <div class="mt-8 text-center">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Back to Home
                </a>
            </div>
        </div>
    </section>
@endsection