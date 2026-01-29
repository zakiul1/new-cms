<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AnonymousResponseCache
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (!config('cms.response_cache.enabled', false)) {
            return $next($request);
        }

        // Only cache GET/HEAD
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        // Only cache guests
        if (auth()->check()) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');

        // Exclude sensitive areas
        foreach ((array) config('cms.response_cache.exclude_paths', []) as $prefix) {
            $prefix = '/' . ltrim((string) $prefix, '/');
            if ($prefix !== '/' && str_starts_with($path, $prefix)) {
                return $this->withHeader($next($request), 'BYPASS');
            }
        }

        // Build cache key (include query string)
        $key = 'cms:pagecache:' . sha1($request->fullUrl());

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return response($cached, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-CMS-PageCache' => 'HIT',
            ]);
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        // Cache only successful HTML responses
        if (!$this->isCacheable($request, $response)) {
            return $this->withHeader($response, 'BYPASS');
        }

        $ttl = (int) config('cms.response_cache.ttl_seconds', 300);
        $ttl = max(30, min($ttl, 86400)); // 30s..24h safety

        Cache::put($key, (string) $response->getContent(), now()->addSeconds($ttl));

        return $this->withHeader($response, 'MISS');
    }

    private function isCacheable(Request $request, SymfonyResponse $response): bool
    {
        if (method_exists($response, 'isSuccessful') && !$response->isSuccessful()) {
            return false;
        }

        // Only HTML responses
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (!str_contains(strtolower($contentType), 'text/html')) {
            return false;
        }

        // If response sets cookies, don’t cache
        if (!empty($response->headers->getCookies())) {
            return false;
        }

        $body = (string) $response->getContent();

        // Avoid caching pages with CSRF tokens / auth forms etc.
        $needleList = ['csrf-token', 'name="_token"', 'value="_token"'];
        foreach ($needleList as $needle) {
            if (str_contains($body, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function withHeader(SymfonyResponse $response, string $value): SymfonyResponse
    {
        $response->headers->set('X-CMS-PageCache', $value);
        return $response;
    }
}