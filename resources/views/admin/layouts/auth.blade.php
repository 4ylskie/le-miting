@props(['title' => 'Admin'])

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
<body class="lm-site lm-admin antialiased">
    <div class="lm-shader-grain" aria-hidden="true"></div>
    <a href="#admin-main" class="lm-button sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">Skip to content</a>
    <div class="flex min-h-screen flex-col">
        <header class="flex min-h-24 flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-5 sm:px-8">
            <a href="{{ route('admin.login') }}" class="inline-flex min-h-11 items-center">
                <x-brand-logo size="md" variant="admin" mode="lockup" />
            </a>
            <span class="lm-label text-mineral">Administration</span>
        </header>

        <main id="admin-main" tabindex="-1" class="flex flex-1 items-center px-5 py-12 sm:px-8 sm:py-20">
            <div class="mx-auto w-full max-w-md">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
