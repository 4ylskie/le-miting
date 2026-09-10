@extends('layouts.master')

@section('title', 'Thank You — Le Miting')

@section('content')
    <section class="py-12" aria-label="Feedback submitted">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-3xl" aria-hidden="true">✓</span>
                <h1 class="mt-4 text-3xl font-bold text-brand-800">Thank You!</h1>
                <p class="mt-2 text-text-muted">
                    We received your feedback for {{ $transactionLabel }}. It means the world to us.
                </p>
            </div>

            <div class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 text-center shadow-sm md:p-8">
                <h2 class="text-lg font-semibold text-text">Enjoyed your visit?</h2>
                <p class="mt-2 text-sm text-text-muted">
                    Your feedback stays private with us. If you would like to share your experience publicly, we would love a review on our Google page.
                </p>
                <a
                    href="{{ config('restaurant.google_review_url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-5 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Leave a Google Review
                </a>
            </div>

            <div class="mt-8 text-center">
                <a href="{{ route('home') }}" class="text-sm font-semibold text-primary hover:text-primary-dark">
                    Back to Home
                </a>
            </div>
        </div>
    </section>
@endsection