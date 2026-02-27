{{-- themes/siatex-group/views/attachment.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Media $media */

        // ✅ Admin edit URL should come from controller.
        // If not provided, generate safely (no override if already set).
        $adminEditUrl =
            $adminEditUrl ??
            (class_exists(\App\Filament\Resources\MediaResource::class)
                ? \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $media])
                : url('/lara-admin'));

        // -----------------------------
        // Helpers
        // -----------------------------
        // ✅ UPDATED: allow tags needed by shortcodes like [products] (div/picture/img/button etc.)
        // ⚠️ Do NOT allow <script>/<style> here, we will remove those blocks before strip_tags()
        $allowedHtml =
            '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a><h1><h2><h3><h4><h5><h6>' .
            '<div><span><section><article><header><footer>' .
            '<picture><source><img>' .
            '<button>' .
            '<script>';

        // ✅ IMPORTANT FIX:
        // strip_tags() removes <script> tag but keeps its CONTENT, so JS appears as text.
        // Remove script/style blocks completely BEFORE strip_tags().
        $removeScriptStyleBlocks = function (string $html): string {
            $html = preg_replace('~<\s*script\b[^>]*>.*?<\s*/\s*script\s*>~is', '', $html) ?? $html;
            $html = preg_replace('~<\s*style\b[^>]*>.*?<\s*/\s*style\s*>~is', '', $html) ?? $html;
            return $html;
        };

        // ✅ Always work with meta as array (sometimes it may come as JSON string)
        $meta = $media->meta ?? [];
        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($meta)) {
            $meta = [];
        }

        /**
         * ✅ Read editor HTML safely:
         * - string => return it
         * - array  => return ['html'] or ['value'] if present
         * - object => try same via cast
         * - anything else => ''
         */
        $htmlValue = function ($value): string {
            if ($value === null) {
                return '';
            }

            if (is_string($value)) {
                return $value;
            }

            if (is_array($value)) {
                $html = $value['html'] ?? ($value['value'] ?? ($value['content'] ?? ''));
                return is_string($html) ? $html : '';
            }

            if (is_object($value)) {
                $arr = (array) $value;
                $html = $arr['html'] ?? ($arr['value'] ?? ($arr['content'] ?? ''));
                return is_string($html) ? $html : '';
            }

            return '';
        };

        // ✅ UPDATED: run do_shortcode + remove script/style blocks + then strip_tags
        $sanitizeRichHtml = function ($value) use ($allowedHtml, $htmlValue, $removeScriptStyleBlocks): string {
            $html = trim($htmlValue($value));
            if ($html === '') {
                return '';
            }

            if (function_exists('do_shortcode')) {
                try {
                    $html = do_shortcode($html, ['media' => $GLOBALS['media'] ?? null]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $html = $removeScriptStyleBlocks((string) $html);

            return strip_tags($html, $allowedHtml);
        };

        $textValue = function ($value): string {
            if ($value === null) {
                return '';
            }
            if (is_string($value)) {
                return $value;
            }
            return '';
        };

        // ✅ detect empty editor html (<p><br></p>, &nbsp;, etc.)
        $htmlIsEmpty = function ($value) use ($htmlValue): bool {
            $html = trim($htmlValue($value));
            if ($html === '') {
                return true;
            }

            $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace("\xc2\xa0", ' ', $text); // NBSP char
            $text = trim(strip_tags($text));

            return $text === '';
        };

        // ✅ normalize plain text to html for defaults
        $normalizeToHtml = function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }

            // If already has HTML tags, keep as-is
            if ($value !== strip_tags($value)) {
                return $value;
            }

            return '<p>' . nl2br(e($value)) . '</p>';
        };

        // ------------------------------------------------------------------
        // ✅ APPLY DEFAULTS ON FRONTEND (ALWAYS, not only preview)
        // ------------------------------------------------------------------
        if (function_exists('do_action')) {
            // ✅ 1) Preview-only: apply Livewire session state first (for realtime iframe preview)
            if (request()->query('md_preview') === '1') {
                $previewState = session('media_defaults_preview_state', null);

                if (is_array($previewState)) {
                    $d = $previewState['data'] ?? null;

                    if (is_array($d)) {
                        // Title (only if empty)
                        if (!filled($media->title ?? null) && filled($d['default_title'] ?? null)) {
                            $media->title = (string) $d['default_title'];
                        }

                        // Description (Product) (only if empty)
                        if ($htmlIsEmpty($media->description ?? null) && filled($d['default_description'] ?? null)) {
                            $media->description = $normalizeToHtml((string) $d['default_description']);
                        }

                        // Meta fields
                        $m = $media->meta ?? [];
                        if (is_string($m) && trim($m) !== '') {
                            $decoded = json_decode($m, true);
                            $m = is_array($decoded) ? $decoded : [];
                        }
                        if (!is_array($m)) {
                            $m = [];
                        }

                        // Sub title only if empty
                        if (
                            !filled(data_get($m, 'frontend.meta_title', '')) &&
                            filled($d['default_sub_title'] ?? null)
                        ) {
                            data_set($m, 'frontend.meta_title', (string) $d['default_sub_title']);
                        }

                        // Sub description only if empty
                        $curSub = data_get($m, 'frontend.meta_description', null);
                        if ($htmlIsEmpty($curSub) && filled($d['default_sub_description'] ?? null)) {
                            data_set(
                                $m,
                                'frontend.meta_description',
                                $normalizeToHtml((string) $d['default_sub_description']),
                            );
                        }

                        $media->meta = $m;
                    }
                }
            }

            // ✅ 2) Always apply DB-based defaults (category-wise + global fallback)
            do_action('media.attachment.defaults.persist', $media);

            // ✅ Re-read meta after plugin may have mutated it
            $meta = $media->meta ?? [];
            if (is_string($meta) && trim($meta) !== '') {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($meta)) {
                $meta = [];
            }
        }

        // -----------------------------
        // ✅ Frontend meta (from meta.frontend.*)
        // -----------------------------
        $metaTitleRaw = (string) data_get($meta, 'frontend.meta_title', '');
        if (function_exists('do_shortcode')) {
            try {
                $metaTitleRaw = do_shortcode($metaTitleRaw, ['media' => $media]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $metaTitleRaw = $removeScriptStyleBlocks((string) $metaTitleRaw);
        $metaTitle = trim(strip_tags($metaTitleRaw));

        $metaDescRaw = (string) data_get($meta, 'frontend.meta_description', '');
        if (function_exists('do_shortcode')) {
            try {
                $metaDescRaw = do_shortcode($metaDescRaw, ['media' => $media]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $metaDescRaw = $removeScriptStyleBlocks((string) $metaDescRaw);
        $metaDescHtml = strip_tags($metaDescRaw, $allowedHtml);

        // -----------------------------
        // Base title + hero text (SEO-correct)
        // -----------------------------
        // ✅ H1 should be MAIN title (title/default_title), NOT sub title
        $title = (string) ($media->title ?: $media->original_filename ?? '');
        $title = trim($title) !== '' ? trim($title) : 'Attachment';

        // Description (Product) is HTML, Caption is plain text
        $heroDescRaw = (string) ($media->description ?? '');
        if (function_exists('do_shortcode')) {
            try {
                $heroDescRaw = do_shortcode($heroDescRaw, ['media' => $media]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $heroDescRaw = $removeScriptStyleBlocks((string) $heroDescRaw);
        $heroDescHtml = strip_tags($heroDescRaw, $allowedHtml);

        $heroCaption = trim($textValue($media->caption ?? ''));

        $heroHtml = '';
        if ($heroDescHtml !== '') {
            $heroHtml = $heroDescHtml;
        } elseif ($heroCaption !== '') {
            $heroHtml = nl2br(e($heroCaption));
        } elseif ($metaDescHtml !== '') {
            // only as final fallback
            $heroHtml = $metaDescHtml;
        }

        // ✅ Cart/Add-to-cart payload (for ContactForm cart.js)
        $productUrl = filled($media->slug) ? url('/' . ltrim((string) $media->slug, '/')) : url()->current();
        $productImage = '';

        try {
            if (method_exists($media, 'url')) {
                $productImage = (string) $media->url('medium');
            }
        } catch (\Throwable $e) {
            $productImage = '';
        }

        // -----------------------------
        // Breadcrumb taxonomy
        // -----------------------------
        $mediaCategoryTerm = null;
        $mediaCategoryTaxId = null;
        $mediaCategoryIds = [];

        $parentPost = null;
        $postCategory = null;

        try {
            $mediaCategoryTaxId = \App\Models\Taxonomy::query()->where('key', 'media_category')->value('id');

            if ($mediaCategoryTaxId) {
                $mediaCategoryTerm = $media
                    ->terms()
                    ->where('terms.taxonomy_id', $mediaCategoryTaxId)
                    ->where('terms.visibility', 'public')
                    ->orderBy('terms.name')
                    ->first();

                $mediaCategoryIds = $media
                    ->terms()
                    ->where('terms.taxonomy_id', $mediaCategoryTaxId)
                    ->where('terms.visibility', 'public')
                    ->pluck('terms.id')
                    ->map(fn($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            $mediaCategoryTaxId = null;
            $mediaCategoryTerm = null;
            $mediaCategoryIds = [];
        }

        try {
            $parentPost = $media->posts()->where('status', 'published')->latest('id')->first();
            if ($parentPost) {
                $postCategory = $parentPost->categories()->first();
            }
        } catch (\Throwable $e) {
            $parentPost = null;
            $postCategory = null;
        }

        $breadcrumbTerm = $mediaCategoryTerm ?: $postCategory;

        // -----------------------------
        // ✅ STRICT SAME-CATEGORY RELATED (RANDOM)
        // -----------------------------
        $related = collect();
        $relatedLinks = collect();

        $fetchSameCategoryRandom = function (array $excludeIds, int $limit) use (
            $mediaCategoryTaxId,
            $mediaCategoryIds,
        ) {
            if (!$mediaCategoryTaxId || empty($mediaCategoryIds)) {
                return collect();
            }

            try {
                return \App\Models\Media::query()
                    ->where('attachment_public', true)
                    ->whereNotIn('id', $excludeIds)
                    ->whereDoesntHave('terms', function ($t) use ($mediaCategoryTaxId) {
                        $t->where('terms.taxonomy_id', $mediaCategoryTaxId)->where('terms.visibility', 'private');
                    })
                    ->whereHas('terms', function ($q) use ($mediaCategoryTaxId, $mediaCategoryIds) {
                        $q->where('terms.taxonomy_id', $mediaCategoryTaxId)
                            ->whereIn('terms.id', $mediaCategoryIds)
                            ->where('terms.visibility', 'public');
                    })
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get()
                    ->filter(fn($m) => $m instanceof \App\Models\Media && $m->isImage())
                    ->unique('id')
                    ->values();
            } catch (\Throwable $e) {
                return collect();
            }
        };

        if ($mediaCategoryTaxId && !empty($mediaCategoryIds)) {
            $related = $fetchSameCategoryRandom([$media->id], 80)
                ->take(10)
                ->values();

            $excludeForLinks = collect([$media->id])
                ->merge($related->pluck('id'))
                ->unique()
                ->values()
                ->all();

            $relatedLinks = $fetchSameCategoryRandom($excludeForLinks, 80)->take(10)->values();

            $need = 10 - $relatedLinks->count();
            if ($need > 0) {
                $more = $fetchSameCategoryRandom([$media->id], 120)
                    ->reject(fn($m) => $relatedLinks->contains('id', $m->id))
                    ->take($need)
                    ->values();

                $relatedLinks = $relatedLinks->concat($more)->take(10)->values();
            }
        }

        // -----------------------------
        // ✅ Custom JSON (WP-like) loader
        // -----------------------------
        $customJsonRaw = data_get($meta, 'custom_json', null);
        if ($customJsonRaw === null || $customJsonRaw === '') {
            $customJsonRaw = data_get($meta, 'frontend.custom_json', null);
        }

        $customJson = null;

        if (is_array($customJsonRaw)) {
            $customJson = $customJsonRaw;
        } elseif (is_string($customJsonRaw)) {
            $str = trim($customJsonRaw);

            if ($str !== '') {
                $openTag = '<' . 'script';
                $closeTag = '</' . 'script' . '>';

                $openPos = stripos($str, $openTag);

                if ($openPos !== false) {
                    $gtPos = strpos($str, '>', $openPos);
                    if ($gtPos !== false) {
                        $endPos = stripos($str, $closeTag, $gtPos + 1);
                        if ($endPos !== false) {
                            $str = substr($str, $gtPos + 1, $endPos - ($gtPos + 1));
                            $str = trim((string) $str);
                        }
                    }
                }

                $decoded = json_decode($str, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $customJson = $decoded;
                }
            }
        }

        $isJsonLd = is_array($customJson) && (isset($customJson['@context']) || isset($customJson['@type']));
        $printJsonLdHere = false;
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>

            {{--    @if ($breadcrumbTerm)
                <span class="mx-2 text-slate-300">/</span>
                <a class="text-slate-600 hover:underline" href="{{ cms_term_url($breadcrumbTerm) }}">
                    {{ $breadcrumbTerm->name }}
                </a>
            @endif --}}

            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    {{-- HERO --}}
    {{-- HERO --}}
    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-slate-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">
                {{-- IMAGE (sticky on desktop) --}}
                <div class="order-1 lg:order-2 lg:col-span-5 lg:sticky lg:top-24 lg:self-start">
                    @if ($media->isImage())
                        {!! cms_picture(
                            $media,
                            [
                                'alt' => e($title),
                                // make image fit nicely + not overflow viewport
                                'class' => 'w-full object-contain max-h-[70vh]',
                                'sizes' => '(max-width: 1024px) 100vw, 420px',
                                'loading' => 'eager',
                                'decoding' => 'async',
                                'fetchpriority' => 'high',
                            ],
                            'large',
                            ['medium', 'medium_large', 'large'],
                        ) !!}
                    @else
                        <div class="h-80 w-full bg-slate-100"></div>
                    @endif
                </div>

                {{-- CONTENT --}}
                <div class="order-2 min-w-0 lg:order-1 lg:col-span-7">
                    <div class="h-1 w-20 bg-red-500"></div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">
                        Your Tech-pack, Our production
                    </div>

                    <h1 class="mt-3 break-words text-4xl font-extrabold leading-tight tracking-tight text-[#1f5f99]">
                        {{ $title }}
                    </h1>

                    @if ($heroHtml !== '')
                        <div class="mt-4 space-y-4 text-justify text-sm leading-7 text-slate-700">
                            {!! $heroHtml !!}
                        </div>
                    @endif

                    <a href="#"
                        class="cf-get-price mt-8 inline-flex items-center rounded bg-[#1f5f99] px-6 py-3 text-sm font-semibold text-white hover:bg-[#194f7f]"
                        data-item-id="{{ (int) $media->id }}" data-item-type="media" data-item-title="{{ e($title) }}"
                        data-item-url="{{ e($productUrl) }}" data-item-image="{{ e($productImage) }}">
                        Get Price
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- RELATED GRID + META + RELATED LINKS --}}
    @if ($related->count() || $relatedLinks->count() || $metaTitle !== '' || $metaDescHtml !== '')
        <section class="bg-white">
            <div class="cms-container mx-auto px-4 py-10">

                {{-- RELATED GRID --}}
                @if ($related->count())
                    <div class="mt-8 grid grid-cols-2 gap-8 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($related as $r)
                            @php
                                /** @var \App\Models\Media $r */

                                // ✅ Apply plugin defaults in-memory for related cards too (NO SAVE)
                                if (function_exists('do_action')) {
                                    do_action('media.attachment.defaults.persist', $r);
                                }

                                $rMeta = $r->meta ?? [];
                                if (is_string($rMeta) && trim($rMeta) !== '') {
                                    $decoded = json_decode($rMeta, true);
                                    $rMeta = is_array($decoded) ? $decoded : [];
                                }
                                if (!is_array($rMeta)) {
                                    $rMeta = [];
                                }

                                $rMetaTitle = trim((string) data_get($rMeta, 'frontend.meta_title', ''));

                                $rTitle =
                                    (string) ($r->title ?:
                                    ($rMetaTitle !== ''
                                        ? $rMetaTitle
                                        : $r->original_filename ?? ''));
                                $rTitle = trim($rTitle) !== '' ? trim($rTitle) : 'Attachment';

                                $rUrl = filled($r->slug) ? url('/' . ltrim((string) $r->slug, '/')) : $r->url();
                            @endphp

                            <div class="group text-center">
                                <a href="{{ $rUrl }}" class="block">
                                    <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
                                        {!! cms_picture(
                                            $r,
                                            [
                                                'alt' => e($rTitle),
                                                'class' => 'h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]',
                                                'sizes' => '(max-width: 768px) 50vw, 220px',
                                                'loading' => 'lazy',
                                                'decoding' => 'async',
                                            ],
                                            'medium',
                                            ['thumb', 'medium', 'medium_large'],
                                        ) !!}
                                    </div>

                                    <div class="mx-auto mt-4 w-full max-w-[220px] text-slate-700">
                                        <h3 class="text-sm font-semibold leading-snug line-clamp-2">
                                            {{ $rTitle }}
                                        </h3>
                                    </div>
                                </a>

                                {{-- ✅ ADD THIS BUTTON --}}
                                @php
                                    $rImage = '';
                                    try {
                                        if (method_exists($r, 'url')) {
                                            $rImage = (string) $r->url('medium');
                                        }
                                    } catch (\Throwable $e) {
                                        $rImage = '';
                                    }
                                @endphp

                                <button type="button"
                                    class="cf-get-price mt-3 inline-flex items-center justify-center text-sm font-semibold text-[#1f5f99] underline underline-offset-4 hover:text-[#194f7f]"
                                    data-item-id="{{ (int) $r->id }}" data-item-type="media"
                                    data-item-title="{{ e($rTitle) }}" data-item-url="{{ e($rUrl) }}"
                                    data-item-image="{{ e($rImage) }}">
                                    Get Price
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- META + RELATED LINKS --}}
                {{-- ✅ Mobile optimized: stack on mobile, hide Related Links on mobile --}}
                <div class="mt-14 grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                            {{ $metaTitle !== '' ? $metaTitle : $title }}
                        </h2>

                        @if ($metaDescHtml !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                                {!! $metaDescHtml !!}
                            </div>
                        @endif
                    </div>

                    {{-- ✅ Hide on mobile --}}
                    <div class="hidden lg:block lg:col-span-4">
                        <div class="rounded bg-slate-100 p-6">
                            <div class="text-lg font-semibold text-slate-900">Related Links :</div>

                            @if ($relatedLinks->count())
                                <ul class="mt-4 space-y-3 text-sm text-slate-700">
                                    @foreach ($relatedLinks as $q)
                                        @php
                                            /** @var \App\Models\Media $q */

                                            // ✅ Apply plugin defaults in-memory for link titles too (NO SAVE)
                                            if (function_exists('do_action')) {
                                                do_action('media.attachment.defaults.persist', $q);
                                            }

                                            $qMeta = $q->meta ?? [];
                                            if (is_string($qMeta) && trim($qMeta) !== '') {
                                                $decoded = json_decode($qMeta, true);
                                                $qMeta = is_array($decoded) ? $decoded : [];
                                            }
                                            if (!is_array($qMeta)) {
                                                $qMeta = [];
                                            }

                                            $qMetaTitle = trim((string) data_get($qMeta, 'frontend.meta_title', ''));

                                            $qTitle =
                                                (string) ($q->title ?:
                                                ($qMetaTitle !== ''
                                                    ? $qMetaTitle
                                                    : $q->original_filename ?? ''));
                                            $qTitle = trim($qTitle) !== '' ? trim($qTitle) : 'Attachment';

                                            $qUrl = filled($q->slug)
                                                ? url('/' . ltrim((string) $q->slug, '/'))
                                                : $q->url();
                                        @endphp

                                        <li
                                            class="flex items-start gap-2 border-b border-slate-200 pb-3 last:border-b-0 last:pb-0">
                                            <span class="mt-[2px] text-slate-500">›</span>
                                            <a href="{{ $qUrl }}" target="_blank" rel="noopener noreferrer"
                                                class="block truncate italic text-slate-700 hover:text-slate-900 hover:underline"
                                                title="{{ $qTitle }}">
                                                {{ $qTitle }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="mt-3 text-sm text-slate-500">
                                    No related links found.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </section>
    @endif
@endsection
