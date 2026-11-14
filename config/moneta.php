<?php

return [
    'default_profile' => env('MONETA_PROFILE', 'default'),
    'profiles' => [
        // 'firma-b' => ['api_token' => env('MONETA_FIRMA_B_TOKEN'), 'sandbox' => false, 'identification_number' => env('MONETA_FIRMA_B_ID')],
    ],
    'sandbox' => env('MONETA_SANDBOX', true),
    'api_token' => env('MONETA_API_TOKEN'),
    'client_id' => env('MONETA_CLIENT_ID'),
    'client_secret' => env('MONETA_CLIENT_SECRET'),
    'oauth_scope' => env('MONETA_OAUTH_SCOPE'),
    'oauth_token_url' => env('MONETA_OAUTH_TOKEN_URL', 'https://api.moneta.cz/auth/oauth/v2/token'),
    'application_name' => env('MONETA_APPLICATION_NAME', 'Laravel Package'),
    'identification_number' => env('MONETA_IDENTIFICATION_NUMBER'),
    'business_name' => env('MONETA_BUSINESS_NAME'),
    'base_url' => env('MONETA_BASE_URL', 'https://api.moneta.cz'),
    'timeout' => 15,
    'retry' => 3,
    'retry_sleep' => 100,
    'retry_max_sleep' => 5000,
];
