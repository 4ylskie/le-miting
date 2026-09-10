@extends('layouts.master')

@section('title', 'Cancel Reservation — Le Miting')

@section('content')
    <section class="py-12" aria-label="Cancel reservation">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8">
                <h1 class="text-2xl font-bold text-brand-800">Cancel this reservation?</h1>

                <p class="mt-3 text-sm leading-relaxed text-text-muted">
                    Reservation <strong class="text-text">{{ $reservation->reservation_number }}</strong>
                    for <strong class="text-text">{{ $reservation->guest_count }} guests</strong>
                    on <strong class="text-text">{{ $reservation->reservation_date->format('l, d F Y') }}</strong>
                    at <strong class="text-text">{{ \Carbon\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</strong>.
                </p>

                @if ($reservation->reservation_type === 'table_and_food')
                    <p class="mt-4 rounded-md bg-brand-50 px-3 py-2 text-xs text-brand-800">
                        Your food pre-order payment was handled by FIUU. Refunds are processed by the restaurant; contact us for assistance.
                    </p>
                @endif

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <a
                        href="{{ route('home') }}"
                        class="inline-flex items-center justify-center rounded-md border border-brand-200 bg-white px-6 py-3 text-base font-semibold text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-300"
                    >
                        Keep Reservation
                    </a>
                    <form
                        method="POST"
                        action="{{ route('reservation.cancel', $reservation) . '?' . request()->getQueryString() }}"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-md bg-red-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2"
                        >
                            Cancel Reservation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection