@extends('layouts.app')

@section('content')
    @php
        $hooks = app(\App\Cms\Hooks\Hooks::class);
    @endphp

    {{-- Optional fallback hero (if your theme/plugin provides a default) --}}
    @if (function_exists('siatex_slider_render') && function_exists('setting'))
        @php
            $fallbackHeroKey = setting('home_fallback.hero_slider_key');
            $fallbackHeroVariant = setting('home_fallback.hero_slider_variant') ?? 'siatex-default';
        @endphp

        @if (!empty($fallbackHeroKey))
            {!! siatex_slider_render($fallbackHeroKey, ['variant' => $fallbackHeroVariant]) !!}
        @endif
    @endif

    {{-- ✅ Posts grid under slider --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-10">
            @php
                // Apply the same content filters pipeline first (keeps behavior consistent)
                $homeShortcodeRaw = '[posts count=12]';
                $homeShortcodeFiltered = $hooks->applyFilters(
                    \App\Cms\Hooks\HookPoints::CMS_THE_CONTENT,
                    $homeShortcodeRaw,
                    ['post' => null],
                );

                // Then run shortcodes (works even if filter chain doesn't include shortcodes)
if (function_exists('do_shortcode')) {
    try {
        $homePostsHtml = do_shortcode($homeShortcodeFiltered, ['post' => null]);
    } catch (\Throwable $e) {
        $homePostsHtml =
            $homeShortcodeFiltered . "\n<!-- shortcode error: " . e($e->getMessage()) . ' -->';
    }
} else {
    // If do_shortcode isn't available, at least output filtered content
                    $homePostsHtml = $homeShortcodeFiltered;
                }
            @endphp

            {!! $homePostsHtml !!}
        </div>
    </section>

    {{-- Fallback intro text --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="prose prose-slate max-w-none">
                {!! $hooks->applyFilters(
                    \App\Cms\Hooks\HookPoints::CMS_THE_CONTENT,
                    '<h1>' .
                        e(config('app.name')) .
                        '</h1><p>Welcome. Set a static homepage in Admin → Settings to show a page here.</p>',
                    ['post' => null],
                ) !!}
            </div>
        </div>
    </section>
@endsection
