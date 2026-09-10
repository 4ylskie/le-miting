<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Le Miting — Malaysian Restaurant')</title>
    <meta name="description" content="@yield('meta_description', 'Le Miting — Order authentic Malaysian dishes, reserve a table, and pre-order food online.')">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface font-sans text-text antialiased min-h-screen flex flex-col">
    <x-customer-nav />

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="bg-brand-950 text-brand-100">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div>
                    <h3 class="text-lg font-semibold text-white">Le Miting</h3>
                    <p class="mt-3 text-sm text-brand-200">
                        Authentic Malaysian cuisine served with kampung heart. Order, reserve, and enjoy.
                    </p>
                </div>
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-brand-200">Contact</h4>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li>No. 12, Jalan Melaka Raya<br>75000 Melaka, Malaysia</li>
                        <li>+60 6-123 4567</li>
                        <li><a href="mailto:hello@lemiting.my" class="hover:text-white">hello@lemiting.my</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-brand-200">Opening Hours</h4>
                    <ul class="mt-3 space-y-1 text-sm">
                        <li>Mon – Fri: 11:00 AM – 10:00 PM</li>
                        <li>Sat – Sun: 10:00 AM – 11:00 PM</li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-brand-800 pt-6 text-center text-xs text-brand-300">
                &copy; {{ date('Y') }} Le Miting. All rights reserved.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
