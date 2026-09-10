@extends('admin.layouts.app', ['title' => 'Dashboard'])

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Today's orders</p>
            <p class="mt-2 text-3xl font-bold text-brand-800">{{ $ordersToday }}</p>
        </div>

        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Today's reservations</p>
            <p class="mt-2 text-3xl font-bold text-brand-800">{{ $reservationsToday }}</p>
        </div>

        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Today's revenue</p>
            <p class="mt-2 text-3xl font-bold text-primary">RM {{ number_format((float) $revenueToday, 2) }}</p>
        </div>

        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Pending orders</p>
            <p class="mt-2 text-3xl font-bold text-text">{{ $pendingOrders }}</p>
        </div>

        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Pending reservations</p>
            <p class="mt-2 text-3xl font-bold text-text">{{ $pendingReservations }}</p>
        </div>

        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Available tables</p>
            <p class="mt-2 text-3xl font-bold text-accent">{{ $availableTables }}</p>
        </div>
    </div>

    <section class="mt-8" aria-labelledby="recent-feedback-heading">
        <div class="flex items-center justify-between">
            <h2 id="recent-feedback-heading" class="text-lg font-semibold text-text">Recent feedback</h2>
            <a href="{{ route('admin.feedback.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">
                View all
            </a>
        </div>

        @if ($recentFeedback->isEmpty())
            <p class="mt-4 rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
                No feedback yet. Guests can submit feedback after their order or reservation is completed.
            </p>
        @else
            <ul class="mt-4 divide-y divide-brand-100 rounded-xl border border-brand-100 bg-white shadow-sm">
                @foreach ($recentFeedback as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-text">
                                @if ($item->order)
                                    Order {{ $item->order->order_number }}
                                @else
                                    Reservation {{ $item->reservation->reservation_number }}
                                @endif
                            </p>
                            @if ($item->comment)
                                <p class="mt-1 truncate text-sm text-text-muted">“{{ $item->comment }}”</p>
                            @else
                                <p class="mt-1 text-sm text-text-muted">No comment left.</p>
                            @endif
                        </div>
                        <a href="{{ route('admin.feedback.show', $item) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">
                            View →
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection