<?php

use App\Http\Middleware\AnonymousResponseCache;
use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\CmsEnqueueAssetsMiddleware;
use App\Http\Middleware\ForceCanonicalSiteUrl;
use App\Http\Middleware\ForceTrailingSlash;
use App\Http\Middleware\SearchEngineBlock;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * ✅ Must run early
         * We enforce ONLY trailing slash here (path-only) to avoid redirect loops.
         * Host/scheme canonicalization should be handled by core.site_url config
         * or server config, not by a second middleware in local/dev.
         *
         * Order matters:
         * 1) ForceTrailingSlash: normalize /slug -> /slug/
         * 2) ApplyRedirects: your DB redirect rules run on normalized paths
         * 3) CmsEnqueueAssetsMiddleware: enqueue theme/plugin assets before view render
         */
        $middleware->web(prepend: [
            ForceCanonicalSiteUrl::class,
            ForceTrailingSlash::class,
            ApplyRedirects::class,

                // ✅ IMPORTANT: enqueue theme/plugin assets BEFORE controllers/views render
            CmsEnqueueAssetsMiddleware::class,
        ]);

        // ✅ Runs after response is generated (and after assets are already enqueued)
        $middleware->web(append: [
            AnonymousResponseCache::class,
            SearchEngineBlock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();