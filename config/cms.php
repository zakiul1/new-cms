<?php

return [
    'themes_path' => base_path('themes'),
    'themes_public_path' => public_path('themes'),
    'zip_tmp_path' => storage_path('app/cms/tmp'),

    // guardrails
    'theme_slug_regex' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // letters, numbers, dashes :contentReference[oaicite:2]{index=2}
    'max_zip_size_bytes' => 50 * 1024 * 1024,            // 50MB
    'max_zip_files' => 5000,
    'max_uncompressed_bytes' => 200 * 1024 * 1024,       // 200MB


    'response_cache' => [
        'enabled' => env('CMS_RESPONSE_CACHE', false),
        'ttl_seconds' => env('CMS_RESPONSE_CACHE_TTL', 300),

        // Prefix matches (start of URL path)
        'exclude_paths' => [
            '/filament',
            '/admin',
            '/api',
            '/livewire',
            '/storage',
            '/themes',
            '/plugins',
        ],
    ],

];