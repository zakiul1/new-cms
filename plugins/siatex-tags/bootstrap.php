<?php

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

    $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];
    $seo = is_array($meta['seo'] ?? null) ? $meta['seo'] : [];

    if (!isset($seo['title']) || trim((string) $seo['title']) === '') {
        $seo['title'] = $tag->title;
    }

    return view('siatex-tags::show', [
        'tag' => $tag,
        'mediaItems' => $media,
        'seo' => $seo,
    ]);
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
    $shortcodes->register('page-tags', function (array $attrs, ?string $content, array $ctx): string {
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
    });
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