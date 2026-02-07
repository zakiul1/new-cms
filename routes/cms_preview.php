<?php

use App\Cms\Content\PermalinkManager;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/_customizer/preview/post', function (PermalinkManager $permalinks) {
    $post = Post::query()
        ->where('type', 'post')
        ->where('status', 'published')
        ->latest('id')
        ->first();

    abort_if(!$post, 404, 'No published post found.');

    return redirect()->to($permalinks->postUrl($post));
});

Route::get('/_customizer/preview/page', function (PermalinkManager $permalinks) {
    $page = Post::query()
        ->where('type', 'page')
        ->where('status', 'published')
        ->latest('id')
        ->first();

    abort_if(!$page, 404, 'No published page found.');

    return redirect()->to($permalinks->pageUrl($page));
});