@extends('emails.layout')

@section('content')
    <h1>Thank you for visiting Le Miting</h1>

    <p>Hi {{ $customerName }},</p>

    <p>We hope you enjoyed your {{ $transactionLabel }}. If you would like to, you can share your experience on Google.</p>

    <p>
        <a class="btn" href="{{ $googleReviewUrl }}" target="_blank" rel="noopener noreferrer">Leave a Google Review</a>
    </p>

    <p class="muted">Leaving a review is optional. Google receives and manages any review you submit.</p>
@endsection
