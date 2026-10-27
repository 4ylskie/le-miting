@extends('layouts.master')

@section('title', 'Reserve a Table — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="reservation-heading">
        <div class="lm-container lm-stack">
            <header class="lm-stack max-w-3xl">
                <p class="lm-eyebrow">Reservations</p>
                <h1 id="reservation-heading" class="lm-h1">A table for your visit.</h1>
                <p class="lm-body lm-muted">Choose how you would like to reserve. No account needed.</p>
            </header>

            <div class="grid gap-(--lm-space-xl) pt-(--lm-space-lg) md:grid-cols-2">
                <section class="lm-divider lm-stack min-w-0 pt-(--lm-space-lg)" aria-labelledby="table-only-heading">
                    <p class="lm-eyebrow">Level 1</p>
                    <h2 id="table-only-heading" class="lm-h2">Reservation Only</h2>
                    <p class="lm-body lm-muted max-w-prose">Reserve your table and order when you arrive.</p>
                    <a href="{{ route('reservation.table') }}" class="lm-button self-start">Choose Reservation Only</a>
                </section>
                <section class="lm-divider lm-stack min-w-0 pt-(--lm-space-lg)" aria-labelledby="table-food-heading">
                    <p class="lm-eyebrow">Level 2</p>
                    <h2 id="table-food-heading" class="lm-h2">Reservation + Pre-Order</h2>
                    <p class="lm-body lm-muted max-w-prose">Reserve your table and choose your food in advance.</p>
                    <a href="{{ route('reservation.table-food') }}" class="lm-button self-start">Choose Reservation + Pre-Order</a>
                </section>
            </div>
        </div>
    </section>
@endsection
