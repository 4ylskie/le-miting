@extends('admin.layouts.auth', ['title' => 'Admin Login'])

@section('content')
    <div class="lm-stack">
        <div class="lm-stack border-b border-line pb-8">
            <p class="lm-eyebrow">Staff access</p>
            <h1 class="font-display text-5xl">Admin Login</h1>
            <p class="lm-body text-mineral">Sign in to manage restaurant operations.</p>
        </div>

        @if (session('status'))
            <p class="lm-alert" role="status">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" class="lm-stack">
            @csrf

            <p class="lm-meta text-mineral">Email and password are required.</p>
            <div class="lm-field">
                <label for="email" class="lm-label">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    class="lm-control"
                    @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif
                >
                @error('email')
                    <p id="email-error" class="lm-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="lm-field">
                <label for="password" class="lm-label">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="lm-control"
                    @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
                >
                @error('password')
                    <p id="password-error" class="lm-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-espresso">
                    Remember me
                </label>
            </div>

            <button
                type="submit"
                class="lm-button w-full"
            >
                Sign in
            </button>
        </form>
    </div>
@endsection
