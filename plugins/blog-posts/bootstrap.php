<?php

use App\Cms\Hooks\HookPoints;
use App\Models\Taxonomy;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\BlogCategoryResource;

// 1) Ensure taxonomy exists (only when plugin enabled)
add_action(HookPoints::CMS_BOOTED, function () {
    Taxonomy::firstOrCreate(
        ['key' => 'blog_category'],
        ['label' => 'Blog Categories', 'hierarchical' => true],
    );
});

// 2) Register Filament admin resources + submenu nav items
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {

    // Register Resources
    $panel->resources([
        BlogPostResource::class,
        BlogCategoryResource::class,
    ]);

    // Add nav item: "Add Blog Post" under Blog Posts group
    $panel->navigationItems([
        NavigationItem::make('Add Blog Post')
            ->group('Blog Posts')
            ->sort(2)
            ->url(fn() => BlogPostResource::getUrl('create'))
            ->icon('heroicon-o-plus-circle'),
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