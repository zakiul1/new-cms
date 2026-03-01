<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use App\Http\Controllers\ContentRouterController;
use App\Models\Taxonomy;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

// 1) Require plugin classes (plugins are not composer-autoloaded in this CMS)
require_once __DIR__ . '/src/Models/SiatexTag.php';
require_once __DIR__ . '/src/Support/MediaCategoryOptions.php';
require_once __DIR__ . '/src/Filament/Resources/SiatexTagResource.php';
require_once __DIR__ . '/src/Filament/Resources/SiatexTagResource/Pages/ListSiatexTags.php';
require_once __DIR__ . '/src/Filament/Resources/SiatexTagResource/Pages/CreateSiatexTag.php';
require_once __DIR__ . '/src/Filament/Resources/SiatexTagResource/Pages/EditSiatexTag.php';

// 2) Register view namespace for plugin views
View::addNamespace('siatex-tags', __DIR__ . '/views');

/**
 * Helper: render tag page by slug (returns Response) or null if not found.
 */
if (!function_exists('siatex_tags_render_by_slug')) {
    function siatex_tags_render_by_slug(string $slug)
    {
        $tag = \Plugins\SiatexTags\Models\SiatexTag::query()
            ->where('slug', $slug)
            ->first();

        if (!$tag) {
            return null;
        }

        // Make current tag available to shortcode parsing (context + fallback)
        request()->attributes->set('siatex_tag', $tag);

        // ✅ Allow plugins to apply runtime defaults (no DB save)
        do_action('siatex.tag.defaults.persist', $tag);

        /**
         * ✅ Render shortcodes in tag fields for frontend output
         */
        /** @var \App\Cms\Content\Shortcodes\ShortcodeParser $parser */
        $parser = app(\App\Cms\Content\Shortcodes\ShortcodeParser::class);

        $ctx = [
            'siatex_tag' => $tag,
        ];

        // Title
        if (is_string($tag->title ?? null) && $tag->title !== '') {
            $tag->title = $parser->render($tag->title, $ctx);
        }

        // Content HTML (content_json.html)
        if (is_array($tag->content_json ?? null)) {
            $contentHtml = $tag->content_json['html'] ?? '';
            if (is_string($contentHtml) && $contentHtml !== '') {
                $tag->content_json['html'] = $parser->render($contentHtml, $ctx);
            }
        }

        // Meta fields + SEO fields
        $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $subtitle = data_get($meta, 'subtitle', '');
        if (is_string($subtitle) && $subtitle !== '') {
            data_set($meta, 'subtitle', $parser->render($subtitle, $ctx));
        }

        $subDesc = data_get($meta, 'sub_description', '');
        if (is_string($subDesc) && $subDesc !== '') {
            data_set($meta, 'sub_description', $parser->render($subDesc, $ctx));
        }

        // ✅ Render SEO title/description if present
        $seoTitle = data_get($meta, 'seo.title', '');
        if (is_string($seoTitle) && $seoTitle !== '') {
            data_set($meta, 'seo.title', $parser->render($seoTitle, $ctx));
        }

        $seoDesc = data_get($meta, 'seo.description', '');
        if (is_string($seoDesc) && $seoDesc !== '') {
            data_set($meta, 'seo.description', $parser->render($seoDesc, $ctx));
        }

        // Save meta back onto tag
        $tag->meta_json = $meta;

        $termId = (int) ($tag->media_category_term_id ?? 0);
        $media = collect();

        if ($termId > 0) {
            $ids = \Illuminate\Support\Facades\DB::table('termables')
                ->where('term_id', $termId)
                ->where('termable_type', \App\Models\Media::class)
                ->pluck('termable_id')
                ->map(fn($v) => (int) $v)
                ->unique()
                ->values()
                ->all();

            if (!empty($ids)) {
                $media = \App\Models\Media::query()
                    ->whereIn('id', $ids)
                    ->latest('id')
                    ->get();
            }
        }

        /**
         * ✅ Build $seo from meta
         */
        $seo = data_get($meta, 'seo', []);
        $seo = is_array($seo) ? $seo : [];

        /**
         * ✅ CRITICAL FIX:
         * If seo.title is empty, use Tag Defaults setting default_seo_title (plugins.tag-defaults).
         * This ensures Default SEO Title actually shows on frontend.
         */
        $seoTitleFinal = trim((string) data_get($seo, 'title', ''));

        if ($seoTitleFinal === '') {
            try {
                /** @var \App\Cms\Core\Settings $settings */
                $settings = app(Settings::class);
                $defaultSeoTitle = (string) $settings->get('default_seo_title', '', 'plugins.tag-defaults');
                $defaultSeoTitle = trim($defaultSeoTitle);

                if ($defaultSeoTitle !== '') {
                    // allow shortcodes like [tag]
                    $seo['title'] = $parser->render($defaultSeoTitle, $ctx);
                    $seoTitleFinal = trim((string) $seo['title']);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Final fallback to tag title if still empty
        if ($seoTitleFinal === '') {
            $seo['title'] = (string) ($tag->title ?? '');
        }

        return view('siatex-tags::show', [
            'tag' => $tag,
            'mediaItems' => $media,
            'seo' => $seo,
        ]);
    }
}

// 3) Ensure taxonomy exists + Register shortcodes in plugin
add_action(HookPoints::CMS_BOOTED, function () {
    Taxonomy::firstOrCreate(
        ['key' => 'media_category'],
        ['label' => 'Media Categories', 'hierarchical' => true],
    );

    /** @var \App\Cms\Content\Shortcodes\ShortcodeRegistry $shortcodes */
    $shortcodes = app(\App\Cms\Content\Shortcodes\ShortcodeRegistry::class);

    /**
     * [tag]
     * Prints current tag title.
     */
    $shortcodes->register('tag', function (array $attrs, ?string $content, array $ctx): string {
        $tag = $ctx['siatex_tag'] ?? request()->attributes->get('siatex_tag');

        return $tag instanceof \Plugins\SiatexTags\Models\SiatexTag
            ? e((string) $tag->title)
            : '';
    });

    /**
     * [page-tags number=10 links=true sep=", "]
     * Prints random tag links excluding current tag.
     *
     * NOTE: Links now point to "/{slug}" (no /tag/ prefix).
     */
    $shortcodes->registerWithMeta('page-tags', function (array $attrs, ?string $content, array $ctx): string {
        $limit = (int) ($attrs['number'] ?? 10);
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $current = $ctx['siatex_tag'] ?? request()->attributes->get('siatex_tag');
        $currentId = $current instanceof \Plugins\SiatexTags\Models\SiatexTag ? (int) $current->id : null;

        $query = \Plugins\SiatexTags\Models\SiatexTag::query();
        if ($currentId) {
            $query->where('id', '!=', $currentId);
        }

        $tags = $query->inRandomOrder()->limit($limit)->get(['title', 'slug']);
        if ($tags->isEmpty()) {
            return '';
        }

        $asLinks = filter_var($attrs['links'] ?? true, FILTER_VALIDATE_BOOLEAN); // default true
        $separator = (string) ($attrs['sep'] ?? ', '); // default ", "

        $items = [];
        foreach ($tags as $t) {
            if ($asLinks) {
                $items[] = '<a href="' . e(url('/' . ltrim((string) $t->slug, '/'))) . '">' . e($t->title) . '</a>';
            } else {
                $items[] = e($t->title);
            }
        }

        return (string) new \Illuminate\Support\HtmlString(implode($separator, $items));
    }, [
        'group' => 'Tags',
        'description' => 'Prints random tags (optionally as links), excluding the current tag.',
        'params' => [
            ['name' => 'number', 'type' => 'int', 'default' => 10, 'desc' => 'How many tags to show (1–100).'],
            ['name' => 'links', 'type' => 'bool', 'default' => true, 'desc' => 'Render as links (true) or plain text (false).'],
            ['name' => 'sep', 'type' => 'string', 'default' => ', ', 'desc' => 'Separator between items.'],
        ],
        'examples' => [
            '[page-tags]',
            '[page-tags number=20]',
            '[page-tags links=false]',
            '[page-tags sep=" | "]',
            '[page-tags number=20 links=false sep=" | "]',
        ],
    ]);
});

// 4) Register Filament resource (NO import submenu, import is modal in list page)
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
    $panel->resources([
        \Plugins\SiatexTags\Filament\Resources\SiatexTagResource::class,
    ]);
}, 10, 1);

// 5) Frontend routes
add_action(HookPoints::CMS_ROUTES, function () {

    /**
     * Backward compatible route: /tag/{slug} -> 301 redirect to /{slug}
     */
    Route::get('/tag/{slug}', function (string $slug) {
        return redirect('/' . ltrim($slug, '/'), 301);
    });

    /**
     * Dynamic tag route without prefix: /{slug}
     */
    Route::get('/{slug}', function (string $slug) {

        // 1) Try to render as tag
        $tagResponse = siatex_tags_render_by_slug($slug);
        if ($tagResponse !== null) {
            return $tagResponse;
        }

        // 2) Fallback to CMS content router (pages/posts/etc.)
        return app(ContentRouterController::class)->show($slug);

    })->where('slug', '^(?!lara-admin(?:/|$)|api(?:/|$)|storage(?:/|$)|sitemap\.xml$|robots\.txt$|customizer$).*$');

}, 10, 0);