@extends('layouts.master')

@section('title', $payment?->status === 'paid' ? 'Payment Already Verified — Le Miting' : 'Payment Not Completed — Le Miting')

@section('content')
    <section class="lm-container lm-section" aria-labelledby="payment-heading">
        <div class="lm-stack max-w-2xl">
            <x-stripe-payment-info />
            <p class="lm-status self-start" data-state="{{ $payment?->status === 'paid' ? 'success' : 'failed' }}">{{ $payment?->status === 'paid' ? 'Paid' : 'Payment not completed' }}</p>
            <h1 id="payment-heading" class="lm-h1">{{ $payment?->status === 'paid' ? 'Payment already verified' : 'Payment not completed' }}</h1>
            <p class="lm-body lm-muted">
                @if ($payment?->status === 'paid')
                    This payment is already recorded as paid. Use the confirmation link from your email to view your record.
                @elseif ($canRetry)
                    You can retry payment for the same pending transaction.
                @elseif ($payment?->reservation_id !== null)
                    This reservation payment cannot be retried. Return to reservation options to check availability again.
                @else
                    Payment has not been confirmed. You can return to your cart or browse the menu.
                @endif
            </p>
            @if ($errors->any())
                <div class="lm-alert" data-state="error" role="alert">
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif
            <div class="lm-actions lm-divider pt-(--lm-space-lg)">
                @if ($payment?->status === 'paid')
                    <a href="{{ route('home') }}" class="lm-button">Back to Home</a>
                @elseif ($canRetry)
                    <form method="POST" action="{{ route('payment.retry', $payment) }}">
                        @csrf
                        <button type="submit" class="lm-button">Retry Payment</button>
                    </form>
                @elseif ($payment?->provider === 'stripe' && $payment?->status === 'pending')
                    <a href="{{ route('payment.create', $payment) }}" class="lm-button">Return to Stripe Checkout</a>
                @elseif ($payment?->reservation_id !== null)
                    <a href="{{ route('reservation.create') }}" class="lm-button">Reservation options</a>
                @else
                    <a href="{{ route('cart.index') }}" class="lm-button">Return to Cart</a>
                @endif
                <a href="{{ route('menu.index') }}" class="lm-button lm-button--quiet">Browse Menu</a>
            </div>
        </div>
    </section>
@endsection
