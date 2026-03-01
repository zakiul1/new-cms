<?php

namespace Plugins\MultiPage\Support;

use App\Http\Controllers\Cms\ContentRouterController;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class MultiPageResolver
{
    public function __construct()
    {
        MultiPageStorage::ensureDirs();
    }

    public function handle(Request $request, string $path)
    {
        $path = '/' . trim($path, '/');

        $disk = Storage::disk('local');

        $key = MultiPageStorage::pathKey($path);
        $mapPath = MultiPageStorage::LINKS . '/' . $key . '.json';

        if (!$disk->exists($mapPath)) {
            return null;
        }

        $raw = $disk->get($mapPath);
        $map = json_decode((string) $raw, true);
        if (!is_array($map)) {
            return null;
        }

        $baseSlug = (string) ($map['base_slug'] ?? '');
        if ($baseSlug === '') {
            return null;
        }

        // Load page to check if multipage default should redirect
        $page = Post::query()->where('type', 'page')->where('slug', $baseSlug)->first();

        if ($page) {
            $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
            $cfg = is_array($meta['multipage'] ?? null) ? $meta['multipage'] : [];
            $enabled = (bool) ($cfg['enabled'] ?? false);

            if ($enabled) {
                $urlStructure = trim((string) ($cfg['url_structure'] ?? ''));
                $defaultSegmentsRaw = trim((string) ($cfg['default_segments'] ?? ''));
                $defaultSegments = $defaultSegmentsRaw === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $defaultSegmentsRaw)), fn($v) => $v !== ''));

                $gen = new MultiPageGenerator();
                $defaultUrl = $gen->buildDefaultUrl($urlStructure, $defaultSegments);

                // if request equals default multipage url -> redirect to canonical /{slug}
                if ($defaultUrl && rtrim($defaultUrl, '/') === rtrim($path, '/')) {
                    return redirect('/' . ltrim($baseSlug, '/'), 301);
                }
            }
        }

        $segments = $map['replacer'] ?? [];
        if (!is_array($segments))
            $segments = [];

        // Make segments available to content filter/shortcodes
        $request->attributes->set('multipage_segments', array_values($segments));
        $request->attributes->set('multipage_base_slug', $baseSlug);

        // Render using normal CMS router (page view/theme remains unchanged)
        return app(ContentRouterController::class)->show($request, $baseSlug);
    }
}