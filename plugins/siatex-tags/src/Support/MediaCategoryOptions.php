<?php

namespace Plugins\SiatexTags\Support;

use App\Models\Taxonomy;
use App\Models\Term;

class MediaCategoryOptions
{
    public static function options(): array
    {
        $taxonomyId = Taxonomy::query()
            ->where('key', 'media_category')
            ->value('id');

        if (!$taxonomyId) {
            return [];
        }

        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        // build children map
        $children = [];
        foreach ($terms as $t) {
            $parent = (int) ($t->parent_id ?? 0);
            $children[$parent][] = $t;
        }

        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $children) {
            foreach ($children[$parentId] ?? [] as $term) {
                $prefix = str_repeat('— ', $depth);
                $out[$term->id] = $prefix . $term->name;
                $walk((int) $term->id, $depth + 1);
            }
        };

        // start from root parent_id = 0
        $walk(0, 0);

        // Some DBs use null for root, include that too
        $walk(0, 0);

        return $out;
    }
}