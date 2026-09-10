@extends('layouts.master')

@section('title', 'Reservation Confirmed — Le Miting')

@section('content')
    <section class="py-12" aria-label="Reservation confirmed">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-3xl" aria-hidden="true">✓</span>
                <h1 class="mt-4 text-3xl font-bold text-brand-800">Reservation Confirmed</h1>
                <p class="mt-2 text-text-muted">
                    A confirmation email with your QR code has been sent to <strong>{{ $reservation->customer_email }}</strong>.
                </p>
            </div>

            <div class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8">
                <h2 class="text-lg font-semibold text-text">Reservation Details</h2>

                <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Reservation number</dt>
                        <dd class="mt-1 text-lg font-bold text-primary">{{ $reservation->reservation_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Date</dt>
                        <dd class="mt-1 text-text">{{ $reservation->reservation_date->format('l, d F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Time</dt>
                        <dd class="mt-1 text-text">{{ \Carbon\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Guests</dt>
                        <dd class="mt-1 text-text">{{ $reservation->guest_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Table</dt>
                        <dd class="mt-1 text-text">Table {{ $reservation->table->table_number }} (seats {{ $reservation->table->capacity }})</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-text-muted">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-800">Confirmed</span>
                        </dd>
                    </div>
                </dl>

                @if ($reservation->reservationFoods->count() > 0)
                    <div class="mt-8">
                        <h3 class="font-semibold text-text">Pre-ordered Food</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($reservation->reservationFoods as $food)
                                <li class="flex justify-between">
                                    <span class="text-text">{{ $food->quantity }} × {{ $food->item_name }}</span>
                                    <span class="font-medium text-text">RM {{ number_format($food->unit_price * $food->quantity, 2) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 flex justify-between border-t border-brand-100 pt-3 font-semibold text-text">
                            <span>Food total</span>
                            <span class="text-primary">RM {{ number_format($reservation->total, 2) }}</span>
                        </p>
                        <p class="mt-2 rounded-md bg-green-50 px-3 py-2 text-xs text-green-800">
                            This amount was paid with FIUU.
                        </p>
                    </div>
                @endif

                <div class="mt-8 rounded-xl bg-brand-50 p-6 text-center">
                    <h3 class="font-semibold text-text">Check-in QR Code</h3>
                    <p class="mt-1 text-xs text-text-muted">
                        Show this QR code at the restaurant entrance to check in.
                    </p>
                    <div class="mt-4 inline-block rounded-xl bg-white p-4 shadow-sm" aria-hidden="true">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->generate($reservation->reservation_number) !!}
                    </div>
                    <p class="mt-4 text-sm font-semibold text-text">Reservation number: {{ $reservation->reservation_number }}</p>
                </div>
            </div>

            <div class="mt-8 text-center">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Back to Home
                </a>
            </div>
        </div>
    </section>
@endsection