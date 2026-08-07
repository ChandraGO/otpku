<?php

return [
    'sms_virtual' => [
        'base_url' => env('SMS_VIRTUAL_BASE_URL', 'https://api.sms-virtuals.net'),
        'api_key' => env('SMS_VIRTUAL_API_KEY'),
        'timeout' => (int) env('SMS_VIRTUAL_TIMEOUT', 30),
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],
    'pakasir' => [
        'base_url' => env('PAKASIR_BASE_URL', 'https://app.pakasir.com'),
        'project' => env('PAKASIR_PROJECT'),
        'api_key' => env('PAKASIR_API_KEY'),
        'method' => env('PAKASIR_PAYMENT_METHOD', 'qris'),
    ],
];
