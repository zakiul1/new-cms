@php
    /** @var \App\Models\Post|null $post */
    $seo = is_array($post?->meta_json ?? null) ? $post->meta_json['seo'] ?? [] : [];
    $seo = is_array($seo) ? $seo : [];

    $baseTitle = $post?->title ?? config('app.name');
    $title = trim((string) ($seo['title'] ?? $baseTitle));
    $desc = trim((string) ($seo['description'] ?? ''));

    $robots = trim((string) ($seo['robots'] ?? ''));
    $robots = $robots !== '' ? $robots : 'index, follow';

    $canonical = trim((string) ($seo['canonical'] ?? ''));
    if ($canonical === '') {
        $canonical = url()->current();
    }

    $ogImage = trim((string) ($seo['og_image'] ?? ''));
@endphp

<title>{{ $title }}</title>
<link rel="canonical" href="{{ $canonical }}">

<meta name="robots" content="{{ $robots }}">

@if ($desc !== '')
    <meta name="description" content="{{ $desc }}">
@endif

<meta property="og:title" content="{{ $title }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="article">

@if ($desc !== '')
    <meta property="og:description" content="{{ $desc }}">
@endif

@if ($ogImage !== '')
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif

<meta name="twitter:title" content="{{ $title }}">
@if ($desc !== '')
    <meta name="twitter:description" content="{{ $desc }}">
@endif
