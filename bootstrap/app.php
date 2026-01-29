<?php

use App\Http\Middleware\AnonymousResponseCache;
use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\CmsEnqueueAssetsMiddleware;
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
        // ✅ Redirects should run BEFORE controllers
        $middleware->web(prepend: [
            ApplyRedirects::class,
        ]);

        // ✅ Assets should run AFTER controllers (needs Response)
        $middleware->web(append: [
            CmsEnqueueAssetsMiddleware::class,
            AnonymousResponseCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();