@extends('admin.layouts.app', ['title' => 'Orders'])
@inject('businessTime', 'App\Services\BusinessTime')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="status" class="text-sm font-medium text-text-muted">Filter by status</label>
            <select id="status" name="status" class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected($currentStatus === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Apply filters</button>
        </form>
    </div>

    @if ($orders->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            @if ($currentStatus)
                No orders with status “{{ $currentStatus }}” right now.
            @else
                No orders yet. Orders appear here when guests check out.
            @endif
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Order number</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Customer</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Type</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Total</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Order status</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Payment</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Placed</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach ($orders as $order)
                        <tr class="hover:bg-brand-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-text">{{ $order->order_number }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $order->customer_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ ucfirst($order->order_type) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">RM {{ number_format((float) $order->total, 2) }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $order->status === 'cancelled' ? 'bg-red-50 text-red-700' : ($order->status === 'completed' ? 'bg-green-50 text-green-800' : 'bg-brand-100 text-brand-800') }}">{{ ucfirst($order->status) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3">
                                @if ($order->payment)
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $order->payment->status === 'paid' ? 'bg-green-50 text-green-800' : ($order->payment->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-brand-100 text-brand-800') }}">{{ ucfirst($order->payment->status) }}</span>
                                @else
                                    <span class="text-text-muted">No payment</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-text-muted">{{ $businessTime->local($order->created_at)->format('d M Y, g:i A') }} MYT</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-brand-700 hover:text-brand-800">View <span class="sr-only">order {{ $order->order_number }}</span> →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
