<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\ContentRouterController;
use App\Http\Controllers\ThemeCustomizerController;

// ✅ Customizer (FULL SCREEN, WP-like) - MUST be before catch-all
Route::middleware(['web', 'auth'])
    ->get('/customizer', [ThemeCustomizerController::class, 'index'])
    ->name('cms.customizer');

// Home
Route::get('/', function () {
    return view('home'); // resolves to themes/{active}/views/home.blade.php
});

// ✅ Preview helper routes for Home/Post/Page dropdown
require base_path('routes/cms_preview.php');

// Catch-all (keep this last)
Route::get('/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*');