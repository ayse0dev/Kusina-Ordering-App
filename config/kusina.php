<?php

return [
    'currency' => 'CAD',
    'currency_symbol' => '$',
    'delivery_fee' => (float) env('KUSINA_DELIVERY_FEE', 5),
    'tax_rate' => (float) env('KUSINA_TAX_RATE', 0.05),
    'tax_label' => env('KUSINA_TAX_LABEL', 'GST (5%)'),

    'delivery' => [
        'provider' => env('KUSINA_COURIER', 'manual'),
        'dispatch_on' => env('KUSINA_DISPATCH_ON', 'preparing'),
        'max_km' => env('KUSINA_DELIVERY_MAX_KM', 10),
        'manual_eta' => 45,
        'phone_country' => '+1',

        'pickup' => [
            'name' => env('KUSINA_PICKUP_NAME', 'Kusi Nna Restaurant'),
            'address' => env('KUSINA_PICKUP_ADDRESS', ''),
            'phone' => env('KUSINA_PICKUP_PHONE', ''),
            'lat' => env('KUSINA_PICKUP_LAT'),
            'lng' => env('KUSINA_PICKUP_LNG'),
        ],

        'geocoder' => [
            'driver' => env('KUSINA_GEOCODER', 'nominatim'),
            'country' => 'ca',
            'user_agent' => 'KusinaOrderingApp',
        ],

        'uber' => [
            'customer_id' => env('UBER_DIRECT_CUSTOMER_ID'),
            'client_id' => env('UBER_DIRECT_CLIENT_ID'),
            'client_secret' => env('UBER_DIRECT_CLIENT_SECRET'),
            'signing_key' => env('UBER_DIRECT_SIGNING_KEY'),
        ],

        'grab' => [
            'client_id' => env('GRAB_CLIENT_ID'),
            'client_secret' => env('GRAB_CLIENT_SECRET'),
            'env' => env('GRAB_ENV', 'staging'),
            'webhook_token' => env('GRAB_WEBHOOK_TOKEN'),
        ],
    ],

    'contact' => [
        'address' => env('KUSINA_ADDRESS', 'Edson, Alberta, Canada'),
        'phone' => env('KUSINA_PHONE', ''),
        'email' => env('KUSINA_EMAIL', ''),
    ],

    'reservations' => [
        'open' => '09:00',
        'close' => '22:00',
        'slot_minutes' => 90,
        'step_minutes' => 30,
        'advance_days' => 30,
        'max_party' => 12,
        'fee_per_guest' => 10,
    ],

    'payment_methods' => [
        'cash' => ['label' => 'On-Hand Cash', 'hint' => 'Pay in cash when you arrive.', 'paid_now' => false],
        'ewallet' => ['label' => 'E-Wallet', 'hint' => 'GCash, Maya or GrabPay (demo, no real charge).', 'paid_now' => true, 'options' => ['GCash', 'Maya', 'GrabPay']],
        'card' => ['label' => 'Bank (Credit/Debit Card Tap)', 'hint' => 'Tap your card (demo, no real charge).', 'paid_now' => true,
            'banks' => ['RBC Royal Bank', 'TD Canada Trust', 'Scotiabank', 'BMO', 'CIBC', 'National Bank', 'ATB Financial', 'BDO', 'BPI', 'Metrobank', 'UnionBank', 'Landbank']],
    ],

    'vip' => [
        'title' => 'VIP lounge',
        'blurb' => 'Private upstairs seating for celebrations and special evenings.',
        'perks' => ['Private lounge on the 2nd floor', 'Larger tables for groups', 'Tell us your occasion in the notes and we will prepare for it'],
    ],

    'vouchers' => [
        'gift_min' => 10,
        'gift_max' => 500,
        'gift_presets' => [25, 50, 100],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
