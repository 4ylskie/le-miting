@extends('admin.layouts.auth', ['title' => 'Admin Login'])

@section('content')
    <div class="flex min-h-[70vh] items-center justify-center py-12">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-brand-100 bg-white p-8 shadow-sm">
                <div class="text-center">
                    <span class="text-3xl" aria-hidden="true">🍜</span>
                    <h2 class="mt-3 text-2xl font-bold text-brand-800">Le Miting Admin</h2>
                    <p class="mt-1 text-sm text-text-muted">Sign in to manage restaurant operations.</p>
                </div>

                @if (session('status'))
                    <p class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.login.submit') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-text">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has('email') ? 'border-red-400' : '' }}"
                        >
                        @error('email')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-text">Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        @error('password')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-text-muted">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary">
                            Remember me
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        Sign in
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection