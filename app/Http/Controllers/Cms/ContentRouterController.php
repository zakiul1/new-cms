<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SlugHistory;
use Illuminate\Http\Request;

class ContentRouterController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $slug = trim($slug, '/');
        $path = $slug === '' ? '/' : '/' . $slug;

        // ✅ Optional: normalize trailing slash (premium)
        // If your site standard is WITHOUT trailing slash:
        if ($path !== '/' && str_ends_with($request->getPathInfo(), '/')) {
            return redirect()->to(rtrim($request->getPathInfo(), '/'), 301);
        }

        $now = now();

        // 1) Find published page first
        $page = Post::query()
            ->where('type', 'page')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();

        if ($page) {
            return view('page', [
                'post' => $page,
                'seo' => $this->buildSeo($page, $request),
            ]);
        }

        // 2) Then find published post
        $post = Post::query()
            ->where('type', 'post')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();

        if ($post) {
            return view('post', [
                'post' => $post,
                'seo' => $this->buildSeo($post, $request),
            ]);
        }

        // 3) ✅ Slug history fallback (if redirect row missing)
        $history = SlugHistory::query()
            ->where('entity_type', 'post')
            ->where('old_slug', $slug)
            ->latest('id')
            ->first();

        if ($history) {
            $current = Post::query()->find($history->entity_id);
            if ($current && $current->status === 'published') {
                $to = '/' . ltrim((string) $current->slug, '/');

                // ✅ auto-heal: ensure Redirect row exists
                Redirect::query()->updateOrCreate(
                    ['from_path' => $path],
                    ['to_path' => $to, 'status_code' => 301]
                );

                return redirect()->to($to, 301);
            }
        }

        abort(404);
    }

    private function buildSeo(Post $post, Request $request): array
    {
        $meta = is_array($post->meta_json) ? $post->meta_json : [];
        $seo = isset($meta['seo']) && is_array($meta['seo']) ? $meta['seo'] : [];

        $title = trim((string) ($seo['title'] ?? $post->title ?? config('app.name')));
        $desc = trim((string) ($seo['description'] ?? $post->excerpt ?? ''));

        $canonical = trim((string) ($seo['canonical'] ?? ''));
        if ($canonical === '') {
            $canonical = url('/' . ltrim((string) $post->slug, '/'));
        }

        $robots = trim((string) ($seo['robots'] ?? ''));
        if ($robots === '') {
            $robots = 'index, follow';
        }

        $ogImage = trim((string) ($seo['og_image'] ?? ''));

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $robots,

            'og' => [
                'title' => $title,
                'description' => $desc,
                'type' => $post->type === 'page' ? 'website' : 'article',
                'url' => $canonical,
                'image' => $ogImage, // optional
            ],
        ];
    }

}