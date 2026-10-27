@extends('layouts.master')
@inject('businessTime', 'App\Services\BusinessTime')
@php
    $businessNow = $businessTime->now();
@endphp

@section('title', $type === 'table_and_food' ? 'Reservation + Pre-Order — Le Miting' : 'Reservation Only — Le Miting')

@section('content')
    <section class="lm-section lm-section--compact" aria-labelledby="reservation-heading">
        <div class="lm-container">
            <div class="lm-stack mx-auto max-w-4xl">
                <header class="lm-stack">
                    <a href="{{ route('reservation.create') }}" class="lm-button lm-button--quiet self-start">Reservation options</a>
                    <p class="lm-eyebrow">{{ $type === 'table_and_food' ? 'Level 2' : 'Level 1' }}</p>
                    <h1 id="reservation-heading" class="lm-h1">{{ $type === 'table_and_food' ? 'Reservation + Pre-Order' : 'Reservation Only' }}</h1>
                    <p class="lm-body lm-muted max-w-prose">
                        {{ $type === 'table_and_food'
                            ? 'Choose a time, table and dishes. Payment for your food is required before your reservation is confirmed.'
                            : 'Choose a date, time and available table. No food pre-order or payment is required.' }}
                    </p>
                </header>

                @if (session('success'))
                    <p class="lm-alert" data-state="success" role="status">{{ session('success') }}</p>
                @endif
                @if (session('error'))
                    <p class="lm-alert" data-state="error" role="alert">{{ session('error') }}</p>
                @endif
                @if ($errors->any())
                    <div class="lm-alert" data-state="error" role="alert">
                        <p class="lm-body">Please check your reservation details.</p>
                        <ul class="lm-meta mt-(--lm-space-xs) list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li class="wrap-anywhere">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="availability-form" method="GET" action="{{ route('reservation.availability') }}" data-slots-url="{{ route('reservation.slots') }}" data-business-date="{{ $businessNow->toDateString() }}" data-first-bookable-time="{{ $businessNow->addMinutes(30 - $businessNow->minute % 30)->format('H:i') }}" class="lm-divider lm-stack pt-(--lm-space-lg)">
                    <input type="hidden" name="reservation_type" value="{{ $type }}">
                    <fieldset class="lm-stack min-w-0">
                        <legend class="lm-h2 mb-(--lm-space-md)">1. Plan your visit</legend>
                        <p class="lm-meta lm-muted">All fields are required.</p>
                        <div class="grid min-w-0 gap-(--lm-space-md) sm:grid-cols-3">
                            <div class="lm-field min-w-0">
                                <label for="reservation_date" class="lm-label">Date</label>
                                <input id="reservation_date" type="date" name="reservation_date" value="{{ old('reservation_date', $reservationDate ?? '') }}" required min="{{ $businessNow->toDateString() }}" class="lm-control min-w-0"
                                    @if ($errors->has('reservation_date')) aria-invalid="true" aria-describedby="reservation_date-error" @endif>
                                @error('reservation_date')
                                    <p id="reservation_date-error" class="lm-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="lm-field min-w-0">
                                <label for="reservation_time" class="lm-label">Time</label>
                                @if (config('restaurant.opening_hours') === null)
                                <input id="reservation_time" type="time" name="reservation_time" value="{{ old('reservation_time', $reservationTime ?? '') }}" required step="1800" class="lm-control min-w-0"
                                    @if ($errors->has('reservation_time')) aria-invalid="true" aria-describedby="reservation_time-error" @endif>
                                @else
                                    <select id="reservation_time" name="reservation_time" required class="lm-control" data-selected="{{ old('reservation_time') }}"
                                        @if ($errors->has('reservation_time')) aria-invalid="true" aria-describedby="reservation_time-error" @endif>
                                        <option value="">Choose a date first</option>
                                    </select>
                                @endif
                                @error('reservation_time')
                                    <p id="reservation_time-error" class="lm-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="lm-field min-w-0">
                                <label for="guest_count" class="lm-label">Number of guests</label>
                                <input id="guest_count" type="number" name="guest_count" value="{{ old('guest_count', $guestCount ?? '') }}" required min="1" max="20" class="lm-control"
                                    @if ($errors->has('guest_count')) aria-invalid="true" aria-describedby="guest_count-error" @endif>
                                @error('guest_count')
                                    <p id="guest_count-error" class="lm-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </fieldset>
                    <p class="lm-meta lm-muted">Start on the hour or half hour. Each visit lasts {{ \App\Services\ReservationService::DURATION_MINUTES }} minutes.</p>
                    <p id="slot-status" class="lm-meta lm-muted" role="status"></p>
                    <button type="submit" class="lm-button self-start">Check Table Availability</button>
                </form>

                @if ($availableTables !== null)
                    @php
                        $requestedGuests = (int) old('guest_count', $guestCount ?? 0);
                        $hasEligibleTable = $availableTables->isNotEmpty();
                    @endphp
                    <form method="POST" action="{{ route('reservation.review') }}" id="reservation-form" class="lm-divider lm-stack mt-(--lm-space-lg) pt-(--lm-space-lg)"
                        @if ($type === 'table_and_food') data-food-required="1" @endif>
                        @csrf
                        <input type="hidden" name="reservation_type" value="{{ $type }}">
                        <input type="hidden" name="reservation_date" value="{{ old('reservation_date') }}">
                        <input type="hidden" name="reservation_time" value="{{ old('reservation_time') }}">
                        <input type="hidden" name="guest_count" value="{{ old('guest_count') }}">

                        <fieldset class="lm-stack min-w-0" @if ($errors->has('table_id')) aria-describedby="table_id-error" @endif>
                            <legend class="lm-h2 mb-(--lm-space-md)">2. Choose a table</legend>
                            <p class="lm-meta lm-muted" role="status">
                                For {{ old('reservation_date') }} at {{ old('reservation_time') }} · {{ $requestedGuests }} guests.
                            </p>
                            @if ($availableTables->count() > 0)
                                <x-reservation-floor-plan :tables="$availableTables" :plan="$floorPlan" />
                            @else
                                <p class="lm-empty lm-body" role="status">
                                    @if ($partyTooLarge)
                                        We don't have a table large enough for this party size. Please choose a smaller party size.
                                    @elseif (! $hasSuitableTables)
                                        No supported tables suit this party size. Please adjust your party size.
                                    @else
                                        No suitable tables are available for this time. Choose another date, time or party size above.
                                    @endif
                                </p>
                            @endif
                            @error('table_id')
                                <p id="table_id-error" class="lm-error" role="alert">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        @if ($type === 'table_and_food' && $availableTables->count() > 0)
                            <fieldset class="lm-stack min-w-0 mt-(--lm-space-lg)" aria-describedby="food-guidance food-selection-error">
                                <legend class="lm-h2 mb-(--lm-space-md)">3. Choose your food</legend>
                                <p id="food-guidance" class="lm-body lm-muted">Select at least one dish. Adjust its quantity below, or uncheck it to remove it from this reservation. Your order cart is separate.</p>
                                @php($grouped = $menuItems->groupBy(fn ($item) => $item->category->name))
                                <div id="food-selection" class="lm-stack">
                                    @forelse ($grouped as $categoryName => $items)
                                        <div class="min-w-0">
                                            <p class="lm-eyebrow mb-(--lm-space-md) wrap-anywhere">{{ $categoryName }}</p>
                                            @foreach ($items as $item)
                                                <x-menu-item :item="$item" heading="h3" :orderable="false" data-reservation-food>
                                                    <div class="flex flex-col gap-(--lm-space-sm) sm:flex-row sm:items-end sm:justify-between">
                                                        <label for="food-{{ $item->id }}" class="lm-choice min-w-0">
                                                            <input id="food-{{ $item->id }}" type="checkbox" name="food_items[{{ $item->id }}][menu_item_id]" value="{{ $item->id }}"
                                                                class="food-check lm-check shrink-0" data-item-id="{{ $item->id }}" data-name="{{ $item->name }}" data-price="{{ $item->price }}"
                                                                @checked(old('food_items.'.$item->id.'.menu_item_id') == $item->id)
                                                                @if ($errors->has('food_items.'.$item->id.'.menu_item_id')) aria-invalid="true" aria-describedby="food-{{ $item->id }}-error" @endif>
                                                            <span class="lm-meta wrap-anywhere">Select {{ $item->name }}</span>
                                                        </label>
                                                        <div class="lm-field sm:w-28 sm:shrink-0">
                                                            <label for="food-quantity-{{ $item->id }}" class="lm-label">Quantity<span class="sr-only"> for {{ $item->name }}</span></label>
                                                            <select id="food-quantity-{{ $item->id }}" name="food_items[{{ $item->id }}][quantity]" class="food-qty lm-control"
                                                                @disabled(old('food_items.'.$item->id.'.menu_item_id') != $item->id)
                                                                @if ($errors->has('food_items.'.$item->id.'.quantity')) aria-invalid="true" aria-describedby="food-quantity-{{ $item->id }}-error" @endif>
                                                                @for ($i = 1; $i <= 10; $i++)
                                                                    <option value="{{ $i }}" @selected(old('food_items.'.$item->id.'.quantity', 1) == $i)>{{ $i }}</option>
                                                                @endfor
                                                            </select>
                                                        </div>
                                                    </div>
                                                    @error('food_items.'.$item->id.'.menu_item_id')
                                                        <p id="food-{{ $item->id }}-error" class="lm-error" role="alert">{{ $message }}</p>
                                                    @enderror
                                                    @error('food_items.'.$item->id.'.quantity')
                                                        <p id="food-quantity-{{ $item->id }}-error" class="lm-error" role="alert">{{ $message }}</p>
                                                    @enderror
                                                </x-menu-item>
                                            @endforeach
                                        </div>
                                    @empty
                                        <p class="lm-empty lm-body" role="status">No dishes are available to pre-order. Please try again later or choose Table Only.</p>
                                    @endforelse
                                </div>
                                <p class="lm-divider lm-body flex flex-wrap justify-between gap-(--lm-space-sm) pt-(--lm-space-md)">
                                    <span>Food total</span>
                                    <span id="food-total" class="tabular-nums" role="status" aria-live="polite">RM 0.00</span>
                                </p>
                                <p id="food-selection-error" class="lm-error" role="alert" hidden>Please select at least one dish to pre-order.</p>
                                @error('food_items')
                                    <p class="lm-error" role="alert">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        @endif

                        @if ($availableTables->count() > 0)
                            <fieldset class="lm-divider lm-stack min-w-0 mt-(--lm-space-lg) pt-(--lm-space-lg)">
                                <legend class="lm-h2 mb-(--lm-space-md)">{{ $type === 'table_and_food' ? '4.' : '3.' }} Your details</legend>
                                <p class="lm-meta lm-muted">All fields are required except notes.</p>
                                <div class="grid min-w-0 gap-(--lm-space-md) sm:grid-cols-2">
                                    <div class="lm-field">
                                        <label for="customer_name" class="lm-label">Name</label>
                                        <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name') }}" required autocomplete="name" class="lm-control"
                                            @if ($errors->has('customer_name')) aria-invalid="true" aria-describedby="customer_name-error" @endif>
                                        @error('customer_name')
                                            <p id="customer_name-error" class="lm-error" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="lm-field">
                                        <label for="customer_email" class="lm-label">Email</label>
                                        <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" required autocomplete="email" class="lm-control"
                                            @if ($errors->has('customer_email')) aria-invalid="true" aria-describedby="customer_email-error" @endif>
                                        @error('customer_email')
                                            <p id="customer_email-error" class="lm-error" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="lm-field">
                                        <label for="customer_phone" class="lm-label">Phone number</label>
                                        <input id="customer_phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required autocomplete="tel" placeholder="01XXXXXXXXX" class="lm-control"
                                            @if ($errors->has('customer_phone')) aria-invalid="true" aria-describedby="customer_phone-error" @endif>
                                        @error('customer_phone')
                                            <p id="customer_phone-error" class="lm-error" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="lm-field sm:col-span-2">
                                        <label for="special_request" class="lm-label">Notes (optional)</label>
                                        <textarea id="special_request" name="special_request" rows="3" class="lm-control"
                                            @if ($errors->has('special_request')) aria-invalid="true" aria-describedby="special_request-error" @endif>{{ old('special_request') }}</textarea>
                                        @error('special_request')
                                            <p id="special_request-error" class="lm-error" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </fieldset>
                            @if ($type === 'table_and_food')
                                <p class="lm-alert lm-meta" data-state="pending">Continuing creates a pending reservation with a 15-minute payment hold. Your table is confirmed only after payment for your food is verified.</p>
                            @endif
                            <button id="reservation-submit" type="submit" class="lm-button w-full sm:w-auto sm:self-start"
                                @disabled(! $hasEligibleTable || ($type === 'table_and_food' && $menuItems->isEmpty()))>
                                Review Reservation
                            </button>
                        @endif
                    </form>
                @endif
            </div>
        </div>
    </section>

@endsection
