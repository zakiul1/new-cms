<?php

namespace App\Observers;

use App\Cms\Search\SearchIndex;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

class PostSearchObserver
{
    public function saved(Post $post): void
    {
        DB::afterCommit(function () use ($post) {
            app(SearchIndex::class)->upsertPost($post);
        });
    }

    public function deleted(Post $post): void
    {
        DB::afterCommit(function () use ($post) {
            app(SearchIndex::class)->deletePost($post);
        });
    }
}