<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment driver
    |--------------------------------------------------------------------------
    |
    | 'stripe' uses Stripe Checkout or the local test simulator without a key.
    | 'mock' retains the local simulated payment gateway for existing tests.
    | 'fiuu' uses the real FIUU hosted payment page.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'stripe'),

    'hold_minutes' => 15,

    'fiuu' => [
        'merchant_id' => env('FIUU_MERCHANT_ID'),
        'secret_key' => env('FIUU_SECRET_KEY'),
        'base_url' => env('FIUU_BASE_URL', 'https://payment.fiuu.com'),
        'return_url' => env('FIUU_RETURN_URL'),
        'callback_url' => env('FIUU_CALLBACK_URL'),
    ],

];
