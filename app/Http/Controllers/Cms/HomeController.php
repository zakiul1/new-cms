<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $now = now();

        // ✅ WP-like: homepage content from a Page named "home"
        $home = Post::query()
            ->where('type', 'page')
            ->where('slug', 'home')
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();

        $seo = [
            'title' => $home?->title ?? config('app.name'),
            'description' => $home?->excerpt ?? null,
            'canonical' => url('/'),
            'robots' => 'index, follow',
            'og' => [
                'title' => $home?->title ?? config('app.name'),
                'description' => $home?->excerpt ?? null,
                'type' => 'website',
                'url' => url('/'),
            ],
        ];

        return view('home', [
            'home' => $home,
            'seo' => $seo,
        ]);
    }
}
