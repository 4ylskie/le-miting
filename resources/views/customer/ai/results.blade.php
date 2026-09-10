@extends('layouts.master')

@section('title', 'Your Recommendations — Le Miting')

@section('content')
    <section class="bg-accent text-white py-14" aria-label="Rasa AI recommendations">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold md:text-4xl">Your Recommendations</h1>
            <p class="mt-3 max-w-2xl text-accent-light">
                @if (collect($preferences)->filter()->isEmpty())
                    Here are some popular dishes for you to explore.
                @else
                    Based on your preferences, Rasa AI suggests these dishes.
                @endif
            </p>
        </div>
    </section>

    <section class="py-12" aria-label="Recommended dishes">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($recommendations->count() > 0)
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recommendations as $item)
                        <article class="flex flex-col rounded-xl border border-brand-100 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex h-40 items-center justify-center rounded-lg bg-brand-50 text-6xl" aria-hidden="true">
                                {{ $item->image_path ? '' : '🍽️' }}
                                @if ($item->image_path)
                                    <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover rounded-lg">
                                @endif
                            </div>
                            <div class="mt-4 flex flex-1 flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 class="font-semibold text-text">{{ $item->name }}</h2>
                                    <span class="shrink-0 font-bold text-primary">RM {{ number_format($item->price, 2) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-text-muted line-clamp-2">{{ $item->description }}</p>

                                @php
                                    $reasons = [];
                                    if ($preferences['spice_level'] ?? null && ($item->spice_level === $preferences['spice_level'])) {
                                        $reasons[] = 'matches your spice preference';
                                    }
                                    if ($preferences['protein_type'] ?? null && ($item->protein_type === $preferences['protein_type'])) {
                                        $reasons[] = 'matches your protein preference';
                                    }
                                    if ($preferences['dietary_type'] ?? null && ($item->dietary_type === $preferences['dietary_type'])) {
                                        $reasons[] = 'fits your dietary preference';
                                    }
                                    if ($preferences['flavor_profile'] ?? null && ($item->flavor_profile === $preferences['flavor_profile'])) {
                                        $reasons[] = 'suits your flavour preference';
                                    }
                                @endphp

                                @if ($reasons)
                                    <p class="mt-3 rounded-md bg-accent/10 px-3 py-2 text-xs text-accent">
                                        Recommended because it {{ collect($reasons)->implode(', ') }}.
                                    </p>
                                @endif

                                <div class="mt-4 flex gap-2">
                                    <form method="POST" action="{{ route('cart.add') }}" class="flex-1">
                                        @csrf
                                        <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                            Add to Cart
                                        </button>
                                    </form>
                                    <a
                                        href="{{ route('menu.show', $item) }}"
                                        class="rounded-md border border-brand-200 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-300"
                                    >
                                        View
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center">
                    <span class="text-5xl" aria-hidden="true">🤔</span>
                    <h2 class="mt-4 text-xl font-semibold text-text">No dishes match your preferences.</h2>
                    <p class="mt-2 text-text-muted">Try adjusting your selections or browse the full menu.</p>
                    <a href="{{ route('ai.recommendations') }}" class="mt-6 inline-flex items-center justify-center rounded-md bg-accent px-6 py-3 text-sm font-semibold text-white hover:bg-accent-light">
                        Adjust Preferences
                    </a>
                </div>
            @endif

            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('ai.recommendations') }}" class="inline-flex items-center justify-center rounded-md border border-brand-200 bg-white px-6 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                    Retake the Questionnaire
                </a>
                <a href="{{ route('menu.index') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-sm font-semibold text-white hover:bg-primary-dark">
                    Continue to Full Menu
                </a>
            </div>
        </div>
    </section>
@endsection