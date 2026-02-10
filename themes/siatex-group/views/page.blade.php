@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $settings = app(\App\Cms\Core\SettingsRepository::class);
        $hooks = app(\App\Cms\Hooks\Hooks::class);

        // ✅ Admin edit URL (do NOT override if controller already provided it)
        if (!isset($adminEditUrl)) {
            $panelId = 'admin';

            if (($post->type ?? 'post') === 'page' && class_exists(\App\Filament\Resources\Pages\PageResource::class)) {
                $adminEditUrl = \App\Filament\Resources\Pages\PageResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: $panelId,
                );
            } elseif (class_exists(\App\Filament\Resources\Posts\PostResource::class)) {
                $adminEditUrl = \App\Filament\Resources\Posts\PostResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: $panelId,
                );
            } else {
                $adminEditUrl = url('/lara-admin');
            }
        }

        // ✅ WP-like: detect if THIS page is the selected homepage page
        $homepageId = $settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        $isHomepage = $homepageId !== null && $post && (int) $post->getKey() === $homepageId;

        // ✅ Homepage hero slider config (stored in meta_json.home.*)
        $heroKey = $isHomepage ? (string) data_get($post?->meta_json, 'home.hero_slider_key', '') : '';
        $heroVariant = $isHomepage
            ? (string) data_get($post?->meta_json, 'home.hero_slider_variant', 'siatex-default')
            : 'siatex-default';

        /**
         * ✅ Raw content from editor
         * IMPORTANT:
         * Your editor saves HTML into content_json['html'] (via content_html accessor),
         * not $post->content.
         */
        $raw = (string) ($post->content_html ?? (data_get($post->content_json ?? [], 'html') ?? ''));

        // ✅ 1) Apply CMS "the_content" hooks/filters
        $filtered = $hooks->applyFilters(\App\Cms\Hooks\HookPoints::CMS_THE_CONTENT, $raw, ['post' => $post]);

        // ✅ 2) Force shortcodes
        try {
            $contentHtml = function_exists('do_shortcode') ? do_shortcode($filtered, ['post' => $post]) : $filtered;
        } catch (\Throwable $e) {
            $contentHtml = $filtered . "\n<!-- shortcode error: " . e($e->getMessage()) . ' -->';
        }

        // Detect if content contains posts shortcode (avoid prose wrappers breaking grids)
        $hasPostsShortcode = str_contains($raw, '[posts') || str_contains($filtered, '[posts');

        // ✅ TEMP DEBUG (remove later):
        // Uncomment this once to confirm the homepage + slider meta are correct.
        /*
        dump([
            'page_id' => $post?->id,
            'homepage_id' => $homepageId,
            'is_homepage' => $isHomepage,
            'hero_key' => $heroKey,
            'hero_variant' => $heroVariant,
        ]);
        */

    @endphp

    {{-- ✅ Homepage Hero Slider (ONLY when this page is set as homepage) --}}
    @if ($isHomepage && $heroKey !== '')
        @if (function_exists('siatex_slider_render'))
            {!! siatex_slider_render($heroKey, ['variant' => $heroVariant]) !!}
        @elseif (function_exists('slider_render'))
            {!! slider_render($heroKey) !!}
        @else
            <!-- slider render function not available -->
        @endif
    @endif

    {{-- ✅ Page Content --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 pb-12">
            @if ($hasPostsShortcode)
                <div class="cms-content">
                    {!! $contentHtml !!}
                </div>
            @else
                <div class="prose prose-slate max-w-none">
                    {!! $contentHtml !!}
                </div>
            @endif
        </div>
    </section>
@endsection
