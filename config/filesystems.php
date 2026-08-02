<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => true, 'throw' => false, 'report' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false, 'report' => false],
        'backups' => ['driver' => 'local', 'root' => storage_path('app/backups'), 'throw' => true],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
