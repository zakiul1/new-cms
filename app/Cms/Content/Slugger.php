<?php

namespace App\Cms\Content;

use App\Models\Post;
use Illuminate\Support\Str;

class Slugger
{
    public function uniquePostSlug(string $type, string $title, ?int $ignorePostId = null): string
    {
        return $this->uniqueFromBase($type, Str::slug($title) ?: 'item', $ignorePostId);
    }

    public function uniqueFromSlug(string $type, string $slug, ?int $ignorePostId = null): string
    {
        return $this->uniqueFromBase($type, Str::slug($slug) ?: 'item', $ignorePostId);
    }

    private function uniqueFromBase(string $type, string $base, ?int $ignorePostId): string
    {
        $slug = $base;
        $i = 2;

        while ($this->exists($type, $slug, $ignorePostId)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function exists(string $type, string $slug, ?int $ignorePostId): bool
    {
        $q = Post::query()->where('type', $type)->where('slug', $slug);

        if ($ignorePostId) {
            $q->where('id', '!=', $ignorePostId);
        }

        return $q->exists();
    }
}