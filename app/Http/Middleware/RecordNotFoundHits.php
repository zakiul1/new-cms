<?php

namespace App\Http\Middleware;

use App\Models\NotFoundHit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RecordNotFoundHits
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Only record real 404s (GET) on frontend
        if ($request->method() !== 'GET' || $response->getStatusCode() !== 404) {
            return $response;
        }

        $path = '/' . ltrim($request->path(), '/');
        if ($request->path() === '/') {
            $path = '/';
        }

        // Skip admin/assets/etc
        if (
            str_starts_with($path, '/admin') ||
            str_starts_with($path, '/filament') ||
            str_starts_with($path, '/livewire') ||
            str_starts_with($path, '/storage') ||
            str_starts_with($path, '/build') ||
            str_starts_with($path, '/vendor')
        ) {
            return $response;
        }

        // Throttle spam: one hit per path+ip per 60s
        $ip = (string) $request->ip();
        $throttleKey = 'cms:404hit:' . md5($path . '|' . $ip);
        if (Cache::has($throttleKey)) {
            return $response;
        }
        Cache::put($throttleKey, 1, now()->addSeconds(60));

        $ref = $request->headers->get('referer');
        $ua = $request->userAgent();

        NotFoundHit::query()->updateOrCreate(
            ['path' => $path],
            [
                'hits' => \DB::raw('hits + 1'),
                'first_hit_at' => now(), // if already exists, DB won't keep old; fix below
                'last_hit_at' => now(),
                'last_referrer' => $ref ? mb_substr((string) $ref, 0, 255) : null,
                'last_user_agent' => $ua ? mb_substr((string) $ua, 0, 255) : null,
                'last_ip' => $ip ? mb_substr($ip, 0, 64) : null,
            ]
        );

        // Keep first_hit_at stable
        NotFoundHit::query()
            ->where('path', $path)
            ->whereNull('first_hit_at')
            ->update(['first_hit_at' => now()]);

        return $response;
    }
}