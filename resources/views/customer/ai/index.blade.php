@extends('layouts.master')

@section('title', 'Rasa AI Recommendations — Le Miting')

@section('content')
    <section class="bg-brand-950 text-white py-14" aria-label="Rasa AI">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold md:text-4xl">Rasa AI Recommendations</h1>
            <p class="mt-3 max-w-2xl text-brand-100">
                Tell us what you like, and Rasa AI will suggest dishes you will love. It is optional — you can always browse the menu yourself.
            </p>
        </div>
    </section>

    <section class="py-12" aria-label="Preference form">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @include('customer.ai.form')
        </div>
    </section>
@endsection