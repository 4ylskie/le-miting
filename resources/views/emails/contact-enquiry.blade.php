@extends('emails.layout')

@section('heading')
    New enquiry from {{ $name }}
@endsection

@section('content')
    <dl>
        <div class="row"><dt>Name</dt><dd>{{ $name }}</dd></div>
        <div class="row"><dt>Email</dt><dd>{{ $email }}</dd></div>
    </dl>

    <p class="muted" style="white-space:pre-line; margin-top:16px;">{{ $enquiry }}</p>

    <p class="muted" style="margin-top:16px;">This enquiry was sent from the Contact Us page. There is no customer reply system.</p>
@endsection