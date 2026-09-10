@extends('emails.layout')

@section('heading')
    How was your visit, {{ $customerName }}?
@endsection

@section('content')
    <p class="muted">Thank you for visiting Le Miting ({{ $transactionLabel }}). We would love to hear about your experience.</p>

    <p class="muted">Your feedback helps us serve you better. It takes less than a minute to complete.</p>

    <div>
        <a class="btn" href="{{ $feedbackUrl }}">Leave feedback</a>
    </div>
    <p class="muted" style="margin-top:8px;">The feedback link expires in 24 hours.</p>

    <p class="muted" style="margin-top:16px;">Enjoyed your meal? Leave us a Google review at <a href="{{ config('restaurant.google_review_url') }}">Google</a>.</p>
@endsection