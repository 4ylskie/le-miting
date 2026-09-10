@extends('admin.layouts.app', ['title' => 'Reports'])

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-text">Reports</h1>
        <p class="mt-1 text-sm text-text-muted">Sales, reservation, and feedback reports — weekly and monthly, generated on demand as PDFs.</p>
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
            [
                'type' => 'Feedback',
                'period' => 'Weekly',
                'window' => 'Last 7 days',
                'detail' => 'Average food, service, and experience ratings with star distribution.',
                'route' => 'admin.reports.feedback-weekly',
            ],
            [
                'type' => 'Feedback',
                'period' => 'Monthly',
                'window' => 'Last 30 days',
                'detail' => 'Average food, service, and experience ratings with star distribution.',
                'route' => 'admin.reports.feedback-monthly',
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
                    class="mt-6 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Download PDF
                </a>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-xs text-text-muted">
        Reports are generated on demand and downloaded as PDFs. If generation fails, an error is shown and nothing is downloaded.
    </p>
@endsection