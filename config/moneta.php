<?php

return [
    'sandbox' => env('MONETA_SANDBOX', true),
    'api_token' => env('MONETA_API_TOKEN'),
    'application_name' => env('MONETA_APPLICATION_NAME', 'Laravel Package'),
    'identification_number' => env('MONETA_IDENTIFICATION_NUMBER'),
    'business_name' => env('MONETA_BUSINESS_NAME'),
    'base_url' => env('MONETA_BASE_URL', 'https://api.moneta.cz'),
    'timeout' => 15,
    'retry' => 3,
];
