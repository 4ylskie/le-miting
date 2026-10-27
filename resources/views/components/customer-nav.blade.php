@props([])

@php
    $links = [
        ['name' => 'menu.index', 'active' => 'menu.*', 'label' => 'Menu'],
        ['name' => 'reservation.create', 'active' => 'reservation.*', 'label' => 'Reserve'],
        ['name' => 'ai.recommendations', 'active' => 'ai.recommendations*', 'label' => 'Rasa AI'],
        ['name' => 'contact.create', 'active' => 'contact.*', 'label' => 'Contact'],
    ];
    $cartCount = \App\Http\Controllers\CartController::count();
@endphp

<header
    {{ $attributes->class(['sticky top-0 z-40 border-b border-line bg-bone text-espresso']) }}
    data-customer-nav
>
    <div class="lm-container">
        <div class="flex min-h-20 flex-wrap items-center justify-between gap-x-(--lm-space-sm) gap-y-(--lm-space-xs) py-(--lm-space-xs)">
            <a
                href="{{ route('home') }}"
                class="inline-flex min-h-(--lm-touch) shrink-0 items-center"
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

            <div class="flex items-center gap-(--lm-space-xs) lg:ml-(--lm-space-sm)">
                <a
                    href="{{ route('cart.index') }}"
                    class="lm-meta inline-flex min-h-(--lm-touch) items-center gap-1 px-2 underline-offset-8 hover:underline aria-[current=page]:underline"
                    @if (request()->routeIs('cart.*')) aria-current="page" @endif
                >
                    Cart
                    <span id="cart-count">
                        @if ($cartCount > 0)
                            ({{ $cartCount }})
                        @endif
                    </span>
                </a>

                <button
                    type="button"
                    id="mobile-menu-toggle"
                    class="lm-button lm-button--quiet lg:hidden"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="mobile-menu"
                >
                    Menu
                </button>
            </div>
        </div>

        <nav id="mobile-menu" class="hidden max-h-[calc(100dvh-5rem)] overflow-y-auto border-t border-line py-(--lm-space-sm) lg:hidden" aria-label="Mobile navigation">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['name']) }}"
                    class="lm-body flex min-h-(--lm-touch) items-center py-(--lm-space-xs) underline-offset-8 hover:underline aria-[current=page]:underline"
                    @if (request()->routeIs($link['active'])) aria-current="page" @endif
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
