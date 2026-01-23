<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Http\Request;

class ContentRouterController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $path = '/' . ltrim($slug, '/');

        // 1) Redirects first
        if ($redirect = Redirect::query()->where('from_path', $path)->first()) {
            return redirect($redirect->to_path, (int) $redirect->status_code);
        }

        // 2) Find published page or post (pages first, then posts)
        $now = now();

        $page = Post::query()
            ->where('type', 'page')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();

        if ($page) {
            return view('page', ['post' => $page]);
        }

        $post = Post::query()
            ->where('type', 'post')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();

        if ($post) {
            return view('post', ['post' => $post]);
        }

        abort(404);
    }
}