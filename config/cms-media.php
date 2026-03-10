<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('CMS_MEDIA_DISK', 'public'),

    // Stored as: media/YYYY/MM
    'base_dir' => env('CMS_MEDIA_BASE_DIR', 'media'),

    'max_upload_mb' => (int) env('CMS_MEDIA_MAX_UPLOAD_MB', 50),

    /*
    |--------------------------------------------------------------------------
    | Originals
    |--------------------------------------------------------------------------
    | Keep original uploads so variants can be regenerated later.
    */
    'keep_original' => env('CMS_MEDIA_KEEP_ORIGINAL', true),

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    */
    'dedupe' => env('CMS_MEDIA_DEDUPE', true),

    /*
    |--------------------------------------------------------------------------
    | Image processing
    |--------------------------------------------------------------------------
    */
    'image_variants' => [
        // Small cards / logos / tiny grids
        'thumb' => 275,

        // Small content / compact cards
        'small' => 370,

        // Better for mobile hero/LCP images
        'hero_sm' => 575,

        // Large desktop content image / hero
        'large' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated formats
    |--------------------------------------------------------------------------
    | Main generated format for image variants.
    */
    'variant_format' => env('CMS_MEDIA_VARIANT_FORMAT', 'webp'),

    /*
    |--------------------------------------------------------------------------
    | Optional JPEG fallback
    |--------------------------------------------------------------------------
    | true  = also generate jpeg fallback variants
    | false = generate only the primary format
    |
    | Set default true so both webp + jpg are generated even if env is missing.
    */
    'generate_jpeg_fallback' => env('CMS_MEDIA_GENERATE_JPEG_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Optional: Generate AVIF too
    |--------------------------------------------------------------------------
    */
    'generate_avif' => env('CMS_MEDIA_GENERATE_AVIF', false),

    /*
    |--------------------------------------------------------------------------
    | Quality / Optimization
    |--------------------------------------------------------------------------
    */
    'quality' => [
        'webp' => (int) env('CMS_MEDIA_WEBP_QUALITY', 76),
        'avif' => (int) env('CMS_MEDIA_AVIF_QUALITY', 45),
        'jpeg' => (int) env('CMS_MEDIA_JPEG_QUALITY', 80),
        'png' => (int) env('CMS_MEDIA_PNG_QUALITY', 85),
    ],

    // Removes EXIF (camera gps etc) from processed variants
    'strip_metadata' => env('CMS_MEDIA_STRIP_METADATA', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Processing
    |--------------------------------------------------------------------------
    | true = upload returns fast, variants generated in background.
    | false = generate variants synchronously during upload.
    */
    'queue' => [
        'enabled' => env('CMS_MEDIA_QUEUE_ENABLED', true),
        'connection' => env('CMS_MEDIA_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queue' => env('CMS_MEDIA_QUEUE_NAME', 'media'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxonomy integration for folders
    |--------------------------------------------------------------------------
    */
    'folder_taxonomy_key' => env('CMS_MEDIA_FOLDER_TAXONOMY_KEY', 'media_folder'),

    /*
    |--------------------------------------------------------------------------
    | Filename / Title Keyword Canonicalization
    |--------------------------------------------------------------------------
    */
    'filename_keyword_canonical' => [
        'EU',

        'of',
        'for',
        'the',
        'in',
        'on',
        'at',
        'to',
        'by',
        'with',
        'about',
        'against',
        'and',
        'between',
        'into',
        'through',
        'during',
        'before',
        'after',
        'above',
        'below',
        'from',
        'up',
        'down',
        'off',
        'over',
        'under',
        'again',
        'further',
        'then',
        'once',

        'USA',
        'UK',
        'UAE',

        'T-shirt',
        'T-shirts',
        'v-neck',

        'AL',
        'AK',
        'AZ',
        'AR',
        'CA',
        'CO',
        'CT',
        'DE',
        'FL',
        'GA',
        'HI',
        'ID',
        'IL',
        'IA',
        'KS',
        'KY',
        'LA',
        'ME',
        'MD',
        'MA',
        'MI',
        'MN',
        'MS',
        'MO',
        'MT',
        'NE',
        'NV',
        'NH',
        'NJ',
        'NM',
        'NY',
        'NC',
        'ND',
        'OH',
        'OK',
        'OR',
        'PA',
        'RI',
        'SC',
        'SD',
        'TN',
        'TX',
        'UT',
        'VT',
        'VA',
        'WA',
        'WV',
        'WI',
        'WY',
        'DC',
        'PR',
        'US',

        'Made in',
        'in Bangladesh',

        'OEM',
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyword separators
    |--------------------------------------------------------------------------
    */
    'filename_keyword_separators' => [' ', '-', '_'],
];