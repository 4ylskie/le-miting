@extends('admin.layouts.app', ['title' => 'Feedback Detail'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.feedback.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to feedback</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-lg font-semibold text-text">Ratings</h2>
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between border-b border-brand-100 pb-3">
                    <span class="text-sm font-medium text-text">Food</span>
                    <span class="text-lg font-semibold text-text">{{ $feedback->food_rating }} / 5</span>
                </div>
                <div class="flex items-center justify-between border-b border-brand-100 pb-3">
                    <span class="text-sm font-medium text-text">Service</span>
                    <span class="text-lg font-semibold text-text">{{ $feedback->service_rating }} / 5</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-text">Experience</span>
                    <span class="text-lg font-semibold text-text">{{ $feedback->experience_rating }} / 5</span>
                </div>
            </div>

            @if ($feedback->comment)
                <h3 class="mt-6 text-sm font-semibold text-text">Comment</h3>
                <p class="mt-2 rounded-lg bg-brand-50 px-4 py-3 text-sm leading-relaxed text-text">{{ $feedback->comment }}</p>
            @else
                <p class="mt-6 rounded-lg bg-brand-50 px-4 py-3 text-sm text-text-muted">No written comment.</p>
            @endif

            <p class="mt-6 text-xs text-text-muted">Received {{ $feedback->created_at->format('F j, Y \a\t g:i A') }}</p>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-text">Linked order</h2>
                @if ($feedback->order)
                    <p class="mt-2">
                        <a href="{{ route('admin.orders.show', $feedback->order) }}" class="font-mono text-brand-700 hover:underline">{{ $feedback->order->order_number }}</a>
                    </p>
                    <p class="mt-1 text-sm text-text-muted">
                        Status: <span class="font-medium text-text">{{ ucfirst($feedback->order->status) }}</span>
                    </p>
                    <p class="mt-1 text-sm text-text-muted">Total: RM {{ number_format((float) $feedback->order->total_amount, 2) }}</p>
                @else
                    <p class="mt-2 text-sm text-text-muted">Not linked to an order.</p>
                @endif
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-text">Linked reservation</h2>
                @if ($feedback->reservation)
                    <p class="mt-2">
                        <a href="{{ route('admin.reservations.show', $feedback->reservation) }}" class="font-mono text-brand-700 hover:underline">{{ $feedback->reservation->reservation_number }}</a>
                    </p>
                    <p class="mt-1 text-sm text-text-muted">
                        Status: <span class="font-medium text-text">{{ ucfirst($feedback->reservation->status) }}</span>
                    </p>
                    <p class="mt-1 text-sm text-text-muted">
                        Date: {{ \Carbon\Carbon::parse($feedback->reservation->reservation_date)->format('M j, Y') }} at {{ $feedback->reservation->reservation_time }}
                    </p>
                @else
                    <p class="mt-2 text-sm text-text-muted">Not linked to a reservation.</p>
                @endif
            </div>
        </div>
    </div>
@endsection