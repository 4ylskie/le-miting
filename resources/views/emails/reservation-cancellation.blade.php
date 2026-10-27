@extends('emails.layout')

@section('heading')
    Your reservation has been cancelled.
@endsection

@section('content')
    <p class="muted">Hi {{ $reservation->customer_name }}, your reservation at Le Miting has been cancelled as requested.</p>

    <dl>
        <div class="row"><dt>Reservation number</dt><dd>{{ $reservation->reservation_number }}</dd></div>
        <div class="row"><dt>Original date</dt><dd>{{ $reservation->reservation_date->format('l, d F Y') }}</dd></div>
        <div class="row"><dt>Original time</dt><dd>{{ \Carbon\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</dd></div>
        <div class="row"><dt>Table</dt><dd>Table {{ $reservation->table->table_number }}</dd></div>
    </dl>

    @if ($hasVerifiedPaidPayment)
        <p class="muted mt-16">This cancellation does not issue a refund. Please contact the restaurant about any refund request; eligibility and processing are handled by the restaurant.</p>
    @endif

    <p class="muted">We hope to welcome you to Le Miting another time.</p>

    <div>
        <a class="btn btn-secondary" href="{{ route('home') }}">Visit Le Miting</a>
    </div>
@endsection
