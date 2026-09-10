@extends('layouts.master')

@section('title', $type === 'table_and_food' ? 'Reserve Table + Food — Le Miting' : 'Reserve Table Only — Le Miting')

@section('content')
    <section class="bg-brand-950 text-white py-14" aria-label="Reservation details">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold md:text-4xl">
                {{ $type === 'table_and_food' ? 'Reserve Table + Pre-order Food' : 'Reserve Table Only' }}
            </h1>
            <p class="mt-3 max-w-2xl text-brand-100">
                {{ $type === 'table_and_food'
                    ? 'Pick a time, choose an available table, and pre-order your food. Payment is required to confirm.'
                    : 'Pick a date and time, choose an available table, and confirm. No payment required.' }}
            </p>
        </div>
    </section>

    <section class="py-12" aria-label="Reservation form">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <form method="GET" action="{{ route('reservation.availability') }}" class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8">
                <h2 class="text-lg font-semibold text-text">1. Reservation Details</h2>

                <input type="hidden" name="reservation_type" value="{{ $type }}">

                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label for="reservation_date" class="block text-sm font-medium text-text">Date <span class="text-primary" aria-hidden="true">*</span></label>
                        <input
                            id="reservation_date"
                            type="date"
                            name="reservation_date"
                            value="{{ old('reservation_date', $reservationDate ?? '') }}"
                            required
                            min="{{ date('Y-m-d') }}"
                            class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('reservation_date') ? 'border-red-400' : '' }}"
                        >
                        @error('reservation_date')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reservation_time" class="block text-sm font-medium text-text">Time <span class="text-primary" aria-hidden="true">*</span></label>
                        <input
                            id="reservation_time"
                            type="time"
                            name="reservation_time"
                            value="{{ old('reservation_time', $reservationTime ?? '') }}"
                            required
                            min="10:00"
                            max="21:00"
                            class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('reservation_time') ? 'border-red-400' : '' }}"
                        >
                        @error('reservation_time')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="guest_count" class="block text-sm font-medium text-text">Number of guests <span class="text-primary" aria-hidden="true">*</span></label>
                        <input
                            id="guest_count"
                            type="number"
                            name="guest_count"
                            value="{{ old('guest_count', $guestCount ?? '') }}"
                            required
                            min="1"
                            max="20"
                            class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('guest_count') ? 'border-red-400' : '' }}"
                        >
                        @error('guest_count')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="mt-6 inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    Check Table Availability
                </button>
            </form>

            @if ($availableTables !== null)
                <form
                    method="POST"
                    action="{{ route('reservation.store') }}"
                    class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8"
                    @if ($type === 'table_and_food') data-food-required="1" @endif
                >
                    @csrf
                    <input type="hidden" name="reservation_type" value="{{ $type }}">
                    <input type="hidden" name="reservation_date" value="{{ old('reservation_date') }}">
                    <input type="hidden" name="reservation_time" value="{{ old('reservation_time') }}">
                    <input type="hidden" name="guest_count" value="{{ old('guest_count') }}">

                    <h2 class="text-lg font-semibold text-text">2. Select a Table</h2>

                    @if ($availableTables->count() > 0)
                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($availableTables as $table)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-200 bg-white p-4 {{ old('table_id') == $table->id ? 'border-primary bg-brand-50' : 'hover:bg-brand-50' }}">
                                    <input
                                        type="radio"
                                        name="table_id"
                                        value="{{ $table->id }}"
                                        class="mt-1 h-4 w-4 text-primary focus:ring-primary"
                                        @checked(old('table_id') == $table->id)
                                        required
                                    >
                                    <span>
                                        <span class="block font-semibold text-text">Table {{ $table->table_number }}</span>
                                        <span class="mt-1 block text-sm text-text-muted">Seats up to {{ $table->capacity }}</span>
                                        <span class="mt-2 inline-block rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-800">Available</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('table_id')
                            <p class="mt-3 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">
                                {{ $message }}
                            </p>
                        @enderror
                    @else
                        <p class="mt-5 rounded-md bg-brand-50 px-4 py-4 text-center text-brand-800">
                            No tables available. Please try a different date or time.
                        </p>
                    @endif

                    @if ($type === 'table_and_food' && $availableTables->count() > 0)
                        <div class="mt-10">
                            <h2 class="text-lg font-semibold text-text">3. Pre-order Food</h2>
                            <p class="mt-1 text-sm text-text-muted">Pick dishes you would like ready when you arrive. You can also order at the table later.</p>

                            @php
                                $grouped = $menuItems->groupBy(fn ($item) => $item->category->name);
                            @endphp

                            <div class="mt-5 space-y-6" id="food-selection">
                                @foreach ($grouped as $categoryName => $items)
                                    <div>
                                        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">{{ $categoryName }}</h3>
                                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            @foreach ($items as $item)
                                                <div class="flex items-center justify-between gap-3 rounded-lg border border-brand-100 p-3">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-medium text-text">{{ $item->name }}</p>
                                                        <p class="text-sm text-text-muted">RM {{ number_format($item->price, 2) }}</p>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-2">
                                                        <input
                                                            type="checkbox"
                                                            name="food_items[{{ $item->id }}][menu_item_id]"
                                                            value="{{ $item->id }}"
                                                            class="food-check h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary"
                                                            data-item-id="{{ $item->id }}"
                                                            data-name="{{ $item->name }}"
                                                            data-price="{{ $item->price }}"
                                                        >
                                                        <select
                                                            name="food_items[{{ $item->id }}][quantity]"
                                                            class="food-qty rounded-md border border-brand-200 px-2 py-1 text-sm text-text"
                                                            disabled
                                                        >
                                                            @for ($i = 1; $i <= 10; $i++)
                                                                <option value="{{ $i }}">{{ $i }}</option>
                                                            @endfor
                                                        </select>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-4 rounded-md bg-brand-50 px-4 py-3 text-sm text-brand-800">
                                Food total: <span class="font-bold" id="food-total">RM 0.00</span>
                            </p>
                            <p class="mt-2 text-xs text-text-muted">
                                You will pay for your selected food with FIUU to confirm this reservation.
                            </p>
                            @error('food_items')
                                <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if ($availableTables->count() > 0)
                        <div class="mt-10">
                            <h2 class="text-lg font-semibold text-text">4. Your Details</h2>

                            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="customer_name" class="block text-sm font-medium text-text">Name <span class="text-primary" aria-hidden="true">*</span></label>
                                    <input
                                        id="customer_name"
                                        type="text"
                                        name="customer_name"
                                        value="{{ old('customer_name') }}"
                                        required
                                        autocomplete="name"
                                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('customer_name') ? 'border-red-400' : '' }}"
                                    >
                                    @error('customer_name')
                                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="customer_email" class="block text-sm font-medium text-text">Email <span class="text-primary" aria-hidden="true">*</span></label>
                                    <input
                                        id="customer_email"
                                        type="email"
                                        name="customer_email"
                                        value="{{ old('customer_email') }}"
                                        required
                                        autocomplete="email"
                                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('customer_email') ? 'border-red-400' : '' }}"
                                    >
                                    @error('customer_email')
                                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="customer_phone" class="block text-sm font-medium text-text">Phone number <span class="text-primary" aria-hidden="true">*</span></label>
                                    <input
                                        id="customer_phone"
                                        type="tel"
                                        name="customer_phone"
                                        value="{{ old('customer_phone') }}"
                                        required
                                        autocomplete="tel"
                                        placeholder="01XXXXXXXXX"
                                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('customer_phone') ? 'border-red-400' : '' }}"
                                    >
                                    @error('customer_phone')
                                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="special_request" class="block text-sm font-medium text-text">Notes (optional)</label>
                                    <textarea
                                        id="special_request"
                                        name="special_request"
                                        rows="1"
                                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Birthday, seating preference, allergies..."
                                    >{{ old('special_request') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <button
                            id="reservation-submit"
                            type="submit"
                            class="mt-8 inline-flex w-full items-center justify-center rounded-md {{ $type === 'table_and_food' ? 'bg-accent hover:bg-accent-light' : 'bg-primary hover:bg-primary-dark' }} px-6 py-3 text-base font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                        >
                            {{ $type === 'table_and_food' ? 'Continue to Payment' : 'Confirm Reservation' }}
                        </button>
                    @endif
                </form>
            @endif
        </div>
    </section>

    @if ($type === 'table_and_food' && $availableTables !== null)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const items = document.querySelectorAll('.food-check');
                    const totalEl = document.getElementById('food-total');

                    function currency(n) {
                        return 'RM ' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    function recalc() {
                        let total = 0;
                        items.forEach(function (check) {
                            if (check.checked) {
                                const row = check.closest('.rounded-lg');
                                const qty = row.querySelector('.food-qty');
                                total += parseFloat(check.dataset.price) * parseInt(qty.value, 10);
                            }
                        });
                        totalEl.textContent = currency(total);
                    }

                    items.forEach(function (check) {
                        const row = check.closest('.rounded-lg');
                        const qty = row.querySelector('.food-qty');

                        check.addEventListener('change', function () {
                            qty.disabled = !check.checked;
                            if (!check.checked) {
                                qty.value = '1';
                            }
                            recalc();
                        });

                        qty.addEventListener('change', recalc);
                    });

                    recalc();

                    const form = document.querySelector('form[action$="/reservation"]');
                    if (form) {
                        form.addEventListener('submit', function (e) {
                            const anyChecked = Array.from(items).some(function (check) { return check.checked; });
                            if (form.dataset.foodRequired && !anyChecked) {
                                e.preventDefault();
                                alert('Please select at least one dish to pre-order.');
                            }
                        });
                    }
                    recalc();
                });
            </script>
        @endpush
    @endif
@endsection