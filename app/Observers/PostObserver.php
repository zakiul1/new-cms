<?php

namespace App\Observers;

use App\Cms\Content\PermalinkManager;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Redirect;
use App\Models\SlugHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostObserver
{
    public function updating(Post $post): void
    {
        $needsRevision = $post->isDirty(['title', 'content_json']);
        $slugChanged = $post->isDirty('slug');

        $oldTitle = (string) $post->getOriginal('title');
        $oldContent = $post->getOriginal('content_json');

        $oldSlug = (string) $post->getOriginal('slug');
        $newSlug = (string) $post->slug;

        $type = (string) ($post->type ?? 'post'); // post|page

        DB::afterCommit(function () use ($post, $needsRevision, $slugChanged, $oldTitle, $oldContent, $oldSlug, $newSlug, $type) {
            // 1) Revision
            if ($needsRevision) {
                PostRevision::query()->create([
                    'post_id' => $post->id,
                    'title' => $oldTitle,
                    'content_json' => $oldContent,
                    'author_id' => Auth::id(),
                ]);
            }

            // 2) Slug history + redirect
            if (!$slugChanged) {
                return;
            }

            $oldSlug = trim($oldSlug, '/');
            $newSlug = trim($newSlug, '/');

            if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
                return;
            }

            SlugHistory::query()->firstOrCreate([
                'entity_type' => $type,
                'entity_id' => $post->id,
                'old_slug' => $oldSlug,
            ]);

            $permalinks = app(PermalinkManager::class);

            $from = $type === 'page'
                ? $permalinks->pagePath($post, $oldSlug)
                : $permalinks->postPath($post, $oldSlug);

            $to = $type === 'page'
                ? $permalinks->pagePath($post, $newSlug)
                : $permalinks->postPath($post, $newSlug);

            if ($from === $to) {
                return;
            }

            Redirect::query()->updateOrCreate(
                ['from_path' => $from],
                ['to_path' => $to, 'status_code' => 301]
            );
        });
    }
}