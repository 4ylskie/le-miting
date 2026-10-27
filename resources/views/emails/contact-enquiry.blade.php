@extends('emails.layout')

@section('heading')
    New enquiry from {{ $name }}
@endsection

@section('content')
    <dl>
        <div class="row"><dt>Name</dt><dd>{{ $name }}</dd></div>
        <div class="row"><dt>Email</dt><dd>{{ $email }}</dd></div>
    </dl>

    <p class="muted enquiry-text">{{ $enquiry }}</p>

    <p class="muted mt-16">This enquiry was sent from the Contact Us page. There is no customer reply system.</p>
@endsection
