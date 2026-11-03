@extends('layouts.master')

@section('title', 'Menu — Le Miting')

@section('content')
    <header class="lm-container lm-section">
        <p class="lm-eyebrow mb-(--lm-space-md)">Le Miting</p>
        <h1 class="lm-h1">Our Menu</h1>
        <p class="lm-body lm-muted mt-(--lm-space-md) max-w-xl">Find a dish for today. Browse by category, explore your preferences, or search the menu.</p>
    </header>

    <section class="lm-container" aria-label="Menu search and filters">
        <div class="lm-divider grid gap-(--lm-space-lg) py-(--lm-space-lg) lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <nav class="flex flex-wrap items-start gap-x-(--lm-space-md) gap-y-(--lm-space-xs)" aria-label="Menu categories">
                <a href="{{ route('menu.index') }}" class="lm-meta inline-flex min-h-(--lm-touch) min-w-(--lm-touch) items-center underline-offset-8 hover:underline aria-[current=page]:underline aria-[current=page]:font-semibold" @if (! request()->filled('category')) aria-current="page" @endif>All</a>
                @foreach ($categories as $category)
                    <a href="{{ route('menu.index', ['category' => $category->slug] + request()->except(['category', 'page'])) }}" class="lm-meta inline-flex min-h-(--lm-touch) min-w-(--lm-touch) items-center wrap-anywhere underline-offset-8 hover:underline aria-[current=page]:underline aria-[current=page]:font-semibold" @if (request()->input('category') === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('menu.index') }}" role="search" class="lm-field">
                <label for="menu-search" class="lm-label">Search menu</label>
                <div class="flex flex-col gap-(--lm-space-xs) sm:flex-row">
                    <input id="menu-search" type="search" name="q" value="{{ request()->input('q') }}" placeholder="Search dishes…" class="lm-control">
                    <button type="submit" class="lm-button">Search</button>
                </div>
            </form>
        </div>

        @php
            $filterGroups = [
                'spice' => ['field' => 'spice_level', 'label' => 'Spice Level', 'options' => $filters['spiceLevels']],
                'protein' => ['field' => 'protein_type', 'label' => 'Protein', 'options' => $filters['proteinTypes']],
                'dietary' => ['field' => 'dietary_type', 'label' => 'Dietary', 'options' => $filters['dietaryTypes']],
                'flavor' => ['field' => 'flavor_profile', 'label' => 'Flavour', 'options' => $filters['flavorProfiles']],
            ];
        @endphp
        <div class="lm-divider grid gap-(--lm-space-md) py-(--lm-space-lg) md:grid-cols-2">
            @foreach ($filterGroups as $key => $group)
                @if ($group['options']->isNotEmpty())
                    <div class="min-w-0">
                        <h2 id="filter-{{ $key }}" class="lm-label">{{ $group['label'] }}</h2>
                        <nav class="flex flex-wrap gap-x-(--lm-space-md)" aria-labelledby="filter-{{ $key }}">
                            @foreach ($group['options'] as $option)
                                @php
                                    $isActive = request()->input($key) === $option;
                                    $query = request()->except([$key, 'page']);
                                    $href = $isActive ? route('menu.index', $query) : route('menu.index', $query + [$key => $option]);
                                @endphp
                                <a href="{{ $href }}" class="lm-meta inline-flex min-h-(--lm-touch) items-center wrap-anywhere underline-offset-8 hover:underline aria-[current=true]:underline aria-[current=true]:font-semibold" @if ($isActive) aria-current="true" @endif>
                                    {{ \App\Services\Ai\AiRecommendationOptions::label($group['field'], $option) }}@if ($isActive)<span class="sr-only"> — selected; remove filter</span>@endif
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endif
            @endforeach
        </div>
        @if (request()->except('page'))
            <a href="{{ route('menu.index') }}" class="lm-button lm-button--quiet mb-(--lm-space-lg)">Clear all filters</a>
        @endif
    </section>

    <section class="lm-container pb-lm-section" aria-label="Menu items">
        <div class="lm-actions mb-(--lm-space-lg)">
            <p class="lm-meta lm-muted" role="status">
                Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ $items->total() }} {{ $hasFilters ? 'matching dishes' : 'dishes' }}.
                Page {{ $items->currentPage() }} of {{ $items->lastPage() }}.
            </p>
            @if ($hasFilters)
                <a href="{{ route('menu.index') }}" class="lm-button lm-button--secondary">View all {{ $catalogTotal }} dishes</a>
            @endif
        </div>
        @if (session('success'))
            <p class="lm-alert mb-(--lm-space-lg)" data-state="success" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif
        @forelse ($items as $item)
            <x-menu-item :item="$item" />
        @empty
            <div class="lm-empty lm-stack items-start">
                <h2 class="lm-h2">No dishes match your search</h2>
                <p class="lm-body lm-muted">Try a different search term, or clear your filters to see the full menu.</p>
                <a href="{{ route('menu.index') }}" class="lm-button lm-button--secondary">Clear search and filters</a>
            </div>
        @endforelse
        {{ $items->links('components.discovery-pagination') }}
    </section>
@endsection
