@extends('layouts.master')

@section('title', 'Cancel Reservation — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="cancel-heading">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-2xl">
                <p class="lm-eyebrow">Reservation cancellation</p>
                <h1 id="cancel-heading" class="lm-h1">Cancel this reservation?</h1>
                <dl class="lm-divider grid gap-(--lm-space-lg) pt-(--lm-space-lg) sm:grid-cols-2">
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="lm-label">Reservation number</dt>
                        <dd class="lm-h2 mt-(--lm-space-xs) wrap-anywhere">{{ $reservation->reservation_number }}</dd>
                    </div>
                    <div>
                        <dt class="lm-label">Date</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ $reservation->reservation_date->format('l, d F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="lm-label">Time</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ \Carbon\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="lm-label">Guests</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ $reservation->guest_count }}</dd>
                    </div>
                    <div>
                        <dt class="lm-label">Reservation type</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ $reservation->reservation_type === 'table_and_food' ? 'Table + Food' : 'Table Only' }}</dd>
                    </div>
                </dl>

                @if ($reservation->reservation_type === 'table_and_food' && $reservation->payment?->status === 'paid')
                    <p class="lm-alert lm-meta" data-state="info">
                        Payment remains recorded as paid. Any refund is handled manually by the restaurant; this cancellation does not issue a refund.
                    </p>
                @endif

                <div class="lm-divider flex flex-col gap-(--lm-space-sm) pt-(--lm-space-lg) sm:flex-row sm:flex-wrap">
                    <a href="{{ route('home') }}" class="lm-button lm-button--secondary">Keep Reservation</a>
                    <form method="POST" action="{{ route('reservation.cancel', $reservation) . '?' . request()->getQueryString() }}">
                        @csrf
                        <button type="submit" class="lm-button lm-button--danger w-full">Cancel Reservation</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
