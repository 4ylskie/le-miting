@extends('layouts.master')

@section('title', 'Contact Us — '.$restaurantName)

@section('content')
    <div class="lm-container">
        <section class="lm-section--compact" aria-labelledby="contact-heading">
            <header class="lm-stack">
                <p class="lm-eyebrow">{{ $restaurantName }}</p>
                <h1 id="contact-heading" class="lm-h1">Contact Us</h1>
                <p class="lm-body lm-muted max-w-xl">We’d love to hear from you. Send a question or share your thoughts using the enquiry form below.</p>
            </header>
        </section>

        <div class="lm-contact-layout lm-section--compact">
            <section class="lm-contact-card" aria-labelledby="contact-details-heading">
                <h2 id="contact-details-heading" class="lm-h2">Contact Details</h2>
                <dl class="lm-contact-details">
                    <div>
                        <dt>Contact Number</dt>
                        <dd>
                            @if ($phoneLink)
                                <a href="{{ $phoneLink }}">{{ $phone }}</a>
                            @else
                                {{ $phone }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>
                            @if ($emailLink)
                                <a href="{{ $emailLink }}">{{ $email }}</a>
                            @else
                                {{ $email }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Address</dt>
                        <dd><address>{{ $address }}</address></dd>
                    </div>
                </dl>

                <div class="lm-contact-hours">
                    <h3 class="lm-label">Opening hours</h3>
                    <dl class="lm-contact-hours__list">
                        @foreach ($openingHours as $day => $hours)
                            <div>
                                <dt>{{ $day }}</dt>
                                <dd>{{ $hours }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section class="lm-contact-card" aria-labelledby="contact-enquiry-heading">
                <h2 id="contact-enquiry-heading" class="lm-h2">Send an Enquiry</h2>
                <p class="lm-body lm-muted">Have a question? Leave a message below.</p>
                @if (session('success'))
                    <p class="lm-alert" data-state="success" role="status">{{ session('success') }}</p>
                @endif
                @if (session('error'))
                    <p class="lm-alert" data-state="error" role="alert">{{ session('error') }}</p>
                @endif
                <form method="POST" action="{{ route('contact.store') }}" class="lm-stack" id="contact-form">
                    @csrf
                    <p class="lm-meta lm-muted">All enquiry fields are required.</p>
                    @foreach (['name' => ['label' => 'Name', 'type' => 'text'], 'email' => ['label' => 'Email', 'type' => 'email']] as $field => $input)
                        <div class="lm-field">
                            <label for="{{ $field }}" class="lm-label">{{ $input['label'] }}</label>
                            <input id="{{ $field }}" type="{{ $input['type'] }}" name="{{ $field }}" value="{{ old($field) }}" required autocomplete="{{ $field }}" class="lm-control" @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif>
                            @error($field)
                                <p id="{{ $field }}-error" class="lm-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                    <div class="lm-field">
                        <label for="message" class="lm-label">Message</label>
                        <textarea id="message" name="message" rows="6" required class="lm-control" @if ($errors->has('message')) aria-invalid="true" aria-describedby="message-error" @endif>{{ old('message') }}</textarea>
                        @error('message')
                            <p id="message-error" class="lm-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="lm-button w-full sm:w-auto sm:self-start">Send Enquiry</button>
                </form>
            </section>
        </div>

        <section class="lm-contact-map lm-section--compact" aria-labelledby="contact-location-heading">
            <h2 id="contact-location-heading" class="lm-h2">Find Us</h2>
            <div class="overflow-hidden border-y border-line bg-bone">
                <iframe
                    src="{{ $mapUrl }}"
                    title="{{ $restaurantName }} location map"
                    class="block min-h-80 w-full border-0 md:min-h-112"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
        </section>
    </div>
@endsection
