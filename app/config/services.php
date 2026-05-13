<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'viisp' => [
        'gateway_url' => rtrim((string) env('VIISP_GATEWAY_URL', 'http://127.0.0.1:3000'), '/'),
        'gateway_postback_base' => rtrim(
            (string) env('VIISP_GATEWAY_POSTBACK_BASE', 'http://127.0.0.1:3000/gateway/viisp/postback'),
            '/'
        ),
        'exchange_secret' => env('VIISP_EXCHANGE_SECRET'),
    ],

    'dvs_kontora' => [
        'base_url' => rtrim((string) env('DVS_KONTORA_BASE_URL', 'https://kontorat-api2.kaunas.lt'), '/'),
        'client_id' => env('DVS_KONTORA_CLIENT_ID'),
        'client_secret' => env('DVS_KONTORA_CLIENT_SECRET'),
        'username' => env('DVS_KONTORA_USERNAME'),
        'password' => env('DVS_KONTORA_PASSWORD'),
        'ad_domain_id' => env('DVS_KONTORA_AD_DOMAIN_ID'),
        'pilieciu_laiskai' => [
            'registro_id' => env('DVS_KONTORA_PILIECIAI_REGISTRO_ID'),
            'registro_kodas' => env('DVS_KONTORA_PILIECIAI_REGISTRO_KODAS'),
            'nukreipto_id' => env('DVS_KONTORA_PILIECIAI_NUKREIPTO_ID'),
        ],
    ],

];
