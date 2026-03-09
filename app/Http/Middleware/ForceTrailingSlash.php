<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceTrailingSlash
{
    public function handle(Request $request, Closure $next)
    {
        // Only normalize GET/HEAD (never POST, PUT, etc.)
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        // Skip ajax/json
        if ($request->expectsJson() || $request->ajax()) {
            return $next($request);
        }

        $path = $request->getPathInfo(); // includes leading "/"

        // Root is fine
        if ($path === '/' || $path === '') {
            return $next($request);
        }

        // Skip reserved/system prefixes
        $trim = ltrim($path, '/');
        foreach (['_contact', 'lara-admin', 'filament', 'storage', 'api', 'livewire', 'customizer'] as $prefix) {
            if ($trim === $prefix || str_starts_with($trim, $prefix . '/')) {
                return $next($request);
            }
        }

        // Skip anything that looks like a file: /x.css, /sitemap.xml, /image.png, etc.
        $lastSeg = basename($path);
        if ($lastSeg !== '' && str_contains($lastSeg, '.')) {
            return $next($request);
        }

        // Already has slash
        if (str_ends_with($path, '/')) {
            return $next($request);
        }

        // Build redirect target on SAME host/scheme with query preserved
        $qs = $request->getQueryString();
        $target = $request->getSchemeAndHttpHost() . $path . '/' . ($qs ? ('?' . $qs) : '');

        return redirect()->to($target, 301);
    }
}