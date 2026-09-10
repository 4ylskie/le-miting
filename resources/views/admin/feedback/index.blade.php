@extends('admin.layouts.app', ['title' => 'Feedback'])

@section('content')
    <div class="mb-6">
        <p class="text-sm text-text-muted">Review what guests said about their food, service, and experience.</p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Average food</p>
            <p class="mt-1 text-2xl font-bold text-text">{{ number_format($averages['food'], 1) }}<span class="text-sm font-normal text-text-muted"> / 5</span></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Average service</p>
            <p class="mt-1 text-2xl font-bold text-text">{{ number_format($averages['service'], 1) }}<span class="text-sm font-normal text-text-muted"> / 5</span></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-text-muted">Average experience</p>
            <p class="mt-1 text-2xl font-bold text-text">{{ number_format($averages['experience'], 1) }}<span class="text-sm font-normal text-text-muted"> / 5</span></p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.feedback.index') }}" class="mb-6 flex flex-wrap items-center gap-2">
        <label for="dimension" class="sr-only">Filter by rating type</label>
        <select id="dimension" name="dimension" class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text">
            <option value="">All rating types</option>
            <option value="food_rating" @selected($dimension === 'food_rating')>Food</option>
            <option value="service_rating" @selected($dimension === 'service_rating')>Service</option>
            <option value="experience_rating" @selected($dimension === 'experience_rating')>Experience</option>
        </select>
        <label for="rating" class="sr-only">Filter by rating</label>
        <select id="rating" name="rating" class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text">
            <option value="">Any rating</option>
            @foreach ([1, 2, 3, 4, 5] as $value)
                <option value="{{ $value }}" @selected($rating === $value)>{{ $value }} star{{ $value === 1 ? '' : 's' }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Filter</button>
        @if ($dimension || $rating)
            <a href="{{ route('admin.feedback.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Clear</a>
        @endif
    </form>

    @if ($items->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            No feedback found.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-text">Linked to</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Food</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Service</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Experience</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Comment</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Received</th>
                        <th class="px-5 py-3 text-left font-semibold text-text"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach ($items as $feedback)
                        <tr class="hover:bg-brand-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-text">
                                @if ($feedback->order)
                                    Order <a href="{{ route('admin.orders.show', $feedback->order) }}" class="font-mono text-brand-700 hover:underline">{{ $feedback->order->order_number }}</a>
                                @elseif ($feedback->reservation)
                                    Reservation <a href="{{ route('admin.reservations.show', $feedback->reservation) }}" class="font-mono text-brand-700 hover:underline">{{ $feedback->reservation->reservation_number }}</a>
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $feedback->food_rating }}★</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $feedback->service_rating }}★</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $feedback->experience_rating }}★</td>
                            <td class="max-w-xs truncate px-5 py-3 text-text-muted">{{ $feedback->comment ?: '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text-muted">{{ $feedback->created_at->format('Y-m-d H:i') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.feedback.show', $feedback) }}" class="font-semibold text-brand-700 hover:text-brand-800">View →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    @endif
@endsection