<?php

use App\Mail\ContactEnquiryMail;
use Illuminate\Support\Facades\Mail;

test('contact submissions are emailed to the configured restaurant address', function () {
    Mail::fake();
    config(['restaurant.email' => 'restaurant@lemiting.com']);

    $this->post(route('contact.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'message' => 'Do you have halal options?',
    ])->assertRedirect(route('contact.create'));

    Mail::assertSent(ContactEnquiryMail::class, function (ContactEnquiryMail $mail) {
        return $mail->hasTo('restaurant@lemiting.com');
    });
});
