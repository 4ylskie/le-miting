@extends('admin.layouts.app', ['title' => 'Reports'])

@section('content')
    <div class="mb-6">
        <p class="text-sm text-text-muted">Sales and reservation reports — weekly and monthly, generated on demand as PDFs.</p>
    </div>

    @php
        $reports = [
            [
                'type' => 'Sales',
                'period' => 'Weekly',
                'window' => 'Last 7 days',
                'detail' => 'Total orders, revenue, completed and cancelled orders, popular menu items.',
                'route' => 'admin.reports.sales-weekly',
            ],
            [
                'type' => 'Sales',
                'period' => 'Monthly',
                'window' => 'Last 30 days',
                'detail' => 'Total orders, revenue, completed and cancelled orders, popular menu items.',
                'route' => 'admin.reports.sales-monthly',
            ],
            [
                'type' => 'Reservations',
                'period' => 'Weekly',
                'window' => 'Last 7 days',
                'detail' => 'Total reservations, completed and cancelled, guest count, popular reservation times.',
                'route' => 'admin.reports.reservations-weekly',
            ],
            [
                'type' => 'Reservations',
                'period' => 'Monthly',
                'window' => 'Last 30 days',
                'detail' => 'Total reservations, completed and cancelled, guest count, popular reservation times.',
                'route' => 'admin.reports.reservations-monthly',
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $report)
            <div class="flex flex-col justify-between rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
                <div>
                    <h2 class="text-base font-semibold text-text">{{ $report['period'] }} {{ $report['type'] }}</h2>
                    <p class="mt-1 text-xs font-medium text-primary">{{ $report['window'] }}, ending today</p>
                    <p class="mt-3 text-sm leading-relaxed text-text-muted">{{ $report['detail'] }}</p>
                </div>
                <a
                    href="{{ route($report['route']) }}"
                    data-report-download
                    data-report-name="{{ $report['period'] }} {{ $report['type'] }}"
                    class="lm-admin-target mt-6 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Download {{ $report['period'] }} {{ $report['type'] }} PDF
                </a>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-sm text-text-muted" role="status" aria-live="polite" data-report-download-status hidden></p>
    <p class="mt-6 text-xs text-text-muted">
        Reports are generated on demand. Your browser handles the download; this page cannot confirm completion or detect a failed download.
    </p>
@endsection
