@extends('layouts.master')

@section('title', 'Share Your Feedback — Le Miting')

@section('content')
    <section class="py-12" aria-label="Feedback form">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-brand-800">Tell Us How We Did</h1>
                <p class="mt-2 text-text-muted">
                    Thank you for visiting Le Miting ({{ $transactionLabel }}). Your feedback helps us serve you better.
                </p>
            </div>

            <form method="POST" action="{{ $storeUrl }}" class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8">
                @csrf

                <div class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        @foreach (['food_rating' => 'Food', 'service_rating' => 'Service', 'experience_rating' => 'Overall experience'] as $field => $label)
                            <div>
                                <label for="{{ $field }}" class="text-sm font-semibold text-text">{{ $label }}</label>
                                <select
                                    id="{{ $field }}"
                                    name="{{ $field }}"
                                    class="mt-2 block w-full rounded-md border border-brand-200 bg-white px-3 py-2 text-text shadow-sm focus:border-primary focus:ring-primary"
                                >
                                    <option value="">Rate 1–5</option>
                                    @for ($star = 1; $star <= 5; $star++)
                                        <option value="{{ $star }}" @selected(old($field) == $star)>★ {{ $star }}</option>
                                    @endfor
                                </select>
                                @error($field)
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <label for="comment" class="text-sm font-semibold text-text">Anything else you would like to share? (optional)</label>
                        <textarea
                            id="comment"
                            name="comment"
                            rows="4"
                            class="mt-2 block w-full rounded-md border border-brand-200 bg-white px-3 py-2 text-text shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Tell us what you loved, or what we could improve…"
                        >{{ old('comment') }}</textarea>
                        @error('comment')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-8 inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Submit Feedback
                </button>
            </form>
        </div>
    </section>
@endsection