@extends('layouts.master')

@section('title', 'Payment — Le Miting')

@section('content')
    <section class="py-12" aria-label="Payment">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-brand-100 bg-white p-6 text-center shadow-sm md:p-8">
                <h1 class="text-2xl font-bold text-brand-800">
                    {{ $payment->reservation ? 'Complete your reservation payment' : 'Complete your order payment' }}
                </h1>
                <p class="mt-3 text-sm text-text-muted">
                    Amount: <strong class="text-primary">RM {{ number_format($payment->amount, 2) }}</strong>
                </p>
                <x-stripe-payment-info />
            </div>
        </div>
    </section>
@endsection
