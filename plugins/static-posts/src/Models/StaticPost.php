<?php

namespace Plugins\StaticPosts\Models;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class StaticPost extends Post
{
    protected $table = 'posts';

    protected static function booted(): void
    {
        parent::booted();

        // Always scope this model to static posts only
        static::addGlobalScope('static_post_type', function (Builder $query) {
            $query->where('type', 'static_post');
        });
    }

    /**
     * IMPORTANT:
     * Force morph type to base Post model so termables.termable_type is consistent.
     * This fixes Static Category "Items" counting + term relationships.
     */
    public function getMorphClass(): string
    {
        return \App\Models\Post::class;
    }

    /**
     * Static categories only (taxonomy key = static_category)
     */
    public function categories(): MorphToMany
    {
        return $this->terms()
            ->whereHas('taxonomy', fn($q) => $q->where('key', 'static_category'));
    }
}