<?php

return [
    'disk' => env('CMS_MEDIA_DISK', 'public'),

    'base_dir' => 'media', // media/YYYY/MM

    'max_upload_mb' => 50,

    'image_variants' => [
        'thumb' => 300,
        'medium' => 768,
        'large' => 1600,
    ],

    // format for generated variants
    'variant_format' => 'webp',
];
