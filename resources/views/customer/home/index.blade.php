@extends('layouts.master')

@section('title', 'Le Miting — Malaysian Restaurant')

@section('content')
    <section class="lm-welcome" aria-labelledby="home-heading">
        <figure class="lm-welcome__photograph">
            <img src="{{ asset('images/landingpg1.jpg') }}"
                alt="A dining table set beside a bamboo counter and open windows."
                width="6000" height="4000" loading="eager" fetchpriority="high">
        </figure>
        <div class="lm-container lm-welcome__content">
            <div class="lm-welcome__copy">
                <p class="lm-eyebrow">Traditional Malay dining</p>
                <h1 id="home-heading" class="lm-display">A place at<br>the Table</h1>
                <p class="lm-body max-w-md">Come together over traditional Malay food and a warm Malaysian welcome.</p>
                <div class="lm-actions">
                    <a href="{{ route('menu.index') }}" class="lm-button">Explore the Menu</a>
                    <a href="{{ route('reservation.table-food') }}" class="lm-button lm-button--secondary">Reserve Now</a>
                </div>
            </div>
        </div>
    </section>

    @if ($featuredItems->isNotEmpty())
        <section aria-labelledby="featured-heading">
            <header class="lm-container lm-section lm-stack items-start">
                <h2 id="featured-heading" class="lm-h2 lm-featured-heading">Featured Dishes</h2>
            </header>
            <div class="lm-featured-grid">
                @foreach ($featuredItems as $item)
                    <article @class(['lm-featured-dish', 'lm-featured-dish--photographic' => (bool) $item->image_path, 'lm-featured-dish--right' => $loop->even]) aria-labelledby="featured-dish-{{ $item->id }}">
                        @if ($item->image_path)
                            <figure class="lm-featured-dish__photograph">
                                <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}"
                                    width="1200" height="900" loading="lazy" decoding="async">
                            </figure>
                        @endif
                        <div class="lm-container lm-featured-dish__content">
                            <div class="lm-featured-dish__copy lm-stack">
                                <div class="flex flex-col gap-(--lm-space-sm) lg:flex-row lg:items-baseline lg:justify-between lg:gap-(--lm-space-md)">
                                    <h3 id="featured-dish-{{ $item->id }}" class="lm-h1 min-w-0 wrap-anywhere">{{ $item->name }}</h3>
                                    <p class="lm-body shrink-0 tabular-nums">RM {{ number_format($item->price, 2) }}</p>
                                </div>
                                @if ($item->description)
                                    <p class="lm-body max-w-md wrap-anywhere">{{ $item->description }}</p>
                                @endif
                                <x-menu-metadata :item="$item" />
                                <a href="{{ route('menu.show', $item) }}" class="lm-button lm-button--quiet self-start">
                                    View Dish<span class="sr-only">: {{ $item->name }}</span>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="lm-divider bg-ivory" aria-labelledby="home-rasa-heading">
        <div class="lm-container lm-section grid gap-(--lm-space-lg) md:grid-cols-2 md:gap-(--lm-space-xl)">
            <div class="lm-stack">
                <p class="lm-eyebrow">Rasa AI</p>
                <h2 id="home-rasa-heading" class="lm-h2 max-w-md">What are you<br>in the mood for?</h2>
            </div>
            <div class="lm-stack items-start md:self-end">
                <p class="lm-body lm-muted max-w-md">Choose a category, spice level, protein, dietary preference, or flavour. Rasa AI suggests dishes from the available menu.</p>
                <a href="{{ route('ai.recommendations') }}" class="lm-button lm-button--secondary">Get Rasa AI Recommendations</a>
            </div>
        </div>
    </section>

    <section class="lm-featured-dish lm-featured-dish--photographic" aria-labelledby="home-reserve-heading">
        <figure class="lm-featured-dish__photograph">
            <img src="{{ asset('images/reservelp.jpg') }}"
                alt="An open dining space with wooden tables, traditional furnishings, and greenery."
                width="2064" height="1548" loading="lazy" decoding="async">
        </figure>
        <div class="lm-container lm-featured-dish__content">
            <div class="lm-stack max-w-2xl">
                <p class="lm-eyebrow">Your next visit</p>
                <h2 id="home-reserve-heading" class="lm-h2">Make time for a meal.</h2>
                <p class="lm-body">Reserve a table, with the option to pre-order your food.</p>
                <a href="{{ route('reservation.table-food') }}" class="lm-button self-start border-ivory">Reserve Now</a>
            </div>
        </div>
    </section>
@endsection
