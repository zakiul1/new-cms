<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Http\Request;

class TermArchiveController extends Controller
{
    public function products(Request $request, string $slug)
    {
        $slug = trim($slug, '/');
        $now = now();

        $taxonomyId = Taxonomy::query()->where('key', 'category')->value('id');

        $term = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('slug', $slug)
            ->firstOrFail();

        // ✅ "Products" = posts where category is this term
        $posts = Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->whereHas('categories', fn ($q) => $q->whereKey($term->id))
            ->with([
                'featuredMedia.variantRecords', // for srcset
            ])
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $canonical = url('/products/' . $term->slug);

        $seo = [
            'title' => $term->name . ' - Products',
            'description' => $term->description ?: null,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'og' => [
                'title' => $term->name . ' - Products',
                'description' => $term->description ?: null,
                'type' => 'website',
                'url' => $canonical,
            ],
        ];

        return view('archive', [
            'term' => $term,
            'posts' => $posts,
            'seo' => $seo,
        ]);
    }
}
