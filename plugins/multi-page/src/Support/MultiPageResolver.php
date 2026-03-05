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

        // ✅ New key format (hashed)
        $keyNew = MultiPageStorage::pathKey($path);
        $mapPathNew = MultiPageStorage::LINKS . '/' . $keyNew . '.json';

        // ✅ Backward compatible: old key format (replace "/" with "_")
        $oldKey = str_replace('/', '_', trim($path, '/'));
        if ($oldKey === '') {
            $oldKey = 'home';
        }
        $mapPathOld = MultiPageStorage::LINKS . '/' . $oldKey . '.json';

        // Choose whichever exists
        $mapPath = null;
        if ($disk->exists($mapPathNew)) {
            $mapPath = $mapPathNew;
        } elseif ($disk->exists($mapPathOld)) {
            $mapPath = $mapPathOld;
        } else {
            // ✅ IMPORTANT: no mapping => NOT a multipage-generated URL
            // Let normal CMS routing handle it (post/page/media/tag).
            return null;
        }

        $raw = $disk->get($mapPath);
        $map = json_decode((string) $raw, true);
        if (!is_array($map)) {
            // broken mapping => let normal routing handle (or 404)
            return null;
        }

        $baseSlug = (string) ($map['base_slug'] ?? '');
        $baseSlug = trim($baseSlug, '/');
        if ($baseSlug === '') {
            return null;
        }

        $page = Post::query()
            ->where('type', 'multipage')
            ->where('slug', $baseSlug)
            ->first();

        if (!$page) {
            // base multipage deleted/disabled => not resolvable
            return null;
        }

        $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
        $cfg = is_array($meta['multipage'] ?? null) ? $meta['multipage'] : [];
        $enabled = (bool) ($cfg['enabled'] ?? false);

        /**
         * ✅ Optional: Redirect "default generated URL" back to canonical /{baseSlug}/
         * Your CMS standard is trailing slash.
         */
        if ($enabled) {
            $urlStructure = trim((string) ($cfg['url_structure'] ?? ''));
            $defaultSegmentsRaw = trim((string) ($cfg['default_segments'] ?? ''));
            $defaultSegments = $defaultSegmentsRaw === ''
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $defaultSegmentsRaw)), fn($v) => $v !== ''));

            $gen = new MultiPageGenerator();
            $defaultUrl = $gen->buildDefaultUrl($urlStructure, $defaultSegments);

            if ($defaultUrl && rtrim($defaultUrl, '/') === rtrim($path, '/')) {
                $qs = $request->getQueryString();
                $to = '/' . $baseSlug . '/' . ($qs ? ('?' . $qs) : '');
                return redirect()->to($to, 301);
            }
        }

        // ✅ Segments for shortcode/token replacement (slugified)
        $segments = $map['replacer'] ?? [];
        $segments = is_array($segments) ? array_values($segments) : [];

        // ✅ RAW segments for pretty display (exact CSV casing)
        $segmentsRaw = $map['replacer_raw'] ?? [];
        $segmentsRaw = is_array($segmentsRaw) ? array_values($segmentsRaw) : [];

        // ✅ Mark request as generated multipage URL (prevents canonical redirect in controller)
        $request->attributes->set('multipage_generated', true);

        // store requested path (for SEO canonical if needed)
        $request->attributes->set('multipage_requested_path', $path);

        // Store both segment sets
        $request->attributes->set('multipage_segments', $segments);
        $request->attributes->set('multipage_segments_raw', $segmentsRaw);

        $request->attributes->set('multipage_base_slug', $baseSlug);

        // ✅ Force template for generated links (optional)
        $template = trim((string) data_get($meta, 'template', ''));
        if ($template !== '') {
            $request->attributes->set('cms_forced_template', $template);
        }

        // ✅ Call controller through container so DI works
        $controller = app(ContentRouterController::class);

        return app()->call([$controller, 'show'], [
            'request' => $request,
            'slug' => $baseSlug,
        ]);
    }
}