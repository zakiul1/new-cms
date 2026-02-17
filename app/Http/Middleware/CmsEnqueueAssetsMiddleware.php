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
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Determine context early (we can do this without the response)
        $routeName = optional($request->route())->getName();
        $isAdmin =
            (is_string($routeName) && str_starts_with($routeName, 'filament.'))
            || str_starts_with($request->path(), 'admin');

        $ctx = new CmsRequestContext($request, $routeName, $isAdmin);

        // ✅ IMPORTANT: AssetManager is singleton -> clear per request
        cms_assets()->clear('frontend');
        cms_assets()->clear('admin');

        // ✅ Enqueue theme + plugin assets BEFORE the view renders
        $this->themes->enqueueActiveThemeAssets();

        // Hooks for plugins/themes
        $this->hooks->doAction(HookPoints::CMS_ENQUEUE_ASSETS, $ctx);

        if ($isAdmin) {
            $this->hooks->doAction(HookPoints::CMS_ENQUEUE_ASSETS_ADMIN, $ctx);
        }

        /** @var Response $response */
        $response = $next($request);

        // Keep your original behavior: only relevant for HTML responses.
        // (At this point assets are already enqueued; this just avoids side effects on non-HTML.)
        $contentType = (string) $response->headers->get('Content-Type', '');
        $isHtml = str_contains($contentType, 'text/html') || $contentType === '';

        if (!$isHtml || $response->isRedirection()) {
            return $response;
        }

        return $response;
    }
}