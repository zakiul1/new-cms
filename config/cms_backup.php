<?php

return [
    'disk' => env('CMS_BACKUP_DISK', 'local'),
    'path' => env('CMS_BACKUP_PATH', 'cms-backups'),
    'keep_last' => (int) env('CMS_BACKUP_KEEP_LAST', 14),
    'include_public_storage' => true,

    // IMPORTANT: order matters for restore (parents -> pivots -> tools/history)
    'tables' => [
        // --- Core content
        'posts',
        'post_revisions',

        // --- Taxonomy system
        'taxonomies',
        'terms',
        'termables',      // pivot: termable_id + term_id (no id)

        // --- Media library
        'media',
        'media_variants',
        'media_term',     // pivot
        'post_media',     // pivot

        // --- Menus
        'menus',
        'menu_items',

        // --- Widgets
        'widget_areas',
        'widgets',
        'widget_placements',

        // --- SEO / Redirects
        'redirects',
        'slug_histories',
    ],
];