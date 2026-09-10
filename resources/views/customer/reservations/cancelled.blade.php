@extends('layouts.master')

@section('title', 'Reservation Cancelled — Le Miting')

@section('content')
    <section class="py-12" aria-label="Reservation cancelled">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-brand-100 bg-white p-6 text-center shadow-sm md:p-8">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-3xl" aria-hidden="true">✕</span>
                <h1 class="mt-4 text-2xl font-bold text-brand-800">Reservation Cancelled</h1>

                <dl class="mx-auto mt-6 max-w-sm space-y-3 text-left text-sm">
                    <div class="flex justify-between">
                        <dt class="text-text-muted">Reservation number</dt>
                        <dd class="font-semibold text-text">{{ $reservation->reservation_number }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-text-muted">Status</dt>
                        <dd class="font-semibold text-red-600">Cancelled</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-text-muted">Date</dt>
                        <dd class="text-text">{{ $reservation->reservation_date->format('l, d F Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-text-muted">Time</dt>
                        <dd class="text-text">{{ \Carbon\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</dd>
                    </div>
                </dl>

                @if ($reservation->reservation_type === 'table_and_food')
                    <p class="mt-6 rounded-md bg-brand-50 px-4 py-3 text-sm text-brand-800">
                        Your table slot has been released. If you paid for a food pre-order, the restaurant will handle any applicable refund.
                    </p>
                @endif

                <a
                    href="{{ route('home') }}"
                    class="mt-8 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Back to Home
                </a>
            </div>
        </div>
    </section>
@endsection