<?php

use App\Cms\Hooks\HookPoints;
use App\Http\Controllers\Cms\CategoryArchiveController;
use App\Http\Controllers\Cms\ContentRouterController;
use App\Http\Controllers\Cms\RobotsController;
use App\Http\Controllers\Cms\SitemapController;
use App\Http\Controllers\ThemeCustomizerController;
use Illuminate\Support\Facades\Route;

// ✅ Let plugins register routes BEFORE the catch-all
do_action(HookPoints::CMS_ROUTES);

// ✅ SEO endpoints (must be before catch-all)
Route::get('/robots.txt', [RobotsController::class, 'show'])->name('cms.robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('cms.sitemap');

// ✅ Customizer (FULL SCREEN, WP-like) - MUST be before catch-all
Route::middleware(['web', 'auth'])
    ->get('/customizer', [ThemeCustomizerController::class, 'index'])
    ->name('cms.customizer');

// ✅ Backward-compatible category archive (default base: /category/{slug})
Route::get('/category/{slug}', [CategoryArchiveController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.category.archive');

// ✅ Home (now supports ?p=123 for "Plain" permalinks)
Route::get('/', [ContentRouterController::class, 'home'])->name('cms.home');

// ✅ Redirect /home -> / (SEO + fixes wrong menu links if any exist)
Route::redirect('/home', '/', 301)->name('cms.home.redirect');

// ✅ Preview helper routes
require base_path('routes/cms_preview.php');

/**
 * Legacy route - you can keep it; canonical redirects will normalize URLs.
 */
Route::get('/blog/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.blog.show');

/**
 * ✅ Catch-all route:
 * Handles pages, posts, attachment pages (/media-slug), and slug history redirects.
 * IMPORTANT: keep this LAST.
 */
Route::get('/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.catchall');