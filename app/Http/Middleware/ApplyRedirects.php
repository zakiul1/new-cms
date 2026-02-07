<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;

class ApplyRedirects
{
    public function handle(Request $request, Closure $next)
    {
        // Normalize: "/" or "/about"
        $path = '/' . ltrim($request->path(), '/');
        if ($path === '//') {
            $path = '/';
        }

        // Normalize trailing slash (WP-like behavior)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Skip admin/assets
        if (
            str_starts_with($path, '/admin') ||
            str_starts_with($path, '/livewire') ||
            str_starts_with($path, '/storage') ||
            str_starts_with($path, '/build') ||
            str_starts_with($path, '/vendor')
        ) {
            return $next($request);
        }

        $r = Redirect::query()->where('from_path', $path)->first();
        if (!$r) {
            return $next($request);
        }

        $to = (string) $r->to_path;

        // prevent loop
        if ($to === $path) {
            return $next($request);
        }

        // ✅ preserve query string (optional but premium)
        $qs = $request->getQueryString();
        if ($qs) {
            $to .= (str_contains($to, '?') ? '&' : '?') . $qs;
        }

        return redirect()->to($to, (int) ($r->status_code ?: 301));
    }
}