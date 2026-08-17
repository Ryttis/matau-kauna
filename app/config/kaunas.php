<?php

return [
    'map_center' => [
        'lat' => 54.8985,
        'lng' => 23.9036,
    ],

    // Turi sutapti su KAUNAS_BOUNDS resources/js/utils/leafletKaunas.js.
    'bounds' => [
        'min_lat' => 54.75,
        'min_lng' => 23.65,
        'max_lat' => 55.05,
        'max_lng' => 24.15,
    ],

    'address' => [
        'max_length' => 255,
        'min_length' => 5,
    ],

    'geocoder' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'MatauKaune/1.0 (+https://mataukaune.kaunas.lt)'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 6),
        'cache_ttl' => (int) env('NOMINATIM_CACHE_TTL', 604800),
        // Kauno rajono savivaldybė yra Kauno miesto ribų staciakampio viduje,
        // todėl vien koordinačių ribų nepakanka – reikia display_name patikros.
        'district_marker' => 'rajono savivaldyb',
    ],

    'mobile_session_lifetime_days' => (int) env('MOBILE_SESSION_LIFETIME_DAYS', 90),
];
