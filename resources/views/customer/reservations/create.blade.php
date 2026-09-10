@extends('layouts.master')

@section('title', 'Reserve a Table — Le Miting')

@section('content')
    <section class="bg-brand-950 text-white py-14" aria-label="Reservations">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold md:text-4xl">Reserve a Table</h1>
            <p class="mt-3 max-w-2xl text-brand-100">
                Choose how you would like to reserve. No account needed.
            </p>
        </div>
    </section>

    <section class="py-12" aria-label="Reservation types">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <a
                    href="{{ route('reservation.table') }}"
                    class="group flex flex-col rounded-2xl border border-brand-100 bg-white p-8 shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-brand-300"
                >
                    <span class="text-4xl" aria-hidden="true">🪑</span>
                    <h2 class="mt-4 text-xl font-bold text-brand-800 group-hover:text-primary">Reserve Table Only</h2>
                    <p class="mt-2 text-sm leading-relaxed text-text-muted">
                        Reserve a table for a specific date and time. You can order food when you arrive.
                    </p>
                    <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-800">
                        No payment required
                    </p>
                    <span class="mt-6 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white group-hover:bg-primary-dark">
                        Reserve Table Only
                    </span>
                </a>

                <a
                    href="{{ route('reservation.table-food') }}"
                    class="group flex flex-col rounded-2xl border border-brand-100 bg-white p-8 shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-brand-300"
                >
                    <span class="text-4xl" aria-hidden="true">🍜</span>
                    <h2 class="mt-4 text-xl font-bold text-brand-800 group-hover:text-primary">Reserve Table + Pre-order Food</h2>
                    <p class="mt-2 text-sm leading-relaxed text-text-muted">
                        Reserve a table and pre-order your food in advance, so it is ready when you arrive.
                    </p>
                    <p class="mt-4 rounded-md bg-brand-50 px-3 py-2 text-sm font-medium text-brand-800">
                        Payment required with FIUU
                    </p>
                    <span class="mt-6 inline-flex items-center justify-center rounded-md bg-accent px-6 py-3 text-base font-semibold text-white group-hover:bg-accent-light">
                        Reserve Table + Food
                    </span>
                </a>
            </div>
        </div>
    </section>
@endsection