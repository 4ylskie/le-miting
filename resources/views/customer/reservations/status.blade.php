@extends('layouts.master')

@section('title', 'Reservation Status — Le Miting')

@section('content')
    <section class="py-12" aria-label="Reservation status">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-brand-100 bg-white p-6 text-center shadow-sm md:p-8">
                <h1 class="text-2xl font-bold text-brand-800">
                    @if ($reservation->status === 'completed')
                        This reservation has already been completed.
                    @else
                        This reservation has already been cancelled.
                    @endif
                </h1>
                <p class="mt-3 text-sm text-text-muted">
                    Reservation {{ $reservation->reservation_number }} can no longer be cancelled online.
                </p>
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