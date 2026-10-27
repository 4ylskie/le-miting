@extends('layouts.master')

@section('title', 'Review your reservation — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-3xl">
                <p class="lm-eyebrow">{{ $data['reservation_type'] === 'table_only' ? 'Level 1' : 'Level 2' }}</p>
                <h1 class="lm-h1">Review your reservation</h1>
                <h2 class="lm-h2">{{ $data['reservation_type'] === 'table_only' ? 'Reservation Only' : 'Reservation + Pre-Order' }}</h2>
                <dl class="grid gap-5 border-y border-line py-6 sm:grid-cols-2">
                    <div><dt class="lm-meta lm-muted">Date</dt><dd>{{ $data['reservation_date'] }}</dd></div>
                    <div><dt class="lm-meta lm-muted">Visit</dt><dd>{{ $data['reservation_time'] }}–{{ $end->format('H:i') }} · 120 minutes</dd></div>
                    <div><dt class="lm-meta lm-muted">Table</dt><dd>{{ $table->table_number }} · Seats {{ $table->capacity }}</dd></div>
                    <div><dt class="lm-meta lm-muted">Party size</dt><dd>{{ $data['guest_count'] }} guests</dd></div>
                    <div><dt class="lm-meta lm-muted">Guest</dt><dd class="wrap-anywhere">{{ $data['customer_name'] }}</dd></div>
                    <div><dt class="lm-meta lm-muted">Contact</dt><dd class="wrap-anywhere">{{ $data['customer_email'] }}<br>{{ $data['customer_phone'] }}</dd></div>
                    @if (!empty($data['special_request']))
                        <div class="sm:col-span-2"><dt class="lm-meta lm-muted">Notes</dt><dd class="wrap-anywhere">{{ $data['special_request'] }}</dd></div>
                    @endif
                </dl>
                @if ($data['reservation_type'] === 'table_and_food')
                    <section class="lm-stack" aria-labelledby="preorder-heading">
                        <h2 id="preorder-heading" class="lm-h2">Your pre-order</h2>
                        <ul class="divide-y divide-line">
                            @foreach ($items as $item)
                                <li class="flex flex-wrap justify-between gap-3 py-4">
                                    <div><p>{{ $item['name'] }}</p><p class="lm-meta lm-muted">{{ $item['quantity'] }} × RM {{ number_format($item['unit_price'], 2) }}</p></div>
                                    <p>RM {{ number_format($item['line_total'], 2) }}</p>
                                </li>
                            @endforeach
                        </ul>
                        <p class="lm-body">Food total: RM {{ number_format($total, 2) }}</p>
                        <p class="lm-meta lm-muted">Payment is for your food only. Prices and availability are checked again when you continue. Your pending table hold lasts {{ \App\Services\ReservationService::HOLD_MINUTES }} minutes.</p>
                    </section>
                @endif
                <p class="lm-meta lm-muted">Your table is not held during this review. Availability is checked again when you continue.</p>
                <form method="POST" action="{{ route('reservation.store') }}" class="lm-stack">
                    @csrf
                    @foreach (['reservation_type', 'table_id', 'reservation_date', 'reservation_time', 'guest_count', 'customer_name', 'customer_email', 'customer_phone', 'special_request'] as $field)
                        <input type="hidden" name="{{ $field }}" value="{{ $data[$field] ?? '' }}">
                    @endforeach
                    @foreach ($items as $item)
                        <input type="hidden" name="food_items[{{ $item['id'] }}][menu_item_id]" value="{{ $item['id'] }}">
                        <input type="hidden" name="food_items[{{ $item['id'] }}][quantity]" value="{{ $item['quantity'] }}">
                    @endforeach
                    <button class="lm-button self-start" type="submit">{{ $data['reservation_type'] === 'table_only' ? 'Confirm Reservation' : 'Continue to Payment' }}</button>
                </form>
                <a class="lm-button lm-button--quiet self-start" href="{{ route('reservation.availability', \Illuminate\Support\Arr::only($data, ['reservation_type', 'reservation_date', 'reservation_time', 'guest_count'])) }}">Change reservation details</a>
            </div>
        </div>
    </section>
@endsection
