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
    'paykita' => [
        'base_url' => env('PAYKITA_BASE_URL', 'https://paykita.biz.id'),
        'api_key' => env('PAYKITA_API_KEY'),
        'ttl_seconds' => (int) env('PAYKITA_TTL_SECONDS', 600),
    ],
];
