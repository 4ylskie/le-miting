@extends('layouts.master')

@section('title', $item->name.' — Le Miting')

@section('content')
    <section class="py-12" aria-label="Dish details">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <nav class="text-sm text-text-muted" aria-label="Breadcrumb">
                <a href="{{ route('menu.index') }}" class="hover:text-brand-700">Menu</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <a href="{{ route('menu.index', ['category' => $item->category->slug]) }}" class="hover:text-brand-700">
                    {{ $item->category->name }}
                </a>
            </nav>

            <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-start">
                <div class="flex h-80 items-center justify-center rounded-2xl bg-brand-50 text-8xl" aria-hidden="true">
                    @if ($item->image_path)
                        <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover rounded-2xl">
                    @else
                        🍽️
                    @endif
                </div>

                <div>
                    <div class="flex items-start justify-between gap-4">
                        <h1 class="text-3xl font-bold text-brand-800">{{ $item->name }}</h1>
                        <span class="shrink-0 text-2xl font-bold text-primary">RM {{ number_format($item->price, 2) }}</span>
                    </div>

                    <p class="mt-4 text-base leading-relaxed text-text-muted">{{ $item->description }}</p>

                    <dl class="mt-6 grid grid-cols-2 gap-4 text-sm" aria-label="Dish attributes">
                        @if ($item->spice_level)
                            <div>
                                <dt class="font-semibold text-text-muted">Spice Level</dt>
                                <dd class="mt-1 text-text">{{ $item->spice_level }}</dd>
                            </div>
                        @endif
                        @if ($item->protein_type)
                            <div>
                                <dt class="font-semibold text-text-muted">Protein</dt>
                                <dd class="mt-1 text-text">{{ $item->protein_type }}</dd>
                            </div>
                        @endif
                        @if ($item->dietary_type)
                            <div>
                                <dt class="font-semibold text-text-muted">Dietary</dt>
                                <dd class="mt-1 text-text">{{ $item->dietary_type }}</dd>
                            </div>
                        @endif
                        @if ($item->flavor_profile)
                            <div>
                                <dt class="font-semibold text-text-muted">Flavour</dt>
                                <dd class="mt-1 text-text">{{ $item->flavor_profile }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($item->is_available)
                        <form method="POST" action="{{ route('cart.add') }}" class="mt-8 flex items-center gap-4">
                            @csrf
                            <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                            <label for="quantity" class="sr-only">Quantity</label>
                            <select
                                id="quantity"
                                name="quantity"
                                class="rounded-md border border-brand-200 bg-white px-4 py-3 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                @for ($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Add to Cart
                            </button>
                        </form>
                    @else
                        <p class="mt-8 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            This dish is currently unavailable.
                        </p>
                    @endif
                </div>
            </div>

            @if ($related->count() > 0)
                <div class="mt-16" aria-label="You may also like">
                    <h2 class="text-2xl font-bold text-brand-800">You May Also Like</h2>
                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                        @foreach ($related as $item)
                            <a
                                href="{{ route('menu.show', $item) }}"
                                class="rounded-xl border border-brand-100 bg-white p-4 shadow-sm hover:shadow-md transition-shadow"
                            >
                                <div class="flex h-28 items-center justify-center rounded-lg bg-brand-50 text-4xl" aria-hidden="true">
                                    {{ $item->image_path ? '' : '🍽️' }}
                                    @if ($item->image_path)
                                        <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover rounded-lg">
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <h3 class="font-semibold text-text">{{ $item->name }}</h3>
                                    <span class="shrink-0 font-semibold text-primary">RM {{ number_format($item->price, 2) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection