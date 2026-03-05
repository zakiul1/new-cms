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
         * We want: non-www -> www 301 redirect (canonical host).
         *
         * Safety rules:
         * 1) Do NOT run on local/testing environments.
         * 2) Do NOT redirect Livewire endpoints (avoid ajax/cors issues).
         * 3) Do NOT redirect admin panel endpoints (avoid login/session/cookie issues).
         * 4) Enforce host (www) + optional canonical port. (Do NOT enforce scheme here.)
         */

        // ✅ Disable on local/testing environments
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        // ✅ Skip Livewire endpoints (avoid ajax/cors issues)
        if (
            str_starts_with($path, 'livewire') ||
            str_contains($path, '/livewire') ||
            $request->headers->has('X-Livewire')
        ) {
            return $next($request);
        }

        // ✅ Skip admin panel endpoints (avoid session/cookie/redirect quirks)
        if (
            str_starts_with($path, 'lara-admin') ||
            str_starts_with($path, 'filament') ||
            str_starts_with($path, 'admin')
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
        $reqHost = $request->getHost();
        $reqPort = $request->getPort();

        $hostMismatch = strcasecmp($reqHost, $canonHost) !== 0;

        // If canonical specifies a port, enforce it; otherwise ignore port mismatch.
        $portMismatch = $canonPort ? ((int) $reqPort !== (int) $canonPort) : false;

        // ✅ Enforce only host (+ optional port). Do NOT enforce scheme to avoid proxy https loops.
        if ($hostMismatch || $portMismatch) {
            $target = $canonScheme . '://' . $canonHost . ($canonPort ? ':' . $canonPort : '') . $request->getRequestUri();
            return redirect()->to($target, 301);
        }

        return $next($request);
    }
}