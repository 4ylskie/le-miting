@extends('layouts.master')

@section('title', 'Link Invalid — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="invalid-link-heading">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-2xl">
                <p class="lm-status self-start" data-state="error" role="alert">Link unavailable</p>
                <h1 id="invalid-link-heading" class="lm-h1">This link is no longer valid.</h1>
                <p class="lm-body lm-muted">The link is invalid or has expired. If you reached this page from an email, check that you used the complete link.</p>
                <div class="lm-actions">
                    <a href="{{ route('home') }}" class="lm-button">Back to Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
