<?php

use App\Http\Middleware\AnonymousResponseCache;
use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\CmsEnqueueAssetsMiddleware;
use App\Http\Middleware\ForceCanonicalSiteUrl;
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
        // ✅ Must run early
        $middleware->web(prepend: [
            ForceCanonicalSiteUrl::class,
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