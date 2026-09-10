@extends('layouts.master')

@section('title', 'Contact Us — Le Miting')

@section('content')
    <section class="bg-brand-950 text-white py-14" aria-label="Contact">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold md:text-4xl">Contact Us</h1>
            <p class="mt-3 max-w-2xl text-brand-100">
                Questions, feedback, or special requests — we would love to hear from you.
            </p>
        </div>
    </section>

    <section class="py-12" aria-label="Contact details and form">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-bold text-brand-800">Restaurant Information</h2>

                    <dl class="mt-6 space-y-6 text-sm">
                        <div>
                            <dt class="font-semibold text-text">Address</dt>
                            <dd class="mt-1 text-text-muted">
                                No. 12, Jalan Melaka Raya<br>
                                75000 Melaka, Malaysia
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-text">Phone</dt>
                            <dd class="mt-1">
                                <a href="tel:+6061234567" class="text-primary hover:text-primary-dark">+60 6-123 4567</a>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-text">Email</dt>
                            <dd class="mt-1">
                                <a href="mailto:hello@lemiting.my" class="text-primary hover:text-primary-dark">hello@lemiting.my</a>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-text">Opening Hours</dt>
                            <dd class="mt-1 text-text-muted">
                                Mon – Fri: 11:00 AM – 10:00 PM<br>
                                Sat – Sun: 10:00 AM – 11:00 PM
                            </dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h2 class="text-2xl font-bold text-brand-800">Send an Enquiry</h2>

                    @if (session('success'))
                        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-5">
                        @csrf

                        <div>
                            <label for="name" class="block text-sm font-medium text-text">Name <span class="text-primary" aria-hidden="true">*</span></label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                autocomplete="name"
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('name') ? 'border-red-400' : '' }}"
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-text">Email <span class="text-primary" aria-hidden="true">*</span></label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="email"
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('email') ? 'border-red-400' : '' }}"
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-text">Message <span class="text-primary" aria-hidden="true">*</span></label>
                            <textarea
                                id="message"
                                name="message"
                                rows="6"
                                required
                                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('message') ? 'border-red-400' : '' }}"
                            >{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:w-auto"
                        >
                            Send Enquiry
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection