<?php

use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/_customizer/preview/post', function () {
    $post = Post::query()
        ->where('type', 'post')
        ->where('status', 'published')
        ->latest('id')
        ->first();

    abort_if(!$post, 404, 'No published post found.');

    // ✅ Your CMS uses catch-all slug route
    return redirect()->to(url($post->slug));
});

Route::get('/_customizer/preview/page', function () {
    $page = Post::query()
        ->where('type', 'page')
        ->where('status', 'published')
        ->latest('id')
        ->first();

    abort_if(!$page, 404, 'No published page found.');

    // ✅ Your CMS uses catch-all slug route
    return redirect()->to(url($page->slug));
});