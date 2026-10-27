@extends('layouts.master')

@section('title', 'Reservation Confirmed — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="confirmation-heading">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-3xl">
                <p class="lm-status self-start" data-state="success" role="status">Confirmed</p>
                <h1 id="confirmation-heading" class="lm-h1">Reservation Confirmed</h1>
                <p class="lm-body lm-muted">Keep your reservation number for reference.</p>
                <dl class="lm-divider lm-stack pt-(--lm-space-lg)">
                    <div>
                        <dt class="lm-label">Reservation number</dt>
                        <dd class="lm-h2 mt-(--lm-space-xs) wrap-anywhere">{{ $reservation->reservation_number }}</dd>
                    </div>
                </dl>
                <dl class="grid min-w-0 gap-(--lm-space-lg) sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="lm-label">Reservation type</dt>
                        <dd class="lm-body mt-(--lm-space-xs)">{{ $reservation->reservation_type === 'table_and_food' ? 'Table + Food' : 'Table Only' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="lm-label">Email</dt>
                        <dd class="lm-body mt-(--lm-space-xs) wrap-anywhere">{{ $reservation->customer_email }}</dd>
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
                    <div class="min-w-0">
                        <dt class="lm-label">Table</dt>
                        <dd class="lm-body mt-(--lm-space-xs) wrap-anywhere">Table {{ $reservation->table->table_number }} · Seats up to {{ $reservation->table->capacity }}</dd>
                    </div>
                </dl>

                @if ($reservation->reservation_type === 'table_and_food' && $reservation->reservationFoods->count() > 0)
                    <section class="lm-divider lm-stack pt-(--lm-space-lg)" aria-labelledby="food-heading">
                        <h2 id="food-heading" class="lm-h2">Pre-ordered Food</h2>
                        <ul class="divide-y divide-line">
                            @foreach ($reservation->reservationFoods as $food)
                                <x-transaction-item :name="$food->item_name" :quantity="$food->quantity" :unit-price="$food->unit_price" />
                            @endforeach
                        </ul>
                        <p class="lm-divider lm-body flex flex-wrap justify-between gap-(--lm-space-sm) pt-(--lm-space-sm)">
                            <span>Food total</span>
                            <span class="tabular-nums">RM {{ number_format($reservation->total, 2) }}</span>
                        </p>
                        @if ($reservation->payment)
                            <p class="lm-meta lm-muted">Payment status: {{ ucfirst($reservation->payment->status) }}</p>
                        @endif
                    </section>
                @endif

                <div class="lm-divider lm-actions pt-(--lm-space-lg)">
                    <a href="{{ route('home') }}" class="lm-button">Back to Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
