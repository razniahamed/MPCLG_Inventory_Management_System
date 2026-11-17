<?php

return [

    /*
    | Ministry Inventory & Asset Management System settings.
    | Runtime-adjustable values live in the system_settings table; these are
    | the fallbacks used when a setting row is missing.
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Colombo'),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'bootstrap_admin' => [
        'username' => env('ADMIN_USERNAME', 'sysadmin'),
        'email' => env('ADMIN_EMAIL', 'sysadmin@mpclg.local'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'pagination' => [
        'default_per_page' => 25,
        'max_per_page' => 100,
    ],

    // Cache lifetime for resolved permission sets (seconds). Cache keys include
    // users.permission_version, so changes take effect immediately anyway.
    'permission_cache_ttl' => 3600,
];
