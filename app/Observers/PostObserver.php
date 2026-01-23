<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Redirect;
use App\Models\SlugHistory;
use Illuminate\Support\Facades\Auth;

class PostObserver
{
    public function updating(Post $post): void
    {
        // Create a revision if key content fields change (Phase 2 requirement) :contentReference[oaicite:7]{index=7}
        if ($post->isDirty(['title', 'content_json'])) {
            PostRevision::query()->create([
                'post_id' => $post->id,
                'title' => (string) $post->getOriginal('title'),
                'content_json' => $post->getOriginal('content_json'),
                'author_id' => Auth::id(),
            ]);
        }

        // If slug changed, store history + redirect (blueprint slug history + redirect models) :contentReference[oaicite:8]{index=8}
        if ($post->isDirty('slug')) {
            $oldSlug = (string) $post->getOriginal('slug');

            SlugHistory::query()->create([
                'entity_type' => 'post',
                'entity_id' => $post->id,
                'old_slug' => $oldSlug,
            ]);

            // Redirect old path -> new path
            $from = '/' . ltrim($oldSlug, '/');
            $to = '/' . ltrim((string) $post->slug, '/');

            Redirect::query()->updateOrCreate(
                ['from_path' => $from],
                ['to_path' => $to, 'status_code' => 301]
            );
        }
    }
}