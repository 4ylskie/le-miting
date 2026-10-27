@extends('layouts.master')

@section('title', ($reservationUnconfirmed ?? false) ? 'Reservation Needs Review — Le Miting' : 'Verifying Payment — Le Miting')

@section('content')
    <section class="lm-container lm-section" aria-labelledby="payment-heading">
        <div class="lm-stack max-w-2xl">
            @if ($reservationUnconfirmed ?? false)
                <p class="lm-status self-start" data-state="pending" role="status">Manual review required</p>
                <h1 id="payment-heading" class="lm-h1">Payment received, reservation not confirmed</h1>
                <p class="lm-body lm-muted">Your payment was verified, but your table could not be secured. Please contact the restaurant with your payment reference for manual resolution. Do not make another payment for this reservation.</p>
            @else
                <p class="lm-status self-start" data-state="pending" role="status">Awaiting verification</p>
                <h1 id="payment-heading" class="lm-h1">Verifying your payment</h1>
                <p class="lm-body lm-muted">Your payment is awaiting verification. Returning to this page does not mean your payment succeeded.</p>
            @endif
            <dl class="lm-divider pt-(--lm-space-lg)">
                <dt class="lm-label">Payment reference</dt>
                <dd class="lm-body mt-(--lm-space-xs) wrap-anywhere">{{ $payment->provider_reference }}</dd>
            </dl>
            <a href="{{ route('home') }}" class="lm-button lm-button--secondary self-start">Back to Home</a>
        </div>
    </section>
@endsection
