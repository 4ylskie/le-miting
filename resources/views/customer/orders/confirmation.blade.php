@extends('layouts.master')
@inject('businessTime', 'App\Services\BusinessTime')

@section('title', 'Order Confirmed — Le Miting')

@section('content')
    <section class="lm-container lm-section--compact" aria-labelledby="order-heading">
        <div class="mx-auto max-w-3xl">
            <header class="lm-stack mb-(--lm-space-xl)">
                <p class="lm-status self-start" data-state="success" role="status">{{ ucfirst($order->status) }}</p>
                <h1 id="order-heading" class="lm-h1">Order Confirmed</h1>
            </header>
            <section class="lm-divider pt-(--lm-space-lg)" aria-labelledby="order-details-heading">
                <h2 id="order-details-heading" class="lm-h2">Order Details</h2>
                <dl class="mt-(--lm-space-lg) grid gap-(--lm-space-md) sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="lm-label">Order number</dt>
                        <dd class="lm-h3 mt-(--lm-space-xs) wrap-anywhere">{{ $order->order_number }}</dd>
                    </div>
                    <div>
                        <dt class="lm-label">Order type</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ ucfirst($order->order_type) }}</dd>
                    </div>
                    <div>
                        <dt class="lm-label">Order date &amp; time</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ $businessTime->local($order->created_at)->format('d M Y, g:i A') }} MYT</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="lm-label">Email</dt>
                        <dd class="lm-body mt-(--lm-space-xs) wrap-anywhere">{{ $order->customer_email }}</dd>
                    </div>
                </dl>
            </section>
            <section class="lm-divider mt-(--lm-space-xl) pt-(--lm-space-lg)" aria-labelledby="order-items-heading">
                <h2 id="order-items-heading" class="lm-h2">Your order</h2>
                <ul class="mt-(--lm-space-sm) divide-y divide-line">
                    @foreach ($order->orderItems as $item)
                        <x-transaction-item :name="$item->item_name" :quantity="$item->quantity" :unit-price="$item->unit_price" />
                    @endforeach
                </ul>
                <dl class="lm-divider pt-(--lm-space-md)">
                    <div class="lm-body flex justify-between gap-(--lm-space-md) font-semibold">
                        <dt>Total</dt>
                        <dd class="tabular-nums">RM {{ number_format($order->total, 2) }}</dd>
                    </div>
                </dl>
                @if ($order->notes)
                    <p class="lm-meta lm-muted mt-(--lm-space-md) whitespace-pre-line wrap-anywhere"><span class="font-semibold">Notes:</span> {{ $order->notes }}</p>
                @endif
            </section>
            <section class="lm-divider lm-stack mt-(--lm-space-xl) pt-(--lm-space-lg)" aria-labelledby="order-payment-heading">
                <h2 id="order-payment-heading" class="lm-h2">Payment</h2>
                @if ($order->payment)
                    <p class="lm-meta">Payment status: <strong>{{ ucfirst($order->payment->status) }}</strong></p>
                    <dl>
                        <dt class="lm-label">Payment reference</dt>
                        <dd class="lm-meta mt-(--lm-space-xs) wrap-anywhere">{{ $order->payment->provider_reference }}</dd>
                    </dl>
                    @if ($order->payment->status === 'paid')
                        <div class="lm-stack items-start">
                            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('orders.receipt', ['order' => $order->id], now()->addHours(24)) }}" class="lm-button">Download receipt (PDF)</a>
                            <p class="lm-meta lm-muted">This receipt link expires in 24 hours.</p>
                        </div>
                    @endif
                @else
                    <p class="lm-meta lm-muted">Payment information is not available.</p>
                @endif
                <a href="{{ route('home') }}" class="lm-button lm-button--quiet self-start">Back to Home</a>
            </section>
        </div>
    </section>
@endsection
