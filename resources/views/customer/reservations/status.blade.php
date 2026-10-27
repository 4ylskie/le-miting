@extends('layouts.master')

@section('title', 'Reservation Status — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="status-heading">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-2xl">
                <p class="lm-status self-start" data-state="info" role="status">{{ ucfirst($reservation->status) }}</p>
                <h1 id="status-heading" class="lm-h1">
                    @if ($reservation->status === 'completed')
                        This reservation has already been completed.
                    @elseif ($reservation->status === 'cancelled')
                        This reservation has already been cancelled.
                    @else
                        This reservation can no longer be cancelled online.
                    @endif
                </h1>
                <dl class="lm-divider pt-(--lm-space-lg)">
                    <dt class="lm-label">Reservation number</dt>
                    <dd class="lm-h2 mt-(--lm-space-xs) wrap-anywhere">{{ $reservation->reservation_number }}</dd>
                </dl>
                <p class="lm-body lm-muted">
                    @if ($reservation->checked_in_at !== null)
                        This reservation has been checked in and can no longer be cancelled.
                    @elseif (in_array($reservation->status, ['pending', 'confirmed'], true))
                        Its reservation time has already started.
                    @else
                        This reservation can no longer be cancelled online.
                    @endif
                </p>
                <div class="lm-actions">
                    <a href="{{ route('home') }}" class="lm-button">Back to Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
