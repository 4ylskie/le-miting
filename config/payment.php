<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment driver
    |--------------------------------------------------------------------------
    |
    | 'mock' uses the local simulated FIUU gateway (development default).
    | 'fiuu' uses the real FIUU hosted payment page.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'mock'),

    'hold_minutes' => 15,

    'fiuu' => [
        'merchant_id' => env('FIUU_MERCHANT_ID'),
        'secret_key' => env('FIUU_SECRET_KEY'),
        'base_url' => env('FIUU_BASE_URL', 'https://payment.fiuu.com'),
        'return_url' => env('FIUU_RETURN_URL'),
        'callback_url' => env('FIUU_CALLBACK_URL'),
    ],

];
