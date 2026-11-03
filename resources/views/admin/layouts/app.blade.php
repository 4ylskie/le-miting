@props(['title' => 'Admin'])

@php
    $sections = [
        [
            'label' => 'Overview',
            'links' => [
                ['name' => 'admin.dashboard', 'label' => 'Dashboard'],
            ],
        ],
        [
            'label' => 'Operations',
            'links' => [
                ['name' => 'admin.orders.index', 'label' => 'Orders'],
                ['name' => 'admin.reservations.index', 'label' => 'Reservations'],
                ['name' => 'admin.tables.index', 'label' => 'Tables'],
            ],
        ],
        [
            'label' => 'Management',
            'links' => [
                ['name' => 'admin.menu.items.index', 'label' => 'Menu Items'],
                ['name' => 'admin.menu.categories.index', 'label' => 'Categories'],
            ],
        ],
        [
            'label' => 'Reporting',
            'links' => [
                ['name' => 'admin.reports.index', 'label' => 'Reports'],
            ],
        ],
    ];

    $current = request()->route() ? request()->route()->getName() : '';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — Le Miting Admin</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lm-site lm-admin min-h-screen antialiased" data-admin-shell>
    <div class="lm-shader-grain" aria-hidden="true"></div>
    <a href="#admin-main" class="lm-button sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">Skip to content</a>
    <div class="min-h-screen">
        <aside class="bg-espresso text-ivory lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:w-64 lg:overflow-y-auto" id="admin-sidebar" aria-label="Administration sidebar">
          <div id="admin-navigation" class="flex min-h-full flex-col px-5 py-6 [&_:focus-visible]:outline-ivory">
            <div class="flex items-start justify-between gap-3 border-b border-ivory/20 pb-6">
                <a href="{{ route('admin.dashboard') }}" class="flex min-h-11 flex-col gap-2">
                    <x-brand-logo size="md" variant="admin" />
                    <span class="text-xs tracking-widest uppercase text-ivory/70">Administration</span>
                </a>
                <button type="button" data-admin-close hidden class="min-h-11 px-2 text-sm underline underline-offset-4">Close</button>
            </div>

            <nav class="flex-1 pb-6" aria-label="Admin navigation">
                @foreach ($sections as $section)
                    <p class="mt-6 px-3 text-xs uppercase tracking-widest text-ivory/60">{{ $section['label'] }}</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($section['links'] as $link)
                            @php
                                $isActive = $current === $link['name']
                                    || (str_ends_with($link['name'], '.index') && str_starts_with($current, substr($link['name'], 0, -5)))
                                    || ($link['name'] === 'admin.menu.items.index' && str_starts_with($current, 'admin.menu.allergens.'));
                            @endphp
                            <li>
                                <a
                                    href="{{ route($link['name']) }}"
                                    class="flex min-h-11 items-center border-l-2 px-3 py-2 text-sm {{ $isActive ? 'border-ivory bg-ivory/10 text-ivory' : 'border-transparent text-ivory/80 hover:bg-ivory/10 hover:text-ivory' }}"
                                    @if ($isActive) aria-current="page" @endif
                                >
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-ivory/20 pt-4">
                @csrf
                <button type="submit" class="min-h-11 w-full px-3 text-left text-sm underline-offset-4 hover:underline">Log out</button>
            </form>
          </div>
        </aside>
        <dialog id="admin-navigation-dialog" aria-label="Administration navigation" class="fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-72 max-w-[calc(100%-3rem)] overflow-y-auto border-0 bg-espresso p-0 text-ivory backdrop:bg-espresso/50"></dialog>

        <div class="min-w-0 lg:pl-64">
            <header class="flex min-h-24 flex-wrap items-center justify-between gap-4 border-b border-line bg-ivory px-5 py-5 sm:px-8">
              <div class="flex w-full min-w-0 items-center gap-3 sm:gap-4 lg:w-auto lg:flex-1">
                <button
                    type="button"
                    hidden
                    data-admin-open
                    data-admin-nav-open
                    class="min-h-11 shrink-0 border border-line px-3 text-sm lg:hidden"
                    aria-label="Open administration navigation"
                    aria-controls="admin-navigation-dialog"
                    aria-expanded="false"
                    aria-haspopup="dialog"
                >
                    Menu
                </button>
                <h1 class="min-w-0 font-display text-3xl wrap-anywhere sm:text-4xl">{{ $title }}</h1>
              </div>
              <div class="flex min-w-0 flex-wrap items-center gap-4">
                @yield('header-actions')
                <span class="text-sm text-mineral wrap-anywhere">{{ auth()->user()->name }}</span>
              </div>
            </header>

            <main id="admin-main" tabindex="-1" class="min-w-0 px-5 py-8 sm:px-8">
                <div class="mx-auto w-full min-w-0 max-w-7xl">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
