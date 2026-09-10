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
                ['name' => 'admin.feedback.index', 'label' => 'Feedback'],
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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface font-sans text-text antialiased min-h-screen">
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-brand-100 bg-brand-950 text-brand-100 transition-transform lg:translate-x-0 lg:translate-y-0" id="admin-sidebar" aria-label="Admin navigation">
            <div class="flex h-16 items-center justify-between px-5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <span class="text-xl" aria-hidden="true">🍜</span>
                    <span class="text-lg font-bold text-white">Le Miting</span>
                    <span class="rounded bg-accent px-1.5 py-0.5 text-xs font-semibold text-white">Admin</span>
                </a>
            </div>

            <nav class="mt-2 px-3 pb-6">
                @foreach ($sections as $section)
                    <p class="mt-6 px-3 text-xs font-semibold uppercase tracking-wider text-brand-300">{{ $section['label'] }}</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($section['links'] as $link)
                            @php $isActive = $current === $link['name']; @endphp
                            <li>
                                <a
                                    href="{{ route($link['name']) }}"
                                    class="block rounded-md px-3 py-2 text-sm font-medium {{ $isActive ? 'bg-brand-800 text-white' : 'text-brand-200 hover:bg-brand-900 hover:text-white' }}"
                                    @if ($isActive) aria-current="page" @endif
                                >
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
        </aside>

        <div class="flex flex-1 flex-col lg:pl-72">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-brand-100 bg-surface/95 px-4 backdrop-blur sm:px-6">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md p-2 text-text-muted hover:bg-brand-50 lg:hidden"
                    aria-label="Open navigation menu"
                    onclick="document.getElementById('admin-sidebar').classList.remove('-translate-x-full')"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <h1 class="text-base font-semibold text-brand-800 sm:text-lg">{{ $title }}</h1>

                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-text-muted sm:inline">
                        {{ auth()->user()->name }}
                    </span>
                    <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md border border-brand-200 bg-white px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50"
                        >
                            Log out
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                <div>
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>