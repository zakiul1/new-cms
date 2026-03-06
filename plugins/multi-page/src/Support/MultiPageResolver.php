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

        // New key format
        $keyNew = MultiPageStorage::pathKey($path);
        $mapPathNew = MultiPageStorage::LINKS . '/' . $keyNew . '.json';

        // Backward compatible old key format
        $oldKey = str_replace('/', '_', trim($path, '/'));
        if ($oldKey === '') {
            $oldKey = 'home';
        }
        $mapPathOld = MultiPageStorage::LINKS . '/' . $oldKey . '.json';

        $mapPath = null;
        if ($disk->exists($mapPathNew)) {
            $mapPath = $mapPathNew;
        } elseif ($disk->exists($mapPathOld)) {
            $mapPath = $mapPathOld;
        } else {
            // no mapping => let normal CMS routing handle it
            return null;
        }

        $raw = $disk->get($mapPath);
        $map = json_decode((string) $raw, true);
        if (!is_array($map)) {
            return null;
        }

        $baseSlug = trim((string) ($map['base_slug'] ?? ''), '/');
        if ($baseSlug === '') {
            return null;
        }

        $page = Post::query()
            ->where('type', 'multipage')
            ->where('slug', $baseSlug)
            ->first();

        if (!$page) {
            return null;
        }

        $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
        $cfg = is_array($meta['multipage'] ?? null) ? $meta['multipage'] : [];
        $enabled = (bool) ($cfg['enabled'] ?? false);

        /**
         * Redirect "default generated URL" back to canonical /{baseSlug}/
         * but never redirect to the same URL again.
         */
        if ($enabled) {
            $urlStructure = trim((string) ($cfg['url_structure'] ?? ''));
            $defaultSegmentsRaw = trim((string) ($cfg['default_segments'] ?? ''));
            $defaultSegments = $defaultSegmentsRaw === ''
                ? []
                : array_values(array_filter(
                    array_map('trim', explode(',', $defaultSegmentsRaw)),
                    fn($v) => $v !== ''
                ));

            $gen = new MultiPageGenerator();
            $defaultUrl = $gen->buildDefaultUrl($urlStructure, $defaultSegments);

            if ($defaultUrl) {
                $normalizedCurrent = '/' . trim($path, '/') . '/';
                $normalizedDefault = '/' . trim((string) parse_url($defaultUrl, PHP_URL_PATH), '/') . '/';
                $target = '/' . trim($baseSlug, '/') . '/';

                // redirect only if current path matches the default generated URL
                // AND the target is different from current path
                if ($normalizedDefault === $normalizedCurrent && $target !== $normalizedCurrent) {
                    $qs = $request->getQueryString();
                    return redirect()->to($target . ($qs ? ('?' . $qs) : ''), 301);
                }
            }
        }

        // Segments for shortcode/token replacement (slugified)
        $segments = $map['replacer'] ?? [];
        $segments = is_array($segments) ? array_values($segments) : [];

        // RAW segments for pretty display (exact CSV casing)
        $segmentsRaw = $map['replacer_raw'] ?? [];
        $segmentsRaw = is_array($segmentsRaw) ? array_values($segmentsRaw) : [];

        // mark request as generated multipage URL
        $request->attributes->set('multipage_generated', true);

        // store requested path
        $request->attributes->set('multipage_requested_path', $path);

        $request->attributes->set('multipage_segments', $segments);
        $request->attributes->set('multipage_segments_raw', $segmentsRaw);
        $request->attributes->set('multipage_base_slug', $baseSlug);

        $template = trim((string) data_get($meta, 'template', ''));
        if ($template !== '') {
            $request->attributes->set('cms_forced_template', $template);
        }

        $controller = app(ContentRouterController::class);

        return app()->call([$controller, 'show'], [
            'request' => $request,
            'slug' => $baseSlug,
        ]);
    }
}