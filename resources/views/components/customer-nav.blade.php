@props([])

@php
    $links = [
        ['name' => 'home', 'label' => 'Home'],
        ['name' => 'menu.index', 'label' => 'Menu'],
        ['name' => 'reservation.create', 'label' => 'Reserve'],
        ['name' => 'ai.recommendations', 'label' => 'Rasa AI'],
        ['name' => 'contact.create', 'label' => 'Contact Us'],
    ];
    $current = request()->route() ? request()->route()->getName() : '';
@endphp

<header class="sticky top-0 z-40 bg-surface/95 backdrop-blur border-b border-brand-100">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="text-2xl" aria-hidden="true">🍜</span>
                <span class="text-xl font-bold tracking-tight text-brand-800">Le Miting</span>
            </a>

            <nav class="hidden md:flex items-center gap-1" aria-label="Main navigation">
                @foreach ($links as $link)
                    @php $isActive = $current === $link['name']; @endphp
                    <a
                        href="{{ route($link['name']) }}"
                        class="rounded-md px-3 py-2 text-sm font-medium {{ $isActive ? 'text-brand-800 bg-brand-50' : 'text-text-muted hover:text-brand-700 hover:bg-brand-50' }}"
                        @if ($isActive) aria-current="page" @endif
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                <a
                    href="{{ route('cart.index') }}"
                    class="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-white px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50"
                >
                    🛒 Cart
                    <span class="text-xs text-text-muted" id="cart-count">
                        @if (\App\Http\Controllers\CartController::count() > 0)
                            ({{ \App\Http\Controllers\CartController::count() }})
                        @endif
                    </span>
                </a>

                <button
                    type="button"
                    class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-text-muted hover:bg-brand-50"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden pb-3" role="navigation" aria-label="Mobile navigation">
            @foreach ($links as $link)
                @php $isActive = $current === $link['name']; @endphp
                <a
                    href="{{ route($link['name']) }}"
                    class="block rounded-md px-3 py-2 text-base font-medium {{ $isActive ? 'text-brand-800 bg-brand-50' : 'text-text-muted hover:text-brand-700 hover:bg-brand-50' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</header>
