<?php

namespace App\Cms\Content;

use App\Models\Post;
use Illuminate\Support\Str;

class Slugger
{
    /**
     * These content types share the same frontend URL namespace: /{slug}
     * Add more here later if you introduce new routable types.
     *
     * @var string[]
     */
    private array $globalTypes = ['post', 'page'];

    /**
     * Backward compatible:
     * - still accepts $type, but uniqueness is enforced globally across post+page.
     */
    public function uniquePostSlug(string $type, string $title, ?int $ignorePostId = null): string
    {
        return $this->uniqueFromBase($type, Str::slug($title) ?: 'item', $ignorePostId);
    }

    /**
     * Backward compatible:
     * - still accepts $type, but uniqueness is enforced globally across post+page.
     */
    public function uniqueFromSlug(string $type, string $slug, ?int $ignorePostId = null): string
    {
        return $this->uniqueFromBase($type, Str::slug($slug) ?: 'item', $ignorePostId);
    }

    /**
     * New (recommended): does not require passing a type.
     */
    public function uniqueGlobalSlugFromTitle(string $title, ?int $ignorePostId = null): string
    {
        return $this->uniqueFromBase('global', Str::slug($title) ?: 'item', $ignorePostId);
    }

    /**
     * New (recommended): does not require passing a type.
     */
    public function uniqueGlobalSlugFromSlug(string $slug, ?int $ignorePostId = null): string
    {
        return $this->uniqueFromBase('global', Str::slug($slug) ?: 'item', $ignorePostId);
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
        // ✅ Global namespace for frontend routing
        // Pages and posts share URLs, so slugs must be unique across these types.
        $q = Post::query()
            ->whereIn('type', $this->globalTypes)
            ->where('slug', $slug);

        if ($ignorePostId) {
            $q->where('id', '!=', $ignorePostId);
        }

        return $q->exists();
    }
}