<?php

namespace App\Http\Middleware;

use App\Cms\Core\SettingsRepository;
use Closure;
use Illuminate\Http\Request;

class ForceCanonicalUrl
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        // Only normalize safe methods
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        // Skip json/ajax
        if ($request->expectsJson() || $request->ajax()) {
            return $next($request);
        }

        $pathInfo = $request->getPathInfo(); // begins with "/"
        $trim = ltrim($pathInfo, '/');

        // Skip system areas
        foreach (['_contact', 'lara-admin', 'filament', 'storage', 'api', 'livewire'] as $prefix) {
            if ($trim === $prefix || str_starts_with($trim, $prefix . '/')) {
                return $next($request);
            }
        }

        // Skip file-like paths (sitemap.xml, robots.txt, .css, .js, images, etc.)
        if ($pathInfo !== '/' && $pathInfo !== '') {
            $lastSeg = basename($pathInfo);
            if ($lastSeg !== '' && str_contains($lastSeg, '.')) {
                return $next($request);
            }
        }

        // Build desired canonical base.
        // IMPORTANT: In local/dev, host enforcement often breaks (www.cms.test not mapped),
        // so we skip forcing host there.
        $desiredBase = $request->getSchemeAndHttpHost();

        if (!app()->environment('local')) {
            $siteUrl = (string) $this->settings->get('core', 'site_url', (string) config('app.url'));
            $siteUrl = trim($siteUrl);
            if ($siteUrl === '') {
                $siteUrl = (string) config('app.url');
            }
            $desiredBase = rtrim($siteUrl, '/');
        }

        // Desired path: enforce trailing slash except "/"
        $desiredPath = $this->normalizeTrailingSlashPath($pathInfo);

        // Preserve query string
        $qs = $request->getQueryString();
        $desiredUrl = $desiredBase . $desiredPath . ($qs ? ('?' . $qs) : '');

        // ✅ Compare EXACT URL (do NOT rtrim)
        $currentUrl = $request->fullUrl();

        if ($currentUrl !== $desiredUrl) {
            return redirect()->to($desiredUrl, 301);
        }

        return $next($request);
    }

    private function normalizeTrailingSlashPath(string $pathInfo): string
    {
        if ($pathInfo === '' || $pathInfo === '/') {
            return '/';
        }

        return rtrim($pathInfo, '/') . '/';
    }
}