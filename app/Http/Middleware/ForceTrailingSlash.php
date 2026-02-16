<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceTrailingSlash
{
    public function handle(Request $request, Closure $next)
    {
        // Only for safe idempotent requests
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = $request->getPathInfo(); // "/", "/abc", "/abc/"
        if ($path === '/' || $path === '') {
            return $next($request);
        }

        // Skip file-like paths: ".css", ".js", ".png", ".xml", ".txt", etc.
        if (preg_match('/\.[a-zA-Z0-9]+$/', $path)) {
            return $next($request);
        }

        // Skip admin, auth, and framework/internal endpoints
        foreach ([
            '/admin',
            '/lara-admin',
            '/livewire',
            '/storage',
            '/build',
            '/vendor',
            '/_boost',
            '/api',
        ] as $skip) {
            if (str_starts_with($path, $skip)) {
                return $next($request);
            }
        }

        // Already has slash? OK
        if (str_ends_with($path, '/')) {
            return $next($request);
        }

        // Redirect to trailing slash version, preserving query string
        $to = $path . '/';
        $qs = $request->getQueryString();
        if ($qs) {
            $to .= '?' . $qs;
        }

        return redirect()->to($to, 301);
    }
}