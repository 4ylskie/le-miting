@extends('emails.layout')

@section('heading')
    Reservation Confirmed, {{ $reservation->customer_name }}!
@endsection

@section('content')
    <p class="muted">Your table at Le Miting is confirmed. Please arrive on time. If your plans change, you can cancel through the link below.</p>

    <dl>
        <div class="row"><dt>Reservation number</dt><dd>{{ $reservation->reservation_number }}</dd></div>
        <div class="row"><dt>Date</dt><dd>{{ $reservation->reservation_date->format('l, d F Y') }}</dd></div>
        <div class="row"><dt>Time</dt><dd>{{ \Carbon\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</dd></div>
        <div class="row"><dt>Guests</dt><dd>{{ $reservation->guest_count }}</dd></div>
        <div class="row"><dt>Table</dt><dd>Table {{ $reservation->table->table_number }} (seats {{ $reservation->table->capacity }})</dd></div>
    </dl>

    @if ($reservation->reservation_type === 'table_and_food' && $reservation->reservationFoods->count() > 0)
        <h3 style="font-size:15px; margin:20px 0 8px;">Pre-ordered Food (paid with FIUU)</h3>
        <dl>
            @foreach ($reservation->reservationFoods as $food)
                <div class="row">
                    <dt>{{ $food->quantity }} × {{ $food->item_name }}</dt>
                    <dd>RM {{ number_format($food->unit_price * $food->quantity, 2) }}</dd>
                </div>
            @endforeach
            <div class="row total"><dt>Total paid</dt><dd>RM {{ number_format($reservation->total, 2) }}</dd></div>
            <div class="row"><dt>Payment reference</dt><dd>{{ $reservation->payment->provider_reference }}</dd></div>
        </dl>
        <div>
            <a class="btn btn-secondary" href="{{ $receiptUrl }}">View &amp; download food receipt (PDF)</a>
        </div>
    @endif

    <div style="text-align:center; margin:20px 0;">
        <img src="{{ $qrDataUri }}" alt="QR code for check-in" width="180" height="180" style="border-radius:10px;">
        <p class="muted">Show this QR code at the restaurant entrance to check in.</p>
    </div>

    <div>
        <a class="btn" href="{{ $cancelUrl }}">Cancel this reservation</a>
    </div>
    <p class="muted" style="margin-top:8px;">The cancellation link expires in 24 hours.</p>
@endsection