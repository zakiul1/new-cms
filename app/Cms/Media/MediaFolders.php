<?php

namespace App\Cms\Media;

use App\Models\Taxonomy;

class MediaFolders
{
    public static function taxonomyId(): int
    {
        return (int) Taxonomy::firstOrCreate(
            ['key' => 'media_folder'],
            ['label' => 'Media Folders', 'hierarchical' => true],
        )->id;
    }
}