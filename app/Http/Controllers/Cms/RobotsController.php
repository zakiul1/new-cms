<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Core\SettingsRepository;
use App\Cms\Seo\SitemapStorage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class RobotsController extends Controller
{
    public function __construct(
        private SettingsRepository $settings,
        private SitemapStorage $storage,
    ) {
    }

    public function show(): Response
    {
        // ✅ WP-like global block: discourage search engines from indexing
        $blocked = (bool) $this->settings->get('seo', 'search_engine_block', false);

        if ($blocked) {
            $lines = [
                'User-agent: *',
                'Disallow: /',
            ];

            return response(implode("\n", $lines), 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // Default robots rules (when not blocked)
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /livewire',
        ];

        $show = (bool) $this->settings->get('seo', 'sitemap_show_in_robots', true);

        $disk = Storage::disk('public');
        if ($show && $disk->exists($this->storage->indexPath())) {
            $lines[] = 'Sitemap: ' . route('cms.sitemap');
        }

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}