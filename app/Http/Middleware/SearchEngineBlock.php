<?php

namespace App\Http\Middleware;

use App\Cms\Core\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchEngineBlock
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // ✅ Only affect public frontend routes (skip admin/filament/api/etc.)
        if (
            $request->is('admin*') ||
            $request->is('filament*') ||
            $request->is('livewire*') ||
            $request->is('api*')
        ) {
            return $response;
        }

        // ✅ WP-like global block
        if ((bool) $this->settings->get('seo', 'search_engine_block', false)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}