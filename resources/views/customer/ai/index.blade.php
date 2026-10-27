@extends('layouts.master')

@section('title', 'Rasa AI Recommendations — Le Miting')

@section('content')
    <section class="lm-container lm-section grid items-start gap-(--lm-space-xl) lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]" aria-labelledby="rasa-heading">
        <header class="lm-stack">
            <p class="lm-eyebrow">Menu discovery</p>
            <h1 id="rasa-heading" class="lm-h1">Rasa AI</h1>
            <p class="lm-body lm-muted max-w-md">Tell us what you are in the mood for. We will look for matching dishes on the available menu.</p>
            <p class="lm-meta lm-muted max-w-md">Rasa AI is optional. You can always browse the full menu instead.</p>
        </header>
        <div class="min-w-0">
            @if (session('success'))
                <p class="lm-alert mb-(--lm-space-lg)" data-state="success" role="status">{{ session('success') }}</p>
            @endif
            @if ($errors->has('menu_item_id') || $errors->has('quantity'))
                <div class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">
                    @foreach (array_merge($errors->get('menu_item_id'), $errors->get('quantity')) as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif
            @if (session('error'))
                <div class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">{{ session('error') }}</div>
            @endif
            @include('customer.ai.form', ['editorial' => true])
        </div>
    </section>
@endsection
