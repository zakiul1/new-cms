<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\ContentRouterController;
use App\Http\Controllers\ThemeCustomizerController;
use App\Cms\Hooks\HookPoints;

// ✅ Let plugins register routes BEFORE the catch-all
do_action(HookPoints::CMS_ROUTES);

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

// ✅ Catch-all (keep this last)
Route::get('/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*');
