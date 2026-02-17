<?php

namespace App\Http\Middleware;

use App\Cms\Core\SettingsRepository;
use Closure;
use Illuminate\Http\Request;

class ForceCanonicalSiteUrl
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        /**
         * ✅ Important fixes:
         * 1) Do NOT run on local/dev environments (prevents .test / cert issues / Livewire CORS).
         * 2) Do NOT redirect Livewire update endpoints (prevents ajax/cors failures during admin use).
         * 3) Still enforce canonical on real frontend pages in production.
         */

        // ✅ Disable on local/dev/testing environments
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        // ✅ Skip Livewire endpoints (avoid ajax/cors issues)
        // Livewire v3: /livewire/* and /livewire-*/update are common
        $path = ltrim($request->path(), '/');
        if (
            str_starts_with($path, 'livewire') ||
            str_contains($path, '/livewire') ||
            $request->headers->has('X-Livewire') // Livewire request header
        ) {
            return $next($request);
        }

        $canonical = (string) $this->settings->get('core', 'site_url', (string) config('app.url'));
        $canonical = rtrim(trim($canonical), '/');

        if ($canonical === '') {
            return $next($request);
        }

        $c = parse_url($canonical);
        $canonScheme = $c['scheme'] ?? 'https';
        $canonHost = $c['host'] ?? null;
        $canonPort = $c['port'] ?? null;

        if (!$canonHost) {
            return $next($request);
        }

        // Current request info (respect proxies if TrustProxies configured)
        $reqScheme = $request->getScheme();
        $reqHost = $request->getHost();
        $reqPort = $request->getPort();

        $hostMismatch = strcasecmp($reqHost, $canonHost) !== 0;
        $schemeMismatch = strtolower($reqScheme) !== strtolower($canonScheme);

        // If canonical specifies a port, enforce it; otherwise ignore port mismatch.
        $portMismatch = $canonPort ? ((int) $reqPort !== (int) $canonPort) : false;

        if ($hostMismatch || $schemeMismatch || $portMismatch) {
            // Preserve full path + query string
            $target = $canonical . $request->getRequestUri();
            return redirect()->to($target, 301);
        }

        return $next($request);
    }
}