<?php

use App\Cms\Hooks\HookPoints;
use App\Models\Taxonomy;
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