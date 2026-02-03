@php
    /**
     * Supports:
     * 1) Posts/Pages: uses $post->meta_json['seo'] (current behavior)
     * 2) Attachment/Custom pages: controller passes $seo array
     * 3) JSON-LD: per-record custom JSON (post/page/media) printed in <head>
     */

    // ------------------------------------
    // SEO input (controller $seo wins)
    // ------------------------------------
    $seoInput = isset($seo) && is_array($seo) ? $seo : null;

    if (!is_array($seoInput)) {
        $postMeta = [];

        if (isset($post) && $post && is_array($post->meta_json ?? null)) {
            $postMeta = $post->meta_json;
        }

        $postSeo = is_array($postMeta['seo'] ?? null) ? $postMeta['seo'] ?? [] : [];
        $seoInput = is_array($postSeo) ? $postSeo : [];
    }

    // ------------------------------------
    // Title base
    // ------------------------------------
    $baseTitle = config('app.name');

    if (isset($post) && $post && !empty($post->title)) {
        $baseTitle = (string) $post->title;
    } elseif (isset($media) && $media && !empty($media->title)) {
        $baseTitle = (string) $media->title;
    }

    $title = trim((string) ($seoInput['title'] ?? $baseTitle));
    if ($title === '') {
        $title = $baseTitle ?: config('app.name');
    }

    $desc = trim((string) ($seoInput['description'] ?? ''));

    // ------------------------------------
    // Robots
    // ------------------------------------
    $robots = trim((string) ($seoInput['robots'] ?? ''));
    if ($robots === '') {
        $robots = 'index, follow';
    }

    // ------------------------------------
    // Canonical
    // ------------------------------------
    $canonical = trim((string) ($seoInput['canonical'] ?? ''));
    if ($canonical === '') {
        $canonical = url()->current();
    }

    // ------------------------------------
    // Open Graph
    // ------------------------------------
    $og = is_array($seoInput['og'] ?? null) ? $seoInput['og'] : [];

    $ogTitle = trim((string) ($og['title'] ?? $title));
    $ogDesc = trim((string) ($og['description'] ?? $desc));
    $ogType = trim((string) ($og['type'] ?? 'article'));
    $ogUrl = trim((string) ($og['url'] ?? $canonical));

    $ogImage = trim((string) ($og['image'] ?? ($seoInput['og_image'] ?? '')));

    // ------------------------------------
    // Twitter
    // ------------------------------------
    $tw = is_array($seoInput['twitter'] ?? null) ? $seoInput['twitter'] : [];

    $twTitle = trim((string) ($tw['title'] ?? $title));
    $twDesc = trim((string) ($tw['description'] ?? $desc));
    $twCard = trim((string) ($tw['card'] ?? ''));

    if ($twCard === '') {
        $twCard = $ogImage !== '' ? 'summary_large_image' : 'summary';
    }

    // ------------------------------------
    // Extra meta tags
    // ------------------------------------
    $extraMeta = is_array($seoInput['meta'] ?? null) ? $seoInput['meta'] : [];

    // ------------------------------------
    // ✅ JSON-LD (per record)
    //
    // Priority:
    // - Post/Page: meta_json.custom_json
    // - Post/Page legacy: meta_json.seo.custom_json
    // - Media: meta.custom_json
    // - Media legacy: meta.frontend.custom_json
    // ------------------------------------
    $rawJsonLd = '';

    if (isset($post) && $post) {
        $m = is_array($post->meta_json ?? null) ? $post->meta_json : [];
        $rawJsonLd = data_get($m, 'custom_json', '') ?: data_get($m, 'seo.custom_json', '');
    }

    if (($rawJsonLd === '' || $rawJsonLd === null) && isset($media) && $media) {
        $m2 = is_array($media->meta ?? null) ? $media->meta : [];
        $rawJsonLd = data_get($m2, 'custom_json', '') ?: data_get($m2, 'frontend.custom_json', '');
    }

    // Normalize into a pure JSON string (NO regex, NO literal "<script")
    $jsonLd = '';

    if (is_array($rawJsonLd)) {
        $jsonLd = json_encode($rawJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    } else {
        $jsonLd = trim((string) $rawJsonLd);

        if ($jsonLd !== '') {
            // Avoid literal "<script" so Blade formatter doesn't replace it
        $openTag = '<' . 'script';
        $closeTag = '</' . 'script' . '>';

        $openPos = stripos($jsonLd, $openTag);
        if ($openPos !== false) {
            // find the ">" of the opening script tag
            $gtPos = strpos($jsonLd, '>', $openPos);
            if ($gtPos !== false) {
                $endPos = stripos($jsonLd, $closeTag, $gtPos + 1);
                if ($endPos !== false) {
                    $jsonLd = substr($jsonLd, $gtPos + 1, $endPos - ($gtPos + 1));
                    $jsonLd = trim((string) $jsonLd);
                }
            }
        }
    }
}

// Validate JSON
$jsonLdIsValid = false;
if ($jsonLd !== '') {
        json_decode($jsonLd, true);
        $jsonLdIsValid = json_last_error() === JSON_ERROR_NONE;
    }
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

{{-- Extra arbitrary meta tags --}}
@foreach ($extraMeta as $name => $content)
    @php
        $name = trim((string) $name);
        $content = trim((string) $content);
    @endphp
    @if ($name !== '' && $content !== '')
        <meta name="{{ $name }}" content="{{ $content }}">
    @endif
@endforeach

{{-- ✅ JSON-LD output (only if valid JSON) --}}
@if ($jsonLdIsValid)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endif
