<?php

namespace App\Cms\Search;

use App\Models\Post;
use App\Models\SearchDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SearchIndex
{
    public function upsertPost(Post $post): void
    {
        // only index public-ish content
        $isPublished = $post->status === 'published';
        $publishedAt = $post->published_at;

        $isPublic = $isPublished; // you can extend this later

        $entityType = $post->type === 'page' ? 'page' : 'post';

        $url = $this->permalink($post);

        // Build indexable text from content_json
        $content = $this->extractTextFromContentJson($post->content_json);

        // Add excerpt too
        $excerpt = trim((string) $post->excerpt);
        if ($excerpt !== '') {
            $content = trim($excerpt . "\n\n" . $content);
        }

        SearchDocument::query()->updateOrCreate(
            ['entity_type' => $entityType, 'entity_id' => $post->id],
            [
                'title' => (string) ($post->title ?? ''),
                'content' => $content,
                'slug' => (string) ($post->slug ?? ''),
                'url' => $url,
                'meta' => [
                    'post_id' => $post->id,
                    'type' => $post->type,
                ],
                'is_public' => $isPublic,
                'published_at' => $publishedAt,
            ]
        );
    }

    public function deletePost(Post $post): void
    {
        $entityType = $post->type === 'page' ? 'page' : 'post';

        SearchDocument::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $post->id)
            ->delete();
    }

    /**
     * Premium search: supports FULLTEXT if available, else LIKE fallback.
     *
     * @return array{items: array<int,SearchDocument>, total:int}
     */
    public function search(string $q, int $page = 1, int $perPage = 10, ?string $type = null): array
    {
        $q = trim($q);
        $page = max(1, (int) $page);
        $perPage = max(1, min(50, (int) $perPage));

        if ($q === '') {
            return ['items' => [], 'total' => 0];
        }

        $builder = SearchDocument::query()
            ->where('is_public', true);

        if ($type && in_array($type, ['post', 'page'], true)) {
            $builder->where('entity_type', $type);
        }

        // MySQL FULLTEXT if present, else LIKE
        $driver = config('database.default');
        $db = config("database.connections.$driver.driver");

        if (in_array($db, ['mysql', 'mariadb'], true)) {
            // FULLTEXT query (requires fulltext index)
            // If you didn’t add FULLTEXT in migration, comment this and use LIKE.
            $builder->whereRaw("MATCH(title, content) AGAINST (? IN BOOLEAN MODE)", [$this->toBooleanQuery($q)]);
            $builder->orderByRaw("MATCH(title, content) AGAINST (? IN BOOLEAN MODE) DESC", [$this->toBooleanQuery($q)]);
        } else {
            // LIKE fallback
            $builder->where(function (Builder $qq) use ($q) {
                $qq->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
            $builder->orderByDesc('published_at');
        }

        $total = (clone $builder)->count();

        $items = $builder
            ->forPage($page, $perPage)
            ->get()
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    private function toBooleanQuery(string $q): string
    {
        // simple boolean-mode builder: "hello world" => "+hello +world*"
        $tokens = array_values(array_filter(preg_split('/\s+/', strtolower($q)) ?: []));
        $tokens = array_map(fn($t) => preg_replace('/[^a-z0-9_\-]/', '', $t), $tokens);
        $tokens = array_values(array_filter($tokens));

        if ($tokens === [])
            return '';

        return implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens));
    }

    private function extractTextFromContentJson($contentJson): string
    {
        // You store blocks JSON; we’ll flatten to plain text.
        if (is_string($contentJson)) {
            return trim($contentJson);
        }

        if (!is_array($contentJson)) {
            return '';
        }

        // common case: page uses content_json['html']
        if (isset($contentJson['html']) && is_string($contentJson['html'])) {
            return trim(strip_tags($contentJson['html']));
        }

        // Otherwise flatten everything
        $text = json_encode($contentJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $text = strip_tags($text);
        return trim($text);
    }

    private function permalink(Post $post): string
    {
        $base = rtrim(config('app.url'), '/');
        $slug = ltrim((string) $post->slug, '/');

        // IMPORTANT: match your routing rules
        if ($post->type === 'page') {
            return $base . '/' . $slug;
        }

        // if you do posts at /{slug}, change this:
        return $base . '/' . $slug;
        // return $base . '/blog/' . $slug;
    }
}