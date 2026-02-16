@php
    // ✅ Safe vars (prevents "Undefined variable $media/$post")
    $postObj = isset($post) && $post ? $post : null;
    $mediaObj = isset($media) && $media ? $media : null;

    // ------------------------------------
    // SEO input (controller $seo wins)
    // ------------------------------------
    $seoInput = isset($seo) && is_array($seo) ? $seo : null;

    if (!is_array($seoInput)) {
        $postMeta = [];

        if ($postObj && is_array($postObj->meta_json ?? null)) {
            $postMeta = $postObj->meta_json;
        }

        $postSeo = is_array($postMeta['seo'] ?? null) ? $postMeta['seo'] ?? [] : [];
        $seoInput = is_array($postSeo) ? $postSeo : [];
    }

    // ------------------------------------
    // Title base
    // ------------------------------------
    $baseTitle = config('app.name');

    if ($postObj && !empty($postObj->title)) {
        $baseTitle = (string) $postObj->title;
    } elseif ($mediaObj && !empty($mediaObj->title)) {
        $baseTitle = (string) $mediaObj->title;
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
    $canonical = rtrim($canonical, '/');
    if ($canonical === '') {
        $canonical = url('/');
    }

    // ------------------------------------
    // Open Graph
    // ------------------------------------
    $og = is_array($seoInput['og'] ?? null) ? $seoInput['og'] : [];

    $ogTitle = trim((string) ($og['title'] ?? $title));
    $ogDesc = trim((string) ($og['description'] ?? $desc));
    $ogType = trim((string) ($og['type'] ?? 'article'));
    $ogUrl = rtrim(trim((string) ($og['url'] ?? $canonical)), '/');
    if ($ogUrl === '') {
        $ogUrl = url('/');
    }

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
    // ✅ Apply shortcodes to SEO fields
    // ------------------------------------
    $applyShortcodes = function (?string $value) use ($postObj, $mediaObj): string {
        $value = (string) $value;

        if (!function_exists('do_shortcode')) {
            return $value;
        }

        // pass context so [h1] knows if it's post/media
    return (string) do_shortcode($value, [
        'post' => $postObj,
        'media' => $mediaObj,
    ]);
};

$title = trim($applyShortcodes($title));
$desc = trim($applyShortcodes($desc));
$ogTitle = trim($applyShortcodes($ogTitle));
$ogDesc = trim($applyShortcodes($ogDesc));
$twTitle = trim($applyShortcodes($twTitle));
$twDesc = trim($applyShortcodes($twDesc));

// Extra meta tags
$extraMeta = is_array($seoInput['meta'] ?? null) ? $seoInput['meta'] : [];

// ------------------------------------
// JSON-LD (unchanged logic)
// ------------------------------------
$rawJsonLd = '';

if ($postObj) {
    $m = is_array($postObj->meta_json ?? null) ? $postObj->meta_json : [];
    $rawJsonLd = data_get($m, 'custom_json', '') ?: data_get($m, 'seo.custom_json', '');
}

if (($rawJsonLd === '' || $rawJsonLd === null) && $mediaObj) {
    $m2 = is_array($mediaObj->meta ?? null) ? $mediaObj->meta : [];
    $rawJsonLd = data_get($m2, 'custom_json', '') ?: data_get($m2, 'frontend.custom_json', '');
}

$jsonLd = '';

if (is_array($rawJsonLd)) {
    $jsonLd = json_encode($rawJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
} else {
    $jsonLd = trim((string) $rawJsonLd);

    if ($jsonLd !== '') {
        $openTag = '<' . 'script';
        $closeTag = '</' . 'script' . '>';

        $openPos = stripos($jsonLd, $openTag);
        if ($openPos !== false) {
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

$jsonLdIsValid = false;

if ($jsonLd !== '') {
    json_decode($jsonLd, true);
    $jsonLdIsValid = json_last_error() === JSON_ERROR_NONE;

    if ($jsonLdIsValid) {
        $closing = '</' . 'script' . '>';
        $safeClosing = '<' . '\\/' . 'script' . '>';
            $jsonLd = str_replace($closing, $safeClosing, $jsonLd);
        }
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

@foreach ($extraMeta as $name => $content)
    @php
        $name = trim((string) $name);
        $content = trim((string) $content);

        // optional: allow shortcodes inside extra meta too
        if ($content !== '' && function_exists('do_shortcode')) {
            $content = (string) do_shortcode($content, ['post' => $postObj, 'media' => $mediaObj]);
        }
    @endphp

    @if ($name !== '' && $content !== '')
        <meta name="{{ $name }}" content="{{ $content }}">
    @endif
@endforeach

@if ($jsonLdIsValid)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endif
