<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\ContentRouterController;
use App\Http\Controllers\ThemeCustomizerController;
use App\Http\Controllers\Cms\SitemapController;
use App\Http\Controllers\Cms\RobotsController;
use App\Http\Controllers\Cms\CategoryArchiveController;
use App\Cms\Hooks\HookPoints;

// ✅ Let plugins register routes BEFORE the catch-all
do_action(HookPoints::CMS_ROUTES);

// ✅ SEO endpoints (must be before catch-all)
Route::get('/robots.txt', [RobotsController::class, 'show'])->name('cms.robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('cms.sitemap');

// ✅ Customizer (FULL SCREEN, WP-like) - MUST be before catch-all
Route::middleware(['web', 'auth'])
    ->get('/customizer', [ThemeCustomizerController::class, 'index'])
    ->name('cms.customizer');

// ✅ Category archive (example: /category/product)
Route::get('/category/{slug}', [CategoryArchiveController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.category.archive');

// ✅ Home (theme home)
Route::get('/', fn() => view('home'));

// ✅ Preview helper routes
require base_path('routes/cms_preview.php');

/**
 * ✅ POSTS route (important if your permalink rule is /blog/{slug})
 */
Route::get('/blog/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.blog.show');

/**
 * ✅ Catch-all last (pages + anything else)
 */
Route::get('/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.catchall');
