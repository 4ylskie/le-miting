@extends('admin.layouts.app', ['title' => 'Order '.$order->order_number])
@inject('businessTime', 'App\Services\BusinessTime')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.orders.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to orders</a>
    </div>

    @error('status')
        <p id="order-status-error" class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }}</p>
    @enderror

    @error('expected_status')
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }}</p>
    @enderror

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif

    @if (session('error'))
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ session('error') }}</p>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm lg:col-span-2" aria-labelledby="order-details-heading">
            <h2 id="order-details-heading" class="text-lg font-semibold text-text">Guest and order details</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-text-muted">Customer</dt>
                    <dd class="mt-1 text-text">{{ $order->customer_name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Email</dt>
                    <dd class="mt-1 text-text">{{ $order->customer_email }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Phone</dt>
                    <dd class="mt-1 text-text">{{ $order->customer_phone }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Order type</dt>
                    <dd class="mt-1 text-text">{{ ucfirst($order->order_type) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Placed</dt>
                    <dd class="mt-1 text-text">{{ $businessTime->local($order->created_at)->format('d M Y, g:i A') }} MYT</dd>
                </div>
                <div>
                    <dt class="font-medium text-text-muted">Notes</dt>
                    <dd class="mt-1 text-text">{{ $order->notes ?: '—' }}</dd>
                </div>
            </dl>

            <h3 class="mt-8 text-base font-semibold text-text">Ordered items</h3>
            <ul class="mt-3 divide-y divide-brand-100 rounded-lg border border-brand-100">
                @foreach ($order->orderItems as $item)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <span class="text-text">{{ $item->quantity }} × {{ $item->item_name }}</span>
                        <span class="shrink-0 font-medium text-text">RM {{ number_format((float) ($item->unit_price * $item->quantity), 2) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 flex justify-between border-t border-brand-100 pt-4">
                <span class="font-semibold text-text">Total</span>
                <span class="font-bold text-primary">RM {{ number_format((float) $order->total, 2) }}</span>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-xl border border-brand-100 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-lg font-semibold text-text">Status</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-sm font-medium text-text-muted">Order status</p>
                        <p class="mt-1">
                            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $order->status === 'cancelled' ? 'bg-red-50 text-red-700' : ($order->status === 'completed' ? 'bg-green-50 text-green-800' : 'bg-brand-100 text-brand-800') }}">{{ ucfirst($order->status) }}</span>
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-text-muted">Payment status</p>
                        <p class="mt-1">
                            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $order->payment && $order->payment->status === 'paid' ? 'bg-green-50 text-green-800' : ($order->payment && $order->payment->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-brand-100 text-brand-800') }}">
                                {{ $order->payment ? ucfirst($order->payment->status) : 'No payment recorded' }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-text-muted">Payment status is updated automatically by the payment gateway — it cannot be changed here.</p>
                    </div>
                </div>

                @if (! empty($nextStatuses))
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="mt-5 space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="expected_status" value="{{ $order->status }}">
                        <label for="status" class="block text-sm font-medium text-text">Move order to</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <select id="status" name="status" class="flex-1 rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text" @if ($errors->has('status')) aria-invalid="true" aria-describedby="order-status-error" @endif>
                                @foreach ($nextStatuses as $transitionStatus)
                                    <option value="{{ $transitionStatus }}" @selected(old('status') === $transitionStatus)>{{ ucfirst($transitionStatus) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Update</button>
                        </div>
                    </form>
                @else
                    <p class="mt-5 rounded-md bg-brand-50 px-4 py-3 text-sm text-brand-800">
                        This order is {{ $order->status }} and has no further status changes.
                    </p>
                @endif
            </section>
        </aside>
    </div>
@endsection
