<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;

class CategoryArchiveController extends Controller
{
    public function show(string $slug)
    {
        $taxonomyId = Taxonomy::query()->where('key', 'category')->value('id');
        abort_unless($taxonomyId, 404);

        $term = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->whereHas('categories', fn ($q) => $q->whereKey($term->id))
            ->latest('id')
            ->paginate(18);

        return view('archive', [
            'title' => $term->name,
            'term' => $term,
            'posts' => $posts,
        ]);
    }
}
