<?php

namespace App\Support;

use App\Models\Media;

class CmsImage
{
    public static function srcset(Media $media): string
    {
        $variants = $media->variantRecords()
            ->orderBy('width')
            ->get(['width', 'height', 'disk', 'directory', 'filename', 'mime_type']);

        $items = [];

        foreach ($variants as $v) {
            if (!$v->width) continue;
            $items[] = $v->url() . ' ' . ((int) $v->width) . 'w';
        }

        // include original as last
        if ($media->width) {
            $items[] = $media->url() . ' ' . ((int) $media->width) . 'w';
        }

        return implode(', ', array_values(array_unique($items)));
    }
}
