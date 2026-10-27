@extends('admin.layouts.app', ['title' => 'Reservations'])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.reservations.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="search" class="sr-only">Search reservations</label>
            <input
                id="search"
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search reservation number…"
                class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
            >
            <label for="status" class="sr-only">Filter by status</label>
            <select id="status" name="status" class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text">
                <option value="">All statuses</option>
                @foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($currentStatus === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Apply filters</button>
            @if ($search || $currentStatus)
                <a href="{{ route('admin.reservations.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">Clear</a>
            @endif
        </form>

        <p class="text-sm text-mineral">Search by reservation number to record arrival from the scheduled start time.</p>
    </div>

    @error('reservation_number')
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
    @enderror

    @if ($reservations->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            No reservations found.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Reservation number</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Customer</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Date &amp; time</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Guests</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Table</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Status</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach ($reservations as $reservation)
                        <tr class="hover:bg-brand-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-text">{{ $reservation->reservation_number }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $reservation->customer_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $reservation->reservation_date->format('d M Y') }} · {{ $reservation->reservation_time }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $reservation->guest_count }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">Table {{ $reservation->table->table_number }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $reservation->status === 'cancelled' ? 'bg-red-50 text-red-700' : ($reservation->status === 'completed' ? 'bg-green-50 text-green-800' : ($reservation->status === 'confirmed' ? 'bg-emerald-50 text-emerald-800' : 'bg-brand-100 text-brand-800')) }}">{{ ucfirst($reservation->status) }}</span>
                                <p class="mt-1 text-xs text-mineral">{{ $reservation->checked_in_at ? 'Checked in' : ($reservation->status === 'confirmed' ? 'Not checked in' : 'Arrival not recorded') }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-semibold text-brand-700 hover:text-brand-800">View <span class="sr-only">reservation {{ $reservation->reservation_number }}</span> →</a>
                                @if ($reservation->canCheckIn())
                                    <form method="POST" action="{{ route('admin.reservations.check-in') }}" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="reservation_number" value="{{ $reservation->reservation_number }}">
                                        <button type="submit" class="lm-button lm-button--secondary" aria-label="Check in reservation {{ $reservation->reservation_number }}">Check In</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reservations->links() }}
        </div>
    @endif
@endsection
