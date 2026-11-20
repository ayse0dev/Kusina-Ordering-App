<?php

return [
    'currency' => 'CAD',
    'currency_symbol' => '$',
    'delivery_fee' => (float) env('KUSINA_DELIVERY_FEE', 5),
    'tax_rate' => (float) env('KUSINA_TAX_RATE', 0.05), // Alberta: 5% GST, no PST
    'tax_label' => env('KUSINA_TAX_LABEL', 'GST (5%)'),

    // Food delivery. `provider` picks the courier for NEW deliveries:
    //   manual = your own driver (flat fee, staff assign the driver)
    //   uber   = Uber Direct (available in Canada)
    //   grab   = GrabExpress (Southeast Asia only; will not serve Canadian addresses)
    'delivery' => [
        'provider' => env('KUSINA_COURIER', 'manual'),
        'dispatch_on' => env('KUSINA_DISPATCH_ON', 'preparing'),   // book the courier when the order reaches: preparing | ready
        'max_km' => env('KUSINA_DELIVERY_MAX_KM', 10),             // delivery radius; empty = unlimited
        'manual_eta' => 45,                                        // minutes quoted for own-driver delivery
        'phone_country' => '+1',

        // Where couriers collect the food. Set a precise street address and coordinates.
        'pickup' => [
            'name' => env('KUSINA_PICKUP_NAME', 'Kusina'),
            'address' => env('KUSINA_PICKUP_ADDRESS', ''),          // falls back to the contact address
            'phone' => env('KUSINA_PICKUP_PHONE', ''),
            'lat' => env('KUSINA_PICKUP_LAT'),
            'lng' => env('KUSINA_PICKUP_LNG'),
        ],

        'geocoder' => [
            'driver' => env('KUSINA_GEOCODER', 'nominatim'),        // nominatim | none
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
            'env' => env('GRAB_ENV', 'staging'),                    // staging | production
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
        'slot_minutes' => 90,   // length of one booking
        'step_minutes' => 30,   // interval between selectable start times
        'advance_days' => 30,
        'max_party' => 12,
    ],

    // Shown to guests when they look at / pick a VIP table. Edit the wording to match your real offer.
    'vip' => [
        'title' => 'VIP lounge',
        'blurb' => 'Private upstairs seating for celebrations and special evenings.',
        'perks' => ['Private lounge on the 2nd floor', 'Larger tables for groups', 'Tell us your occasion in the notes and we will prepare for it'],
    ],

    'vouchers' => [
        'gift_min' => 10,
        'gift_max' => 500,
        'gift_presets' => [25, 50, 100],
        // Gift vouchers are issued without an expiry date (many Canadian provinces restrict gift-card expiry;
        // confirm the current Alberta rules before adding one).
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
