<?php

namespace App\Http\Middleware;

use App\Cms\Core\CmsRequestContext;
use App\Cms\Hooks\Hooks;
use App\Cms\Hooks\HookPoints;
use App\Cms\Themes\ThemeManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsEnqueueAssetsMiddleware
{
    public function __construct(
        private readonly Hooks $hooks,
        private readonly ThemeManager $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // ✅ Only enqueue for HTML responses (where layouts will actually render styles/scripts)
        $contentType = (string) $response->headers->get('Content-Type', '');
        $isHtml = str_contains($contentType, 'text/html') || $contentType === '';

        if (!$isHtml || $response->isRedirection()) {
            return $response;
        }

        // ✅ IMPORTANT: AssetManager is singleton -> clear per request
        cms_assets()->clear('frontend');
        cms_assets()->clear('admin');

        // Theme assets should be enqueued here (route is known now)
        $this->themes->enqueueActiveThemeAssets();

        $routeName = optional($request->route())->getName();
        $isAdmin =
            (is_string($routeName) && str_starts_with($routeName, 'filament.'))
            || str_starts_with($request->path(), 'admin');

        $ctx = new CmsRequestContext($request, $routeName, $isAdmin);

        // Hooks for plugins/themes
        $this->hooks->doAction(HookPoints::CMS_ENQUEUE_ASSETS, $ctx);

        if ($isAdmin) {
            $this->hooks->doAction(HookPoints::CMS_ENQUEUE_ASSETS_ADMIN, $ctx);
        }

        return $response;
    }
}
