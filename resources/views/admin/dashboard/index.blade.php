@extends('admin.layouts.app', ['title' => 'Dashboard'])

@section('content')
    <section
        data-dashboard-refresh
        data-dashboard-summary-url="{{ route('admin.dashboard.summary') }}"
        aria-label="Dashboard summary"
    >
        <p data-dashboard-refresh-status role="status" aria-live="polite" hidden class="mb-2 text-xs text-text-muted"></p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-text-muted">Today's orders</p>
                <p data-dashboard-metric="ordersToday" class="mt-2 text-3xl font-bold text-brand-800">{{ $ordersToday }}</p>
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-text-muted">Today's reservations</p>
                <p data-dashboard-metric="reservationsToday" class="mt-2 text-3xl font-bold text-brand-800">{{ $reservationsToday }}</p>
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-text-muted">Today's revenue</p>
                <p data-dashboard-metric="revenueToday" class="mt-2 text-3xl font-bold text-primary">RM {{ number_format((float) $revenueToday, 2) }}</p>
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-text-muted">Pending orders</p>
                <p data-dashboard-metric="pendingOrders" class="mt-2 text-3xl font-bold text-text">{{ $pendingOrders }}</p>
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-text-muted">Pending reservations</p>
                <p data-dashboard-metric="pendingReservations" class="mt-2 text-3xl font-bold text-text">{{ $pendingReservations }}</p>
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-text-muted">Available tables</p>
                <p data-dashboard-metric="availableTables" class="mt-2 text-3xl font-bold text-accent">{{ $availableTables }}</p>
            </div>
        </div>
    </section>
@endsection
