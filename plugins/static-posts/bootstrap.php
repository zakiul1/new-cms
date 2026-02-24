<?php

use App\Cms\Hooks\HookPoints;
use App\Cms\Hooks\Hooks;
use App\Models\Taxonomy;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\StaticPostResource;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\StaticCategoryResource;

// 1) Ensure taxonomy exists (only when plugin enabled)
add_action(HookPoints::CMS_BOOTED, function () {
    Taxonomy::firstOrCreate(
        ['key' => 'static_category'],
        ['label' => 'Static Categories', 'hierarchical' => true],
    );
});

// 2) Register Filament admin resources + submenu nav items
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {

    // Register Resources
    $panel->resources([
        StaticPostResource::class,
        StaticCategoryResource::class,
    ]);

    // Add submenu-like nav item: "Add Static Post"
    $panel->navigationItems([
        NavigationItem::make('Add Static Post')
            ->group('Static Posts')
            ->sort(2)
            ->url(fn() => StaticPostResource::getUrl('create'))
            ->icon('heroicon-o-plus-circle'),
    ]);

}, 10, 1);

// 3) Optional: Frontend route (only exists when plugin enabled)
//    You can change URL pattern if you want.
add_action(HookPoints::CMS_ROUTES, function () {
    Route::get('/static/{slug}', function (string $slug) {
        $post = \Plugins\StaticPosts\Models\StaticPost::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        // Theme view suggestion:
        // themes/<active>/views/static-posts/show.blade.php
        // fallback can be a generic view if you prefer.
        return view('static-posts.show', ['post' => $post]);
    });
}, 10, 0);