@extends('layouts.master')

@section('title', 'Le Miting — Authentic Malaysian Restaurant')

@section('content')
    {{-- Hero Section --}}
    <section class="relative overflow-hidden bg-brand-950 text-white" aria-label="Welcome to Le Miting">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 md:py-32">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-300">Authentic Malaysian Cuisine</p>
                <h1 class="mt-4 text-4xl font-bold tracking-tight md:text-6xl">
                    Taste the Heart<br>of <span class="text-brand-300">Kampung</span>
                </h1>
                <p class="mt-6 text-lg text-brand-100">
                    From fragrant nasi lemak to sizzling satay, Le Miting brings home-style Malaysian cooking
                    to your table. Order online, reserve a table, and let us take care of the rest.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a
                        href="{{ route('menu.index') }}"
                        class="inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2 focus:ring-offset-brand-950"
                    >
                        Order Now
                    </a>
                    <a
                        href="{{ route('reservation.create') }}"
                        class="inline-flex items-center justify-center rounded-md border border-brand-300/40 px-6 py-3 text-base font-semibold text-brand-100 hover:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-300"
                    >
                        Reserve a Table
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Restaurant Introduction --}}
    <section class="py-16 md:py-20" aria-label="About Le Miting">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="md:grid md:grid-cols-2 md:gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-brand-800">Malaysian Flavours, Kampung Heart</h2>
                    <p class="mt-4 text-base leading-relaxed text-text-muted">
                        Le Miting is a family-run restaurant celebrating the rich, diverse flavours of Malaysian
                        cuisine. Every dish is prepared fresh with recipes passed down through generations —
                        from the aromatic spices of Malay cooking to the bold wok-hei of Chinese hawker fare.
                    </p>
                    <p class="mt-4 text-base leading-relaxed text-text-muted">
                        We believe authentic Malaysian food should be accessible to everyone. That's why we've
                        made it easy to browse our menu, get personalised recommendations from Rasa AI, and
                        reserve your table — all without needing an account.
                    </p>
                </div>
                <div class="mt-8 md:mt-0 rounded-2xl bg-brand-100 p-8 md:p-12 flex items-center justify-center">
                </div>
            </div>
        </div>
    </section>

    {{-- Rasa AI --}}
    <section class="bg-accent py-16 md:py-20" aria-label="Rasa AI recommendations">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-widest text-accent-light">Rasa AI</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">Not Sure What to Order?</h2>
                    <p class="mt-4 text-base leading-relaxed text-white/90">
                        Tell us what you are hungry for — a category, spice level, protein, dietary need, or flavour
                        profile — and Rasa AI will hand-pick dishes you will love. Quick, optional, and no account needed.
                    </p>
                </div>
                <a
                    href="{{ route('ai.recommendations') }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-md bg-white px-6 py-3 text-base font-semibold text-brand-800 shadow-sm hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-accent"
                >
                    Get Rasa AI Recommendations
                </a>
            </div>
        </div>
    </section>

    {{-- Featured Dishes --}}
    <section class="py-16 md:py-20 bg-surface-dark" aria-label="Featured dishes">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-primary">Chef's Selection</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-brand-800">Featured Dishes</h2>
                </div>
                <a href="{{ route('menu.index') }}" class="mt-4 md:mt-0 text-sm font-semibold text-primary hover:text-primary-dark">
                    Browse Full Menu →
                </a>
            </div>

            @if ($featuredItems->count() > 0)
                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featuredItems as $item)
                        <div class="rounded-xl border border-brand-100 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex h-40 items-center justify-center rounded-lg bg-brand-50 text-6xl" aria-hidden="true">
                                {{ $item->image_path ? '' : '🍽️' }}
                            </div>
                            <div class="mt-4 flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-text">{{ $item->name }}</h3>
                                    <p class="mt-1 text-sm text-text-muted line-clamp-2">{{ $item->description }}</p>
                                </div>
                                <span class="shrink-0 font-bold text-primary">RM {{ number_format($item->price, 2) }}</span>
                            </div>
                            <a
                                href="{{ route('menu.show', $item) }}"
                                class="mt-4 inline-flex w-full items-center justify-center rounded-md border border-brand-200 bg-white px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50"
                            >
                                View Dish
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-8 text-text-muted">Featured dishes coming soon. Browse the full menu now.</p>
            @endif
        </div>
    </section>

    {{-- Restaurant Experience --}}
    <section class="py-16 md:py-20" aria-label="The Le Miting experience">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight text-brand-800">The Le Miting Experience</h2>
            <p class="mt-4 text-center text-text-muted max-w-2xl mx-auto">
                More than a meal — it's a celebration of Malaysian hospitality.
            </p>
            <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                @php
                    $experiences = [
                        ['icon' => '🥘', 'title' => 'Authentic Flavours', 'desc' => 'Recipes passed down through generations, cooked with love and fresh ingredients.'],
                        ['icon' => '🏡', 'title' => 'Kampung Heritage', 'desc' => 'A warm, homely atmosphere inspired by the traditional Malaysian village.'],
                        ['icon' => '🤝', 'title' => 'Friendly Service', 'desc' => 'Hospitality is our tradition. You are not a customer — you are a guest.'],
                    ];
                @endphp
                @foreach ($experiences as $exp)
                    <div class="rounded-xl bg-brand-50 p-8 text-center">
                        <span class="text-4xl" aria-hidden="true">{{ $exp['icon'] }}</span>
                        <h3 class="mt-4 text-lg font-semibold text-text">{{ $exp['title'] }}</h3>
                        <p class="mt-2 text-sm text-text-muted">{{ $exp['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="py-16 md:py-20 bg-surface-dark" aria-label="What our guests say">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight text-brand-800">What Our Guests Say</h2>
            <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                @php
                    $testimonials = [
                        ['name' => 'Aina R.', 'text' => 'The nasi lemak here is the best I have had outside of my grandmother\'s kitchen. Booking a table online was so easy!'],
                        ['name' => 'John T.', 'text' => 'Used Rasa AI to find dishes I would love — it nailed my taste perfectly. The sambal udang is incredible.'],
                        ['name' => 'Mei L.', 'text' => 'Reserved a table and pre-ordered food for a family dinner. Everything was ready when we arrived. Wonderful service.'],
                    ];
                @endphp
                @foreach ($testimonials as $t)
                    <figure class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex text-brand-400" aria-label="5 star rating">
                            ★★★★★
                        </div>
                        <blockquote class="mt-3 text-sm leading-relaxed text-text-muted">
                            "{{ $t['text'] }}"
                        </blockquote>
                        <figcaption class="mt-4 text-sm font-semibold text-text">— {{ $t['name'] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Order + Reserve CTA --}}
    <section class="bg-brand-800 py-16 text-center" aria-label="Start your order or reservation">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white">Ready to Eat?</h2>
            <p class="mt-4 text-brand-100">
                Order your favourites for pickup or dine-in, or reserve a table for your next gathering.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row justify-center">
                <a
                    href="{{ route('menu.index') }}"
                    class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-base font-semibold text-brand-800 hover:bg-brand-50"
                >
                    Order Now
                </a>
                <a
                    href="{{ route('reservation.create') }}"
                    class="inline-flex items-center justify-center rounded-md border border-white/30 px-6 py-3 text-base font-semibold text-white hover:bg-brand-700"
                >
                    Reserve a Table
                </a>
            </div>
        </div>
    </section>
@endsection
