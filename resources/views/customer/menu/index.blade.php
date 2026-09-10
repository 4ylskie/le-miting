@extends('layouts.master')

@section('title', 'Menu — Le Miting')

@section('content')
    <section class="bg-brand-950 text-white py-14" aria-label="Menu">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold md:text-4xl">Our Menu</h1>
            <p class="mt-3 max-w-2xl text-brand-100">
                Authentic Malaysian dishes, prepared fresh. Search, filter, and add your favourites to the cart.
            </p>
        </div>
    </section>

    <section class="py-10" aria-label="Menu search and filters">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Menu categories">
                    <a
                        href="{{ route('menu.index') }}"
                        class="rounded-full px-4 py-2 text-sm font-medium {{ ! request()->filled('category') ? 'bg-primary text-white' : 'border border-brand-200 bg-white text-brand-700 hover:bg-brand-50' }}"
                        @if (! request()->filled('category')) aria-current="page" @endif
                    >
                        All
                    </a>
                    @foreach ($categories as $category)
                        <a
                            href="{{ route('menu.index', ['category' => $category->slug] + request()->except(['category', 'page'])) }}"
                            class="rounded-full px-4 py-2 text-sm font-medium {{ request()->input('category') === $category->slug ? 'bg-primary text-white' : 'border border-brand-200 bg-white text-brand-700 hover:bg-brand-50' }}"
                            @if (request()->input('category') === $category->slug) aria-current="page" @endif
                        >
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('menu.index') }}" class="flex items-center gap-2" role="search">
                    <label for="menu-search" class="sr-only">Search menu</label>
                    <input
                        id="menu-search"
                        type="search"
                        name="q"
                        value="{{ request()->input('q') }}"
                        placeholder="Search dishes..."
                        class="w-full sm:w-64 rounded-md border border-brand-200 bg-white px-4 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                        Search
                    </button>
                </form>
            </div>

            <div class="mt-6 space-y-4" aria-label="Menu filters">
                @php
                    $filterGroups = [
                        'spice' => ['label' => 'Spice Level', 'options' => $filters['spiceLevels']],
                        'protein' => ['label' => 'Protein', 'options' => $filters['proteinTypes']],
                        'dietary' => ['label' => 'Dietary', 'options' => $filters['dietaryTypes']],
                        'flavor' => ['label' => 'Flavour', 'options' => $filters['flavorProfiles']],
                    ];
                @endphp

                @foreach ($filterGroups as $key => $group)
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <span class="text-xs font-semibold uppercase tracking-wide text-text-muted sm:w-32 sm:shrink-0">{{ $group['label'] }}:</span>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($group['options'] as $option)
                                @php
                                    $isActive = request()->input($key) === $option;
                                    $query = request()->except([$key, 'page']);
                                    $href = $isActive
                                        ? route('menu.index', $query)
                                        : route('menu.index', $query + [$key => $option]);
                                @endphp
                                <a
                                    href="{{ $href }}"
                                    class="rounded-full px-3 py-1 text-xs font-medium {{ $isActive ? 'bg-accent text-white' : 'border border-brand-200 bg-white text-text-muted hover:bg-brand-50' }}"
                                    @if ($isActive) aria-current="true" @endif
                                >
                                    {{ $option }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if (request()->except('page'))
                    <a href="{{ route('menu.index') }}" class="inline-block text-sm font-semibold text-primary hover:text-primary-dark">
                        Clear all filters
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="pb-16" aria-label="Menu items">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($items->count() > 0)
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
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
                                <div class="mt-3 flex flex-wrap gap-1.5 text-xs text-text-muted" aria-label="Item attributes">
                                    @if ($item->spice_level)
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5">{{ $item->spice_level }}</span>
                                    @endif
                                    @if ($item->dietary_type)
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5">{{ $item->dietary_type }}</span>
                                    @endif
                                    @if (! $item->is_available)
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-red-700">Sold out</span>
                                    @endif
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <form method="POST" action="{{ route('cart.add') }}" class="flex-1">
                                        @csrf
                                        <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button
                                            type="submit"
                                            class="w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                            @if (! $item->is_available) disabled class="w-full rounded-md bg-text-muted px-4 py-2 text-sm font-semibold text-white cursor-not-allowed" @endif
                                        >
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

                <div class="mt-10">
                    {{ $items->links() }}
                </div>
            @else
                <div class="py-16 text-center">
                    <span class="text-5xl" aria-hidden="true">🍽️</span>
                    <h2 class="mt-4 text-xl font-semibold text-text">No dishes match your search</h2>
                    <p class="mt-2 text-text-muted">Try a different search term, or clear your filters to see the full menu.</p>
                    <a href="{{ route('menu.index') }}" class="mt-6 inline-block rounded-md bg-primary px-6 py-3 text-sm font-semibold text-white hover:bg-primary-dark">
                        Clear search and filters
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection