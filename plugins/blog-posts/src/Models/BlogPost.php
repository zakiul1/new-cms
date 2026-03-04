<?php

namespace Plugins\BlogPosts\Models;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class BlogPost extends Post
{
    protected $table = 'posts';

    protected static function booted(): void
    {
        parent::booted();

        // Always scope this model to blog posts only
        static::addGlobalScope('blog_post_type', function (Builder $builder) {
            $builder->where('type', 'blog_post');
        });
    }

    /**
     * IMPORTANT:
     * Keep polymorphic type same as core Post model,
     * so termables.termable_type remains consistent.
     */
    public function getMorphClass(): string
    {
        return Post::class;
    }

    /**
     * Blog Categories (taxonomy key = blog_category)
     */
    public function categories(): MorphToMany
    {
        return $this->terms()
            ->whereHas('taxonomy', function ($q) {
                $q->where('key', 'blog_category');
            });
    }
}