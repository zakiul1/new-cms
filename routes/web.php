<?php

use App\Cms\Core\SettingsRepository;
use App\Cms\Hooks\HookPoints;
use App\Http\Controllers\Cms\CategoryArchiveController;
use App\Http\Controllers\Cms\ContentRouterController;
use App\Http\Controllers\Cms\RobotsController;
use App\Http\Controllers\Cms\SitemapController;
use App\Http\Controllers\ThemeCustomizerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ✅ Let plugins register routes BEFORE the catch-all
do_action(HookPoints::CMS_ROUTES);

// ✅ SEO endpoints (must be before catch-all)
Route::get('/robots.txt', [RobotsController::class, 'show'])->name('cms.robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('cms.sitemap');

/**
 * ✅ Serve sub-sitemaps like:
 * /page.xml
 * /post.xml
 * /siatex-tags.xml
 * /post-2.xml
 * /media.xml
 * /media-2.xml
 *
 * Must be before catch-all.
 *
 * SECURITY:
 * Only allow safe filename characters. Controller should still check file exists.
 */
Route::get('/{name}.xml', [SitemapController::class, 'file'])
    ->where('name', '[A-Za-z0-9\-_]+(?:-\d+)?')
    ->name('cms.sitemap.file');

// ✅ Customizer (FULL SCREEN, WP-like) - MUST be before catch-all
Route::middleware(['web', 'auth'])
    ->get('/customizer', [ThemeCustomizerController::class, 'index'])
    ->name('cms.customizer');

// ✅ Backward-compatible category archive (default base: /category/{slug})
Route::get('/category/{slug}', [CategoryArchiveController::class, 'show'])
    ->where('slug', '.*')
    ->name('cms.category.archive');

// ✅ Home (supports ?p=123 for "Plain" permalinks + static front page)
Route::get('/', [ContentRouterController::class, 'home'])->name('cms.home');

/**
 * ✅ Logout route for frontend admin bar
 * Fixes: "Route [logout] not defined."
 */
Route::post('/logout', function (Request $request) {
    auth()->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->name('logout');

/**
 * ✅ Toggle Frontend Admin Bar (called by Filament topbar button)
 * Stored in cms_settings group=core key=frontend_admin_bar_enabled
 *
 * IMPORTANT: must be BEFORE catch-all route.
 */
Route::post('/lara-admin/toggle-frontend-admin-bar', function (SettingsRepository $settings) {
    $current = (bool) $settings->get('core', 'frontend_admin_bar_enabled', true);
    $settings->set('core', 'frontend_admin_bar_enabled', !$current);

    return back();
})->middleware(['web', 'auth'])->name('cms.toggle_frontend_admin_bar');

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
 *
 * ✅ CRITICAL FIX:
 * Exclude reserved prefixes so plugin/system endpoints are NOT captured.
 */
Route::get('/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '^(?!_contact(?:/|$))(?!lara-admin(?:/|$))(?!filament(?:/|$))(?!storage(?:/|$))(?!api(?:/|$))(?!livewire(?:/|$))(?!customizer(?:/|$)).+')
    ->name('cms.catchall');