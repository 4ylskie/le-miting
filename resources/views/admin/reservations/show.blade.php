@extends('admin.layouts.app', ['title' => 'Reservation '.$reservation->reservation_number])
@inject('businessTime', 'App\Services\BusinessTime')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.reservations.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to reservations</a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif

    @if (session('error'))
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ session('error') }}</p>
    @endif

    @error('table_id')
        <p id="reservation-table-error" class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }}</p>
    @enderror

    @error('status')
        <p id="reservation-status-error" class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }}</p>
    @enderror

    @error('expected_status')
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }}</p>
    @enderror

    @error('expected_table_id')
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }} Refresh the page before trying again.</p>
    @enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm lg:col-span-2" aria-labelledby="reservation-details-heading">
            <h2 id="reservation-details-heading" class="text-lg font-semibold text-text">Guest and reservation details</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-text-muted">Customer</dt>
                    <dd class="mt-1 text-text">{{ $reservation->customer_name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Email</dt>
                    <dd class="mt-1 text-text">{{ $reservation->customer_email }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Phone</dt>
                    <dd class="mt-1 text-text">{{ $reservation->customer_phone }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Reservation type</dt>
                    <dd class="mt-1 text-text">{{ str_replace('_', ' ', ucfirst($reservation->reservation_type)) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Date &amp; time</dt>
                    <dd class="mt-1 text-text">{{ $reservation->reservation_date->format('d M Y') }} · {{ $reservation->reservation_time }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Guests</dt>
                    <dd class="mt-1 text-text">{{ $reservation->guest_count }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Assigned table</dt>
                    <dd class="mt-1 text-text">Table {{ $reservation->table->table_number }} (seats {{ $reservation->table->capacity }})</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Notes</dt>
                    <dd class="mt-1 text-text">{{ $reservation->special_request ?: '—' }}</dd>
                </div>
            </dl>

            @if ($reservation->reservationFoods->isNotEmpty())
                <h3 class="mt-8 text-base font-semibold text-text">Pre-ordered food</h3>
                <ul class="mt-3 divide-y divide-brand-100 rounded-lg border border-brand-100">
                    @foreach ($reservation->reservationFoods as $food)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="text-text">{{ $food->quantity }} × {{ $food->item_name }}</span>
                            <span class="shrink-0 font-medium text-text">RM {{ number_format((float) ($food->unit_price * $food->quantity), 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex justify-between border-t border-brand-100 pt-4">
                    <span class="font-semibold text-text">Food total</span>
                    <span class="font-bold text-primary">RM {{ number_format((float) $reservation->total, 2) }}</span>
                </div>
            @else
                <p class="mt-8 text-sm text-text-muted">No food pre-ordered for this reservation.</p>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm" aria-labelledby="reservation-status-heading">
                <h2 id="reservation-status-heading" class="text-lg font-semibold text-text">Status</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-sm font-medium text-text-muted">Reservation status</p>
                        <p class="mt-1">
                            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $reservation->status === 'cancelled' ? 'bg-red-50 text-red-700' : ($reservation->status === 'completed' ? 'bg-green-50 text-green-800' : ($reservation->status === 'confirmed' ? 'bg-emerald-50 text-emerald-800' : 'bg-brand-100 text-brand-800')) }}">{{ ucfirst($reservation->status) }}</span>
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-text-muted">Arrival</p>
                        <p class="mt-1 text-sm text-text">
                            @if ($reservation->checked_in_at)
                                Checked in {{ $businessTime->local($reservation->checked_in_at)->format('d M Y, g:i A') }} (Malaysia time)
                            @else
                                {{ $reservation->status === 'confirmed' ? 'Not checked in' : 'Arrival not recorded' }}
                            @endif
                        </p>
                    </div>

                    @if ($reservation->payment)
                        <div>
                            <p class="text-sm font-medium text-text-muted">Payment status</p>
                            <p class="mt-1">
                                <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $reservation->payment->status === 'paid' ? 'bg-green-50 text-green-800' : ($reservation->payment->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-brand-100 text-brand-800') }}">{{ ucfirst($reservation->payment->status) }}</span>
                            </p>
                            <p class="mt-1 text-xs text-text-muted">Payment status is managed by the payment gateway and is read-only here.</p>
                        </div>
                    @endif
                </div>

                @error('reservation_number')
                    <p class="mt-3 text-sm text-danger" role="alert">{{ $message }}</p>
                @enderror

                @if ($reservation->canCheckIn())
                    <form method="POST" action="{{ route('admin.reservations.check-in') }}" class="mt-5">
                        @csrf
                        <input type="hidden" name="reservation_number" value="{{ $reservation->reservation_number }}">
                        <button type="submit" class="lm-button">Check In</button>
                    </form>
                @elseif ($reservation->status === 'confirmed' && $reservation->checked_in_at === null)
                    <p class="mt-3 text-sm text-mineral">Check-in opens at the scheduled start time in Malaysia.</p>
                @endif

                @if (! empty($nextStatuses))
                    <form method="POST" action="{{ route('admin.reservations.update-status', $reservation) }}" class="mt-5 space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="expected_status" value="{{ $reservation->status }}">
                        <label for="status" class="block text-sm font-medium text-text">Move reservation to</label>
                        <div class="flex gap-2">
                            <select id="status" name="status" class="flex-1 rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text" @if ($errors->has('status')) aria-invalid="true" aria-describedby="reservation-status-error" @endif>
                                @foreach ($nextStatuses as $transitionStatus)
                                    <option value="{{ $transitionStatus }}" @selected(old('status') === $transitionStatus)>{{ ucfirst($transitionStatus) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">{{ $reservation->status === 'confirmed' && $reservation->checked_in_at ? 'Mark Completed' : 'Update' }}</button>
                        </div>
                    </form>
                @else
                    <p class="mt-5 rounded-md bg-brand-50 px-4 py-3 text-sm text-brand-800">
                        This reservation is {{ $reservation->status }} and has no further status changes.
                    </p>
                @endif
            </section>

            @if (in_array($reservation->status, ['pending', 'confirmed'], true) && $reservation->checked_in_at === null)
                <section class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm" aria-labelledby="reassign-heading">
                    <h2 id="reassign-heading" class="text-lg font-semibold text-text">Reassign table</h2>

                    @if ($availableTables->isEmpty())
                        <p class="mt-3 rounded-md bg-brand-50 px-4 py-3 text-sm text-brand-800">
                            No other available tables can seat {{ $reservation->guest_count }} guests at {{ $reservation->reservation_time }}.
                        </p>
                    @else
                        <form method="POST" action="{{ route('admin.reservations.update-table', $reservation) }}" class="mt-3 space-y-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="expected_table_id" value="{{ $reservation->table_id }}">
                            <input type="hidden" name="expected_status" value="{{ $reservation->status }}">
                            <label for="table_id" class="block text-sm font-medium text-text">Move to</label>
                            <div class="flex gap-2">
                                <select id="table_id" name="table_id" class="flex-1 rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text" @if ($errors->has('table_id')) aria-invalid="true" aria-describedby="reservation-table-error" @endif>
                                    @foreach ($availableTables as $table)
                                        <option value="{{ $table->id }}" @selected((string) old('table_id') === (string) $table->id)>Table {{ $table->table_number }} (seats {{ $table->capacity }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Assign</button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif
        </aside>
    </div>
@endsection
