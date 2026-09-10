@extends('layouts.master')

@section('title', 'Verifying Payment — Le Miting')

@section('content')
    <section class="py-24" aria-label="Verifying payment">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8 text-center">
            <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-50" role="status" aria-label="Loading">
                <span class="h-8 w-8 animate-spin rounded-full border-4 border-brand-200 border-t-primary" aria-hidden="true"></span>
            </div>
            <h1 class="mt-6 text-2xl font-bold text-brand-800">Verifying your payment</h1>
            <p class="mt-3 text-text-muted">
                Your payment result will appear here once it is verified. Returning to this page does not mean your payment succeeded.
            </p>
            <p class="mt-4 text-sm text-text-muted">Reference: {{ $payment->provider_reference }}</p>
            <a href="{{ route('home') }}" class="mt-8 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white hover:bg-primary-dark">
                Back to Home
            </a>
        </div>
    </section>
@endsection