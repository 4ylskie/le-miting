@extends('layouts.master')

@section('title', 'Payment Failed — Le Miting')

@section('content')
    <section class="py-20" aria-label="Payment failed">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8 text-center">
            <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-3xl" aria-hidden="true">✕</span>
            <h1 class="mt-6 text-2xl font-bold text-brand-800">Payment could not be processed.</h1>
            <p class="mt-3 text-text-muted">
                Please try again or return to checkout. No payment was deducted.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a
                    href="{{ route('cart.index') }}"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Try Again
                </a>
                <a
                    href="{{ route('menu.index') }}"
                    class="inline-flex items-center justify-center rounded-md border border-brand-200 bg-white px-6 py-3 text-base font-semibold text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-300"
                >
                    Browse Menu
                </a>
            </div>
        </div>
    </section>
@endsection