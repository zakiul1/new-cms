@extends('theme::layouts.app')

@php
    // ✅ Home page content comes from CMS page: type=page, slug=home
    $homePage = \App\Models\Post::query()
        ->where('type', 'page')
        ->where('slug', 'home')
        ->where('status', 'published')
        ->first();

    // ✅ If home page exists, use its SEO meta for layout
    // layout/app.blade.php expects $seo in some routes, so we provide it here too
    $seo = null;

    if ($homePage) {
        $meta = is_array($homePage->meta_json) ? $homePage->meta_json : [];
        $seoData = isset($meta['seo']) && is_array($meta['seo']) ? $meta['seo'] : [];

        $title = trim((string) ($seoData['title'] ?? $homePage->title ?? 'Home'));
        $desc = trim((string) ($seoData['description'] ?? $homePage->excerpt ?? ''));
        $canonical = trim((string) ($seoData['canonical'] ?? '')) ?: url('/');
        $robots = trim((string) ($seoData['robots'] ?? '')) ?: 'index, follow';
        $ogImage = trim((string) ($seoData['og_image'] ?? ''));

        $seo = [
            'title' => $title,
            'description' => $desc !== '' ? $desc : null,
            'canonical' => $canonical,
            'robots' => $robots,
            'og' => [
                'title' => $title,
                'description' => $desc !== '' ? $desc : null,
                'type' => 'website',
                'url' => $canonical,
                'image' => $ogImage !== '' ? $ogImage : null,
            ],
        ];
    }
@endphp

@section('title', $homePage?->title ?? 'Home')

@section('content')
    {{-- ✅ HERO from plugin (recommended hook) --}}
    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.front.top', '') !!}

    {{-- ✅ Intro + other sections from CMS blocks --}}
    <div class="cms-container" style="padding: 22px 0;">
        @if ($homePage)
            {!! app(\App\Cms\Content\Blocks\BlockRenderer::class)->render($homePage->content_json) !!}
        @else
            <p style="opacity:.7">
                Create a CMS Page with slug: <strong>home</strong>
            </p>
        @endif
    </div>

    {{-- Optional bottom hook if you need later --}}
    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.front.bottom', '') !!}
@endsection
