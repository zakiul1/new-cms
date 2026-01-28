<?php

namespace App\Cms\Menus;

use App\Models\Post;
use App\Models\Term;
use Illuminate\Support\Facades\Schema;

class MenuItemFactory
{
    public function fromCustomLink(string $label, string $url): array
    {
        return [
            'type' => 'custom_url',
            'object_type' => 'custom',
            'label' => $label,
            'url' => $url,
            'is_enabled' => true,
        ];
    }

    public function fromPost(Post $post): array
    {
        $label = $post->title ?? $post->name ?? ('Post #' . $post->getKey());

        // Generic permalink (you can improve later via your ContentRouter/permalink service)
        $slug = $post->slug ?? null;
        $url = $slug ? '/' . ltrim($slug, '/') : '/';

        return [
            'type' => 'post',
            'object_type' => 'post',
            'object_id' => (int) $post->getKey(),
            'label' => (string) $label,
            'url' => (string) $url,
            'is_enabled' => true,
        ];
    }

    public function fromTerm(Term $term, ?string $taxonomyKey = null): array
    {
        $label = $term->name ?? ('Term #' . $term->getKey());
        $slug = $term->slug ?? null;

        // Generic URL; improve later if you have taxonomy routes
        $url = $slug ? '/' . ltrim($slug, '/') : '/';

        return [
            'type' => 'taxonomy_term',
            'object_type' => 'term',
            'object_id' => (int) $term->getKey(),
            'taxonomy_key' => $taxonomyKey,
            'label' => (string) $label,
            'url' => (string) $url,
            'is_enabled' => true,
        ];
    }

    public function detectPostTypeColumn(): ?string
    {
        // Supports either `post_type` or `type`
        if (Schema::hasColumn('posts', 'post_type')) {
            return 'post_type';
        }
        if (Schema::hasColumn('posts', 'type')) {
            return 'type';
        }
        return null;
    }
}