<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Le Miting — Malaysian Restaurant')</title>
    <meta name="description" content="@yield('meta_description', 'Le Miting — Browse the menu, order food, or reserve a table online.')">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lm-site lm-customer lm-culinary-shader-theme antialiased min-h-screen flex flex-col">
    <div class="lm-shader-grain" aria-hidden="true"></div>

    <a href="#main-content" class="lm-button sr-only focus:not-sr-only focus:fixed focus:left-(--lm-gutter) focus:top-4 focus:z-50">
        Skip to content
    </a>

    <x-customer-nav />

    <main id="main-content" tabindex="-1" class="min-w-0 flex-1 scroll-mt-44 sm:scroll-mt-32 lg:scroll-mt-24">
        @yield('content')
    </main>

    <footer class="lm-divider bg-bone text-espresso">
        <div class="lm-container lm-section--compact">
            <div class="flex flex-col gap-(--lm-space-lg) md:flex-row md:items-start md:justify-between">
                <a href="{{ route('home') }}" class="inline-flex min-h-(--lm-touch) items-center self-start">
                    <x-brand-logo size="sm" variant="footer" />
                </a>
                <nav class="lm-meta flex flex-col gap-(--lm-space-xs) sm:flex-row sm:flex-wrap sm:gap-x-(--lm-space-lg)" aria-label="Policies">
                    @foreach ([
                        'policies.terms' => 'Terms & Conditions',
                        'policies.privacy' => 'Privacy Policy',
                        'policies.cancellation' => 'Cancellation & Refund Policy',
                    ] as $routeName => $label)
                        <a href="{{ route($routeName) }}" class="inline-flex min-h-(--lm-touch) min-w-(--lm-touch) items-center underline-offset-8 hover:underline">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <p class="lm-meta lm-muted mt-(--lm-space-lg)">&copy; {{ now()->year }} Le Miting. Third-party content belongs to its respective owners.</p>
        </div>
    </footer>

    @stack('scripts')
    <script src="{{ asset('js/customer-interactions.js') }}" defer></script>
</body>
</html>
