<?php

use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use App\Cms\Hooks\HookPoints;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Plugins\BlogPosts\Filament\Pages\GenerateBlogPosts;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\BlogCategoryResource;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource;

// 1) Ensure taxonomy exists (only when plugin enabled)
add_action(HookPoints::CMS_BOOTED, function () {
    Taxonomy::firstOrCreate(
        ['key' => 'blog_category'],
        ['label' => 'Blog Categories', 'hierarchical' => true],
    );
});

// ✅ 1.1) Register shortcode: [bp] (Blog Posts)
add_action(HookPoints::CMS_BOOTED, function () {

    if (!class_exists(ShortcodeRegistry::class)) {
        return;
    }

    /** @var ShortcodeRegistry $shortcodes */
    $shortcodes = app(ShortcodeRegistry::class);

    // bool parser: supports [bp img], [bp img="yes"], [bp img="1"]
    $toBool = function ($v): bool {
        if ($v === null)
            return true;
        if ($v === '')
            return true;
        if (is_bool($v))
            return $v;
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'yes', 'y', 'on'], true);
    };

    $shortcodes->registerWithMeta('bp', function (array $atts = [], ?string $content = null, array $context = []) use ($toBool) {
        try {
            // BlogPost model should exist in plugin
            if (!class_exists(\Plugins\BlogPosts\Models\BlogPost::class)) {
                return '<!-- [bp] BlogPost model missing -->';
            }

            // postid for TOP section (optional)
            $topTitle = '';
            $topContent = '';
            $topImages = [];
            $topVariant = ''; // ✅ no "top" attr in [bp]

            $postId = 0;
            if (array_key_exists('postid', $atts) && trim((string) ($atts['postid'] ?? '')) !== '') {
                $postId = (int) $atts['postid'];
            }

            if ($postId > 0) {
                $topPost = Post::query()
                    ->whereKey($postId)
                    ->where('type', 'blog_post')
                    ->whereRaw('LOWER(status) = ?', ['published'])
                    ->first();

                if ($topPost) {
                    $topTitle = trim((string) ($topPost->title ?? ''));
                    $topContent = trim((string) (
                        $topPost->content_html
                        ?? data_get($topPost->content_json ?? [], 'html')
                        ?? $topPost->excerpt
                        ?? ''
                    ));

                    // fetch up to 2 images if available
                    try {
                        $mediaItems = collect();

                        if (method_exists($topPost, 'featuredMediaPivot')) {
                            $mediaItems = $topPost->featuredMediaPivot()->get();
                        }

                        if ($mediaItems->isEmpty() && method_exists($topPost, 'featuredMedia')) {
                            $one = $topPost->featuredMedia()->first();
                            if ($one) {
                                $mediaItems = collect([$one]);
                            }
                        }

                        $topImages = $mediaItems
                            ->map(function ($m) {
                                if (is_object($m) && method_exists($m, 'url')) {
                                    return (string) $m->url('large');
                                }
                                if (is_object($m) && property_exists($m, 'url') && is_string($m->url)) {
                                    return (string) $m->url;
                                }
                                if (is_object($m) && property_exists($m, 'path') && is_string($m->path)) {
                                    return (string) $m->path;
                                }
                                return null;
                            })
                            ->filter(fn($u) => is_string($u) && trim($u) !== '')
                            ->values()
                            ->take(2)
                            ->all();
                    } catch (\Throwable $e) {
                        $topImages = [];
                    }
                }
            }

            // catid (grid)
            $hasCatId = array_key_exists('catid', $atts) && trim((string) ($atts['catid'] ?? '')) !== '';
            $catId = $hasCatId ? (int) $atts['catid'] : 0;

            // Required: catid unless postid is provided
            if (!$hasCatId && $postId <= 0) {
                return '<!-- [bp] missing required catid -->'
                    . '<div class="text-sm text-red-600 my-4">Please provide <b>catid</b>. Example: <code>[bp catid="6"]</code></div>';
            }

            if ($hasCatId && $catId <= 0) {
                return '<!-- [bp] invalid catid -->'
                    . '<div class="text-sm text-red-600 my-4">Invalid <b>catid</b>. Example: <code>[bp catid="6"]</code></div>';
            }

            $columns = (int) ($atts['column'] ?? 3);
            $columns = max(1, min(12, $columns));

            $mobile = (int) ($atts['mobile'] ?? 1);
            $mobile = max(1, min(6, $mobile));

            $limit = (int) ($atts['load'] ?? ($atts['laod'] ?? 6));
            $limit = max(1, min(50, $limit));

            $class = trim((string) ($atts['class'] ?? 'static_posts'));
            $class = $class !== '' ? $class : 'static_posts';

            $showImg = false;
            if (array_key_exists('img', $atts)) {
                $showImg = $toBool($atts['img']);
            }

            $showLearnMoreBtn = false;
            if (array_key_exists('lmbtn', $atts)) {
                $showLearnMoreBtn = $toBool($atts['lmbtn']);
            }

            $items = collect();

            if ($hasCatId && $catId > 0) {
                // validate term belongs to blog_category taxonomy
                $taxonomyId = Taxonomy::query()->where('key', 'blog_category')->value('id');

                $termQuery = Term::query()->whereKey($catId);
                if ($taxonomyId) {
                    $termQuery->where('taxonomy_id', $taxonomyId);
                }

                if ($termQuery->exists()) {
                    $now = now();

                    $query = \Plugins\BlogPosts\Models\BlogPost::query()
                        ->whereRaw('LOWER(status) = ?', ['published'])
                        ->where(function ($q) use ($now) {
                            $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                        })
                        ->whereHas('terms', function ($q) use ($catId) {
                            $q->where('terms.id', $catId);
                        })
                        ->orderByDesc('published_at')
                        ->orderByDesc('id')
                        ->limit($limit);

                    // eager load media if relations exist
                    $with = [];
                    if (method_exists(\Plugins\BlogPosts\Models\BlogPost::class, 'featuredMediaPivot')) {
                        $with[] = 'featuredMediaPivot';
                    }
                    if (method_exists(\Plugins\BlogPosts\Models\BlogPost::class, 'featuredMedia')) {
                        $with[] = 'featuredMedia';
                    }
                    if (!empty($with)) {
                        $query->with($with);
                    }

                    $items = $query->get();
                }
            }

            // SAME view as [sp]
            if (view()->exists('shortcodes.static-posts-grid')) {
                return view('shortcodes.static-posts-grid', [
                    'items' => $items,
                    'column' => $columns,
                    'mobile' => $mobile,
                    'img' => $showImg,
                    'class' => $class,

                    // top section support (via postid)
                    'topTitle' => $topTitle,
                    'topContent' => $topContent,
                    'topVariant' => $topVariant,
                    'topImages' => $topImages,

                    'showLearnMoreBtn' => $showLearnMoreBtn,
                ])->render();
            }

            return '<!-- [bp] missing view shortcodes.static-posts-grid -->';
        } catch (\Throwable $e) {
            return '<!-- [bp] shortcode error: ' . e($e->getMessage()) . ' -->';
        }
    }, [
        'group' => 'Blog Posts',
        'description' => 'Renders Blog Posts grid. Same layout as [sp] but for blog posts. No "top" attribute.',
        'params' => [
            ['name' => 'catid', 'type' => 'int', 'default' => null, 'desc' => 'Blog category term id (required unless postid is provided).'],
            ['name' => 'postid', 'type' => 'int', 'default' => 0, 'desc' => 'Blog post id for top section. If catid missing, renders top section only.'],
            ['name' => 'column', 'type' => 'int', 'default' => 3, 'desc' => 'Desktop columns (1–12).'],
            ['name' => 'mobile', 'type' => 'int', 'default' => 1, 'desc' => 'Mobile columns (1–6).'],
            ['name' => 'load', 'type' => 'int', 'default' => 6, 'desc' => 'Number of items to load (1–50).'],
            ['name' => 'img', 'type' => 'bool', 'default' => false, 'desc' => 'Show images (flag or value).'],
            ['name' => 'lmbtn', 'type' => 'bool', 'default' => false, 'desc' => 'Show Learn More button (flag or value).'],
            ['name' => 'class', 'type' => 'string', 'default' => 'static_posts', 'desc' => 'CSS class for wrapper.'],
        ],
        'examples' => [
            '[bp catid="6"]',
            '[bp catid="6" column="3" mobile="1" load="6" img]',
            '[bp postid="14" lmbtn]',
        ],
    ]);
});

// 2) Register Filament admin resources + submenu nav items
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {

    // ✅ Add view namespace for this plugin pages
    $viewsPath = base_path('plugins/blog-posts/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('blog-posts', $viewsPath);
    }

    // Register Resources
    $panel->resources([
        BlogPostResource::class,
        BlogCategoryResource::class,
    ]);

    // ✅ Register Pages (Generate Posts submenu)
    // NOTE: This is a standalone Filament Page (Filament\Pages\Page),
    // so it must define its own slug inside GenerateBlogPosts.php
    $panel->pages([
        GenerateBlogPosts::class,
    ]);

    // Add nav item: "Add Blog Post" under Blog Posts group
    $panel->navigationItems([
        NavigationItem::make('Add Blog Post')
            ->group('Blog Posts')
            ->sort(2)
            ->url(fn() => BlogPostResource::getUrl('create'))
            ->icon('heroicon-o-plus-circle'),

        // Optional: if you want explicit nav item even if page nav is hidden
        // NavigationItem::make('Generate Posts')
        //     ->group('Blog Posts')
        //     ->sort(3)
        //     ->url(fn () => GenerateBlogPosts::getUrl())
        //     ->icon('heroicon-o-bolt'),
    ]);

}, 10, 1);

// 3) Frontend route (only exists when plugin enabled)
add_action(HookPoints::CMS_ROUTES, function () {
    Route::get('/blog/{slug}', function (string $slug) {
        $post = \Plugins\BlogPosts\Models\BlogPost::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('blog-posts.show', ['post' => $post]);
    });
}, 10, 0);