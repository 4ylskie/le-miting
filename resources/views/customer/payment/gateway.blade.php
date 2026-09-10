@extends('layouts.master')

@php
    $paymentTarget = $payment->reservation ?? $payment->order;
@endphp

@section('title', 'Payment Gateway — Le Miting')

@section('content')
    <section class="py-12" aria-label="Payment gateway">
        <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
                <div class="bg-brand-950 px-6 py-5 text-white">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold uppercase tracking-widest text-brand-300">FIUU Secure Payment</p>
                        <span class="rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-medium text-green-300" aria-hidden="true">🔒</span>
                    </div>
                    <p class="mt-1 text-xs text-brand-200">Simulated payment gateway — development mode</p>
                </div>

                <div class="p-6">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-text-muted">Merchant</dt>
                            <dd class="font-medium text-text">Le Miting</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-text-muted">Reference</dt>
                            <dd class="font-medium text-text">{{ $payment->provider_reference }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-text-muted">Description</dt>
                            <dd class="text-right font-medium text-text">
                                {{ $payment->reservation ? 'Reservation pre-order' : 'Food order' }}
                            </dd>
                        </div>
                        <div class="flex justify-between border-t border-brand-100 pt-3">
                            <dt class="font-semibold text-text">Amount due</dt>
                            <dd class="text-xl font-bold text-primary">RM {{ $payment->amount }}</dd>
                        </div>
                    </dl>

                    @if ($payment->reservation)
                        <p class="mt-4 rounded-md bg-brand-50 px-4 py-3 text-xs text-brand-800">
                            Your table is held for <strong>{{ $holdMinutes }} minutes</strong> while you complete payment.
                            The reservation is confirmed only after payment is verified.
                        </p>
                    @endif

                    <div class="mt-6 grid grid-cols-1 gap-3">
                        <form method="POST" action="{{ route('payment.callback') }}">
                            @csrf
                            <input type="hidden" name="reference" value="{{ $payment->provider_reference }}">
                            <input type="hidden" name="amount" value="{{ $amount }}">
                            <input type="hidden" name="status" value="paid">
                            <input type="hidden" name="currency" value="MYR">
                            <input type="hidden" name="signature" value="{{ $paySignature }}">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Simulate Successful Payment
                            </button>
                        </form>

                        <form method="POST" action="{{ route('payment.callback') }}">
                            @csrf
                            <input type="hidden" name="reference" value="{{ $payment->provider_reference }}">
                            <input type="hidden" name="amount" value="{{ $amount }}">
                            <input type="hidden" name="status" value="failed">
                            <input type="hidden" name="currency" value="MYR">
                            <input type="hidden" name="signature" value="{{ $failSignature }}">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-md border border-brand-200 bg-white px-6 py-3 text-base font-semibold text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-300"
                            >
                                Simulate Failed Payment
                            </button>
                        </form>
                    </div>

                    <p class="mt-4 text-center text-xs text-text-muted">
                        Payment is only confirmed after the server-to-server callback verifies successfully.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection