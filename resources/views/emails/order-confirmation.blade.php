@extends('emails.layout')
@inject('businessTime', 'App\Services\BusinessTime')

@section('heading')
    Order Confirmed, {{ $order->customer_name }}!
@endsection

@section('content')
    <p class="muted">Thank you for your order at Le Miting. Your payment has been verified and your order is now being prepared.</p>

    <dl>
        <div class="row"><dt>Order number</dt><dd>{{ $order->order_number }}</dd></div>
        <div class="row"><dt>Order type</dt><dd>{{ ucfirst($order->order_type) }}</dd></div>
        <div class="row"><dt>Order date &amp; time</dt><dd>{{ $businessTime->local($order->created_at)->format('d M Y, g:i A') }} MYT</dd></div>
        <div class="row"><dt>Estimated ready</dt><dd>
            @if ($order->order_type === 'dine-in')
                Served at your table
            @else
                {{ $businessTime->local($order->created_at)->addMinutes(25)->format('g:i A') }} MYT
            @endif
        </dd></div>
    </dl>

    <h3 class="email-heading">Items</h3>
    <dl>
        @foreach ($order->orderItems as $item)
            <div class="row">
                <dt>{{ $item->quantity }} × {{ $item->item_name }}</dt>
                <dd>RM {{ number_format($item->unit_price * $item->quantity, 2) }}</dd>
            </div>
        @endforeach
        <div class="row total">
            <dt>Total paid</dt>
            <dd>RM {{ number_format($order->total, 2) }}</dd>
        </div>
    </dl>

    @if ($order->notes)
        <p class="muted"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <div>
        <a class="btn" href="{{ $receiptUrl }}">View &amp; download receipt (PDF)</a>
    </div>

    <p class="muted mt-12">Payment reference: {{ $order->payment->provider_reference }}</p>
@endsection
