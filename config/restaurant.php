<?php

return [

    'timezone' => 'Asia/Kuala_Lumpur',

    /*
     * Configure the restaurant's public details and daily opening hours here.
     * Replace these settings with approved deployment information; keep the
     * enquiry recipient separately from the public contact email.
     */
    'name' => env('RESTAURANT_NAME', 'Le Miting'),
    'is_demo' => env('RESTAURANT_IS_DEMO', true),
    'phone' => trim((string) env('RESTAURANT_PHONE', '')) ?: '+60 12-345 6789',
    'public_email' => trim((string) env('RESTAURANT_PUBLIC_EMAIL', '')) ?: 'mika25@gmail.com',
    'email' => env('RESTAURANT_EMAIL', 'mika25@gmail.com'),
    'address' => trim((string) env('RESTAURANT_ADDRESS', '')) ?: 'Jalan Ilmiah, Taman Universiti, 43000 Bangi, Selangor',

    // ISO weekdays: 1 = Monday, 7 = Sunday. Omitted/null days are closed.
    // A null schedule means hours are not configured.
    'opening_hours' => [
        1 => ['open' => '08:00', 'close' => '16:00'],
        2 => ['open' => '08:00', 'close' => '16:00'],
        3 => ['open' => '08:00', 'close' => '16:00'],
        4 => ['open' => '08:00', 'close' => '16:00'],
        5 => ['open' => '08:00', 'close' => '16:00'],
        6 => ['open' => '08:00', 'close' => '16:00'],
        7 => ['open' => '08:00', 'close' => '16:00'],
    ],

    'maps' => [
        'embed_key' => env('GOOGLE_MAPS_EMBED_KEY', ''),
        'query' => env('GOOGLE_MAPS_EMBED_QUERY', ''),
    ],

    'google_review_url' => env('GOOGLE_REVIEW_URL', 'https://www.google.com/maps'),

];
