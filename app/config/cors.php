<?php

$allowedOrigins = array_values(array_filter(array_map(
    static fn ($v) => trim((string) $v),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8081,http://127.0.0.1:8081'))
)));

$defaultOriginPatterns = [
    '#^https?://localhost(:\d+)?$#',
    '#^https?://127\.0\.0\.1(:\d+)?$#',
    '#^https?://[a-zA-Z0-9.-]+\.local(:\d+)?$#',
];
$extraOriginPatterns = array_values(array_filter(array_map(
    static fn ($v) => trim((string) $v),
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
)));

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => array_values(array_unique([
        ...$defaultOriginPatterns,
        ...$extraOriginPatterns,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
