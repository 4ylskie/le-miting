@props([])

@php
    $links = [
        ['name' => 'home', 'active' => 'home', 'label' => 'Home'],
        ['name' => 'menu.index', 'active' => 'menu.*', 'label' => 'Menu'],
        ['name' => 'ai.recommendations', 'active' => 'ai.recommendations*', 'label' => 'Rasa AI'],
        ['name' => 'reservation.create', 'active' => 'reservation.*', 'label' => 'Reserve'],
        ['name' => 'contact.create', 'active' => 'contact.*', 'label' => 'Contact'],
    ];
    $cartCount = \App\Http\Controllers\CartController::count();
@endphp

<header
    {{ $attributes->class(['lm-customer-nav sticky top-0 z-40 max-w-full box-border border-b border-line bg-bone text-espresso']) }}
    data-customer-nav
>
    <div class="lm-container">
        <div class="flex min-h-16 min-w-0 max-w-full items-center gap-1 py-2 sm:gap-2 lg:min-h-20">
            <a
                href="{{ route('home') }}"
                class="lm-customer-nav__brand inline-flex min-h-(--lm-touch) min-w-0 items-center"
                @if (request()->routeIs('home')) aria-current="page" @endif
            >
                <x-brand-logo size="md" variant="customer" />
            </a>

            <nav class="ml-auto hidden items-center gap-(--lm-space-lg) lg:flex" aria-label="Main navigation">
                @foreach ($links as $link)
                    <a
                        href="{{ route($link['name']) }}"
                        class="lm-meta inline-flex min-h-(--lm-touch) min-w-(--lm-touch) items-center underline-offset-8 hover:underline aria-[current=page]:underline"
                        @if (request()->routeIs($link['active'])) aria-current="page" @endif
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-(--lm-space-xs) lg:ml-(--lm-space-sm)">
                <a
                    href="{{ route('cart.index') }}"
                    class="lm-meta inline-flex min-h-(--lm-touch) shrink-0 items-center gap-1 px-1 underline-offset-8 hover:underline aria-[current=page]:underline sm:px-2"
                    @if (request()->routeIs('cart.*')) aria-current="page" @endif
                >
                    Cart
                    <span id="cart-count">
                        @if ($cartCount > 0)
                            ({{ $cartCount }})
                        @endif
                    </span>
                </a>
                <button type="button" id="mobile-menu-toggle" data-nav-open aria-label="Open navigation" aria-controls="customer-nav-dialog" aria-expanded="false" aria-haspopup="dialog" class="lm-meta inline-flex min-h-(--lm-touch) min-w-(--lm-touch) items-center justify-center px-2 lg:hidden">
                    Menu
                </button>
            </div>
        </div>

        <nav class="lm-customer-nav__quick-links border-t border-line py-1 lg:hidden" aria-label="Quick navigation">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['name']) }}"
                    class="underline-offset-4 hover:underline aria-[current=page]:underline"
                    @if (request()->routeIs($link['active'])) aria-current="page" @endif
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
            <a href="{{ route('cart.index') }}" class="underline-offset-4 hover:underline aria-[current=page]:underline" @if (request()->routeIs('cart.*')) aria-current="page" @endif>Cart</a>
        </nav>
    </div>
</header>

<dialog id="customer-nav-dialog" aria-labelledby="customer-nav-heading" class="lm-customer-nav__dialog fixed inset-y-0 right-0 left-auto m-0 h-dvh max-h-none w-80 max-w-[calc(100%-2rem)] overflow-y-auto border border-line bg-bone p-5 text-espresso backdrop:bg-black/60">
    <div class="flex items-center justify-between gap-3 border-b border-line pb-4">
        <h2 id="customer-nav-heading" class="lm-h3">Navigation</h2>
        <button type="button" data-nav-close class="lm-button lm-button--quiet">Close</button>
    </div>
    <nav id="mobile-menu" aria-label="Mobile navigation" class="lm-stack pt-4">
        @foreach ($links as $link)
            <a href="{{ route($link['name']) }}" class="lm-button lm-button--quiet justify-start" @if (request()->routeIs($link['active'])) aria-current="page" @endif>{{ $link['label'] }}</a>
        @endforeach
        <a href="{{ route('cart.index') }}" class="lm-button lm-button--quiet justify-start" @if (request()->routeIs('cart.*')) aria-current="page" @endif>Cart</a>
    </nav>
</dialog>
