@php
    /**
     * Supports:
     * 1) Posts/Pages: uses $post->meta_json['seo'] (current behavior)
     * 2) Attachment/Custom pages: controller passes $seo array
     */

    // If controller provides $seo as array, treat it as source of truth
    $seoInput = isset($seo) && is_array($seo) ? $seo : null;

    // Fallback to post meta SEO if no $seo provided
    if (!$seoInput) {
        /** @var \App\Models\Post|null $post */
        $postSeo = is_array($post?->meta_json ?? null) ? $post->meta_json['seo'] ?? [] : [];
        $seoInput = is_array($postSeo) ? $postSeo : [];
    }

    // Title/Description
    $baseTitle = $post?->title ?? config('app.name');
    $title = trim((string) ($seoInput['title'] ?? $baseTitle));
    if ($title === '') {
        $title = $baseTitle ?: config('app.name');
    }

    $desc = trim((string) ($seoInput['description'] ?? ''));

    // Robots
    $robots = trim((string) ($seoInput['robots'] ?? ''));
    $robots = $robots !== '' ? $robots : 'index, follow';

    // Canonical
    $canonical = trim((string) ($seoInput['canonical'] ?? ''));
    if ($canonical === '') {
        $canonical = url()->current();
    }

    // OG block (optional)
    $og = is_array($seoInput['og'] ?? null) ? $seoInput['og'] : [];

    $ogTitle = trim((string) ($og['title'] ?? $title));
    $ogDesc = trim((string) ($og['description'] ?? $desc));
    $ogType = trim((string) ($og['type'] ?? 'article'));
    $ogUrl = trim((string) ($og['url'] ?? $canonical));

    // Images:
    // - prefer og.image from $seo['og']['image']
    // - else fallback to legacy $seo['og_image']
    $ogImage = trim((string) ($og['image'] ?? ($seoInput['og_image'] ?? '')));

    // Twitter
    $tw = is_array($seoInput['twitter'] ?? null) ? $seoInput['twitter'] : [];
    $twTitle = trim((string) ($tw['title'] ?? $title));
    $twDesc = trim((string) ($tw['description'] ?? $desc));
    $twCard = trim((string) ($tw['card'] ?? ''));
    if ($twCard === '') {
        $twCard = $ogImage !== '' ? 'summary_large_image' : 'summary';
    }

    // Optional: add extra meta tags if provided
    $extraMeta = is_array($seoInput['meta'] ?? null) ? $seoInput['meta'] : [];
@endphp

<title>{{ $title }}</title>
<link rel="canonical" href="{{ $canonical }}">

<meta name="robots" content="{{ $robots }}">

@if ($desc !== '')
    <meta name="description" content="{{ $desc }}">
@endif

<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:url" content="{{ $ogUrl }}">
<meta property="og:type" content="{{ $ogType }}">

@if ($ogDesc !== '')
    <meta property="og:description" content="{{ $ogDesc }}">
@endif

@if ($ogImage !== '')
    <meta property="og:image" content="{{ $ogImage }}">
@endif

<meta name="twitter:card" content="{{ $twCard }}">
<meta name="twitter:title" content="{{ $twTitle }}">

@if ($twDesc !== '')
    <meta name="twitter:description" content="{{ $twDesc }}">
@endif

@if ($ogImage !== '')
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif

{{-- Extra arbitrary meta tags (optional) --}}
@foreach ($extraMeta as $name => $content)
    @php
        $name = trim((string) $name);
        $content = trim((string) $content);
    @endphp
    @if ($name !== '' && $content !== '')
        <meta name="{{ $name }}" content="{{ $content }}">
    @endif
@endforeach
