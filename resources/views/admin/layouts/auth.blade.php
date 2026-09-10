@props(['title' => 'Admin'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — Le Miting Admin</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface font-sans text-text antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="flex h-16 items-center justify-between border-b border-brand-100 bg-surface/95 px-4 sm:px-6">
            <a href="{{ route('admin.login') }}" class="flex items-center gap-2">
                <span class="text-xl" aria-hidden="true">🍜</span>
                <span class="text-lg font-bold text-brand-800">Le Miting</span>
                <span class="rounded bg-accent px-1.5 py-0.5 text-xs font-semibold text-white">Admin</span>
            </a>
        </header>

        <main class="flex flex-1 px-4 py-8 sm:px-6">
            <div class="mx-auto w-full">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>