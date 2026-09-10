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
            <select id="status" name="status" onchange="this.form.submit()" class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text">
                <option value="">All statuses</option>
                @foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($currentStatus === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Search</button>
            @if ($search || $currentStatus)
                <a href="{{ route('admin.reservations.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Clear</a>
            @endif
        </form>
    </div>

    @if ($reservations->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            No reservations found.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-text">Reservation number</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Customer</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Date &amp; time</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Guests</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Table</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Status</th>
                        <th class="px-5 py-3 text-left font-semibold text-text"></th>
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
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-semibold text-brand-700 hover:text-brand-800">View →</a>
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