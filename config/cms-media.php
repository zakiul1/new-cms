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
    | WordPress keeps originals, and generates sizes.
    | Keep this enabled for best UX + future re-generation.
    */
    'keep_original' => env('CMS_MEDIA_KEEP_ORIGINAL', true),

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    | If enabled, we compute sha1 and avoid storing duplicates
    | (or you can decide to allow duplicates but reuse file).
    */
    'dedupe' => env('CMS_MEDIA_DEDUPE', true),

    /*
    |--------------------------------------------------------------------------
    | Image processing
    |--------------------------------------------------------------------------
    */
    'image_variants' => [
        // width in px (height auto)
        'thumb' => 300,
        'medium' => 768,

        // WP-like: between medium and large
        'medium_large' => 1024,

        'large' => 1600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated formats
    |--------------------------------------------------------------------------
    | Main output format for generated variants.
    | Recommended: webp
    */
    'variant_format' => env('CMS_MEDIA_VARIANT_FORMAT', 'webp'),

    /*
    |--------------------------------------------------------------------------
    | Optional JPEG fallback
    |--------------------------------------------------------------------------
    | false = generate only the primary format (for example only webp)
    | true  = also generate jpeg fallback variants
    */
    'generate_jpeg_fallback' => env('CMS_MEDIA_GENERATE_JPEG_FALLBACK', false),

    /*
    |--------------------------------------------------------------------------
    | Optional: Generate AVIF too (best compression, slower)
    |--------------------------------------------------------------------------
    */
    'generate_avif' => env('CMS_MEDIA_GENERATE_AVIF', false),

    /*
    |--------------------------------------------------------------------------
    | Quality / Optimization
    |--------------------------------------------------------------------------
    */
    'quality' => [
        'webp' => (int) env('CMS_MEDIA_WEBP_QUALITY', 82),
        'avif' => (int) env('CMS_MEDIA_AVIF_QUALITY', 50),
        'jpeg' => (int) env('CMS_MEDIA_JPEG_QUALITY', 85),
        'png' => (int) env('CMS_MEDIA_PNG_QUALITY', 90),
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
    | We'll store "folders" as terms under this taxonomy key.
    | (hierarchical like WP)
    */
    'folder_taxonomy_key' => env('CMS_MEDIA_FOLDER_TAXONOMY_KEY', 'media_folder'),

    /*
    |--------------------------------------------------------------------------
    | Filename / Title Keyword Canonicalization
    |--------------------------------------------------------------------------
    | One canonical keyword per entry. Matching should be case-insensitive,
    | but replacement output should use exactly the string here.
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
    | Treat these characters as equivalent separators while matching keywords.
    | Example: v neck / v-neck / v_neck => matches "v-neck"
    */
    'filename_keyword_separators' => [' ', '-', '_'],
];