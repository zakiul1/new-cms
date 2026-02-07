<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * IMPORTANT (cPanel shared hosting):
         * Store public uploads inside /public_html/storage (no symlink required)
         */
        'public' => [
            'driver' => 'local',

            // /home/USER/laravel-app -> ../public_html/storage => /home/USER/public_html/storage
            'root' => base_path('../public_html/storage'),

            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',

            // Keep these same
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
     * You won’t need storage:link anymore with this approach,
     * but keep links here harmlessly.
     */
    'links' => [
        public_path('storage') => base_path('../public_html/storage'),
    ],

];
