@extends('layouts.master')

@section('title', 'Link Invalid — Le Miting')

@section('content')
    <section class="py-24" aria-label="Invalid link">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-2xl font-bold text-brand-800">This link is no longer valid.</h1>
            <p class="mt-3 text-text-muted">
                If you reached this page from an email, the link may have expired.
            </p>
            <a
                href="{{ route('home') }}"
                class="mt-8 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
                Back to Home
            </a>
        </div>
    </section>
@endsection