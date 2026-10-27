@extends('layouts.master')

@section('title', 'Reservation Cancelled — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="cancelled-heading">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-2xl">
                <p class="lm-status self-start" data-state="info" role="status">Cancelled</p>
                <h1 id="cancelled-heading" class="lm-h1">Reservation Cancelled</h1>
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
                </dl>
                @if ($reservation->reservation_type === 'table_and_food')
                    <p class="lm-alert lm-meta" data-state="info">
                        @if ($reservation->payment?->status === 'paid')
                            Payment remains recorded as paid. Any refund is handled manually by the restaurant; this cancellation does not issue a refund.
                        @else
                            No verified food pre-order payment is recorded. Your table slot has been released.
                        @endif
                    </p>
                @endif
                <div class="lm-actions">
                    <a href="{{ route('home') }}" class="lm-button">Back to Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
