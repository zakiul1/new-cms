@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $title = (string) ($post->title ?? '');

        // ✅ NEW: get featured images (multiple) from pivot role=featured
        $featuredMedias = collect();

        try {
            if (method_exists($post, 'featuredMediaPivot')) {
                $featuredMedias = $post->featuredMediaPivot()->get();
            } elseif (method_exists($post, 'mediaPivot')) {
                $featuredMedias = $post
                    ->mediaPivot()
                    ->wherePivot('role', 'featured')
                    ->orderBy('post_media.sort_order')
                    ->get();
            }
        } catch (\Throwable $e) {
            $featuredMedias = collect();
        }

        // ✅ Fallback: legacy single featured image
        if ($featuredMedias->isEmpty() && $post->featuredMedia) {
            $featuredMedias = collect([$post->featuredMedia]);
        }

        // ✅ Keep only images
        $featuredMedias = $featuredMedias
            ->filter(function ($m) {
                return $m && method_exists($m, 'isImage') && $m->isImage();
            })
            ->values();

        $featuredCount = $featuredMedias->count();

        $carouselId = $featuredCount > 1 ? 'post-featured-carousel-' . (int) $post->id : null;

        // ✅ Do NOT build Filament URLs in Blade (avoid panel context issues / wrong resource 404).
        $adminEditUrl = $adminEditUrl ?? url('/lara-admin');

        $category = null;
        try {
            $category = $post->categories()->first();
        } catch (\Throwable $e) {
            $category = null;
        }

        // -----------------------------
        // ✅ HERO CONTENT (under title)
        // -----------------------------
        $allowedHtml = '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a><h1><h2><h3><h4><h5><h6>';

        $rawHtml = '';

        $htmlFromJson = data_get($post->content_json ?? [], 'html');
        if (is_string($htmlFromJson) && trim($htmlFromJson) !== '') {
            $rawHtml = $htmlFromJson;
        }

        if (trim($rawHtml) === '') {
            try {
                $rawHtml = (string) app(\App\Cms\Content\Blocks\BlockRenderer::class)->render(
                    $post->content_json ?? [],
                );
            } catch (\Throwable $e) {
                $rawHtml = '';
            }
        }

        $heroHtml = trim($rawHtml) !== '' ? strip_tags($rawHtml, $allowedHtml) : '';
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>

            {{--    @if ($category)
                <a class="text-slate-600 hover:underline" href="{{ cms_term_url($category) }}">
                    {{ $category->name }}
                </a>
                <span class="mx-2 text-slate-300">/</span>
            @endif --}}

            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    {{-- Hero --}}
    <section class=" mt-3 ">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-6 sm:gap-8 lg:grid-cols-2 bg-slate-50 p-5 sm:p-8 lg:p-10">
                <div class="order-2 lg:order-1">
                    <div class="h-1 w-20 bg-red-500"></div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">
                        Your Tech-pack, Our production
                    </div>

                    <h1 class="mt-3 text-4xl font-extrabold leading-tight tracking-tight text-slate-900">
                        {{ $title }}
                    </h1>

                    @if ($heroHtml !== '')
                        <div class="prose text-justify prose-slate mt-4 max-w-none text-sm leading-7 text-slate-700">
                            {!! $heroHtml !!}
                        </div>
                    @endif

                    <a href="#"
                        class="mt-6 inline-flex items-center bg-[#1f5f99] px-5 py-3 text-sm font-semibold text-white hover:bg-[#194f7f]">
                        Get Price
                    </a>
                </div>

                <div class="order-1 lg:order-2 p-0 sm:p-2 lg:p-4">
                    @if ($featuredCount === 1)
                        @php $media = $featuredMedias->first(); @endphp

                        {!! cms_picture(
                            $media,
                            [
                                'alt' => e($title),
                                'class' => 'w-full object-cover',
                                'sizes' => '(max-width: 1024px) 100vw, 560px',
                                'loading' => 'eager',
                                'decoding' => 'async',
                            ],
                            'large',
                            ['medium', 'medium_large', 'large'],
                        ) !!}
                    @elseif ($featuredCount > 1)
                        <div class="bg-white">
                            <div id="{{ $carouselId }}" class="relative overflow-hidden bg-white" tabindex="0">
                                {{-- Slides --}}
                                <div class="relative h-[360px] md:h-[420px]">
                                    @foreach ($featuredMedias as $index => $m)
                                        <div class="carousel-slide absolute inset-0 transition-opacity duration-300 {{ $index === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' }}"
                                            data-slide="{{ $index }}">
                                            {!! cms_picture(
                                                $m,
                                                [
                                                    'alt' => e($title),
                                                    'class' => 'h-full w-full object-cover',
                                                    'sizes' => '(max-width: 1024px) 100vw, 560px',
                                                    'loading' => $index === 0 ? 'eager' : 'lazy',
                                                    'decoding' => 'async',
                                                ],
                                                'large',
                                                ['medium', 'medium_large', 'large'],
                                            ) !!}
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Prev/Next (SVG icons) --}}
                                <button type="button"
                                    class="carousel-prev cursor-pointer absolute left-1 top-1/2 -translate-y-1/2  p-1 "
                                    aria-label="Previous image">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="h-8 w-8 text-slate-400">
                                        <path d="M15 18l-6-6 6-6" />
                                    </svg>
                                </button>

                                <button type="button"
                                    class="carousel-next cursor-pointer absolute right-1 top-1/2 -translate-y-1/2 p-1 "
                                    aria-label="Next image">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="h-8 w-8 text-slate-400">
                                        <path d="M9 18l6-6-6-6" />
                                    </svg>
                                </button>

                                {{-- Indicators OUTSIDE image (flat gray bars) --}}
                                <div class="mt-7 mb-2   flex justify-center gap-2">
                                    @foreach ($featuredMedias as $index => $m)
                                        <button type="button"
                                            class="carousel-dot h-1 w-5 bg-slate-300 hover:bg-slate-400 transition"
                                            aria-label="Go to image {{ $index + 1 }}"
                                            data-dot="{{ $index }}"></button>
                                    @endforeach
                                </div>
                            </div>


                        </div>
                    @else
                        <div class="h-80 w-full bg-slate-100"></div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ✅ BELOW HERO: RELATED PRODUCTS + META + RELATED LINKS (same behavior like attachment) --}}
    @php
        // -----------------------------
        // ✅ Sub Title + Sub Description (from meta_json)
        // -----------------------------
        $subtitle = trim((string) data_get($post->meta_json ?? [], 'subtitle', ''));

        $subDescRaw = data_get($post->meta_json ?? [], 'sub_description', '');
        if (is_array($subDescRaw)) {
            $subDescRaw = $subDescRaw['html'] ?? ($subDescRaw['value'] ?? '');
        }
        $subDescRaw = is_string($subDescRaw) ? $subDescRaw : '';
        $subDescHtml = trim($subDescRaw) !== '' ? strip_tags($subDescRaw, $allowedHtml) : '';

        // -----------------------------
        // ✅ Related Products (same category) - random, max 10
        // -----------------------------
        $related = collect();
        $relatedLinks = collect();

        $postPermalink = function ($p): string {
            try {
                /** @var \App\Cms\Content\PermalinkManager $permalinks */
                $permalinks = app(\App\Cms\Content\PermalinkManager::class);
                return $permalinks->postUrl($p);
            } catch (\Throwable $e) {
                return url('/' . ltrim((string) ($p->slug ?? ''), '/'));
            }
        };

        $fetchSameCategoryRandom = function (array $excludeIds, int $limit) use ($category) {
            if (!$category) {
                return collect();
            }

            try {
                return \App\Models\Post::query()
                    ->where('type', 'post')
                    ->where('status', 'published')
                    ->whereNotIn('id', $excludeIds)
                    ->whereHas('categories', function ($q) use ($category) {
                        $q->where('terms.id', (int) $category->id);
                    })
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get()
                    ->unique('id')
                    ->values();
            } catch (\Throwable $e) {
                return collect();
            }
        };

        if ($category) {
            // 1) Related products/cards (random) max 10
            $related = $fetchSameCategoryRandom([(int) $post->id], 80)
                ->take(10)
                ->values();

            // 2) Related links (random) max 10, exclude related cards too
            $excludeForLinks = collect([(int) $post->id])
                ->merge($related->pluck('id'))
                ->unique()
                ->values()
                ->all();

            $relatedLinks = $fetchSameCategoryRandom($excludeForLinks, 120)->take(10)->values();

            $need = 10 - $relatedLinks->count();
            if ($need > 0) {
                $more = $fetchSameCategoryRandom([(int) $post->id], 200)
                    ->reject(fn($p) => $relatedLinks->contains('id', $p->id))
                    ->take($need)
                    ->values();

                $relatedLinks = $relatedLinks->concat($more)->take(10)->values();
            }

            // ✅ Absolute fallback: if still empty, at least show something from same category
            if ($relatedLinks->isEmpty()) {
                $relatedLinks = $related->take(10)->values();
            }
        }
    @endphp

    @if ($related->count() || $relatedLinks->count() || $subtitle !== '' || $subDescHtml !== '')
        <section class="bg-white">
            <div class="cms-container mx-auto px-4 py-10">

                {{-- RELATED PRODUCT GRID (2 columns on mobile like attachment) --}}
                @if ($related->count())
                    <div class="mt-8 grid grid-cols-2 gap-8 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($related as $r)
                            @php
                                /** @var \App\Models\Post $r */
                                $rTitle = trim((string) ($r->title ?? ''));
                                $rTitle = $rTitle !== '' ? $rTitle : 'Post';

                                // Featured image for card: prefer new featured pivot first, fallback to legacy
                                $rMedia = null;
                                try {
                                    if (method_exists($r, 'featuredMediaPivot')) {
                                        $rMedia = $r->featuredMediaPivot()->orderBy('post_media.sort_order')->first();
                                    }
                                } catch (\Throwable $e) {
                                    $rMedia = null;
                                }

                                if (!$rMedia) {
                                    try {
                                        $rMedia = $r->featuredMedia;
                                    } catch (\Throwable $e) {
                                        $rMedia = null;
                                    }
                                }

                                $rUrl = $postPermalink($r);
                            @endphp

                            <a href="{{ $rUrl }}" class="group block text-center">
                                <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
                                    @if ($rMedia && method_exists($rMedia, 'isImage') && $rMedia->isImage())
                                        {!! cms_picture(
                                            $rMedia,
                                            [
                                                'alt' => e($rTitle),
                                                'class' => 'h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]',
                                                'sizes' => '(max-width: 768px) 50vw, 220px',
                                                'loading' => 'lazy',
                                                'decoding' => 'async',
                                                'fetchpriority' => 'high',
                                            ],
                                            'medium',
                                            ['thumb', 'medium', 'medium_large'],
                                        ) !!}
                                    @else
                                        <div class="h-full w-full bg-slate-100"></div>
                                    @endif
                                </div>

                                <div class="mx-auto mt-4 w-full max-w-[220px] text-slate-700">
                                    <h3 class="text-sm font-semibold leading-snug line-clamp-2">
                                        {{ $rTitle }}
                                    </h3>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- META + RELATED LINKS (same condition like attachment) --}}
                <div class="mt-14 grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                            {{ $subtitle !== '' ? $subtitle : $title }}
                        </h2>

                        @if ($subDescHtml !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                                {!! $subDescHtml !!}
                            </div>
                        @endif
                    </div>

                    {{-- ✅ Hide on mobile (same as attachment) --}}
                    <div class="hidden lg:block lg:col-span-4">
                        <div class="rounded bg-slate-100 p-6">
                            <div class="text-lg font-semibold text-slate-900">Related Links :</div>

                            @if ($relatedLinks->count())
                                <ul class="mt-4 space-y-3 text-sm text-slate-700">
                                    @foreach ($relatedLinks as $q)
                                        @php
                                            /** @var \App\Models\Post $q */
                                            $qTitle = trim((string) ($q->title ?? ''));
                                            $qTitle = $qTitle !== '' ? $qTitle : 'Post';
                                            $qUrl = $postPermalink($q);
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

    {{-- ✅ Custom JSON (per post) for frontend + optional JSON-LD --}}
    @php
        $customJsonRaw =
            data_get($post->meta_json ?? [], 'custom_json', null) ?:
            data_get($post->meta_json ?? [], 'seo.custom_json', null);

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
    @endphp

    @if ($isJsonLd)
        @push('head')
            <script type="application/ld+json">{!! json_encode($customJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endpush
    @endif

    @if ($featuredCount > 1)
        @push('scripts')
            <script>
                (function() {
                    const root = document.getElementById(@json($carouselId ?? ''));
                    if (!root) return;

                    const slides = Array.from(root.querySelectorAll('.carousel-slide'));
                    const dots = Array.from(document.querySelectorAll('#' + root.id + ' ~ div .carousel-dot')) || [];
                    // Fallback if selector above fails (because indicators are outside root):
                    const indicatorWrap = root.parentElement?.querySelector('.mt-3');
                    const dots2 = indicatorWrap ? Array.from(indicatorWrap.querySelectorAll('.carousel-dot')) : [];
                    const dotsFinal = dots2.length ? dots2 : Array.from(root.parentElement?.querySelectorAll('.carousel-dot') ||
                        []);

                    const prevBtn = root.querySelector('.carousel-prev');
                    const nextBtn = root.querySelector('.carousel-next');

                    let index = 0;

                    function show(i) {
                        index = (i + slides.length) % slides.length;

                        slides.forEach((el, idx) => {
                            const active = idx === index;
                            el.classList.toggle('opacity-100', active);
                            el.classList.toggle('opacity-0', !active);
                            el.classList.toggle('pointer-events-none', !active);
                        });

                        // indicators: flat gray, active darker
                        dotsFinal.forEach((dot, idx) => {
                            dot.classList.toggle('bg-slate-700', idx === index);
                            dot.classList.toggle('bg-slate-300', idx !== index);
                        });
                    }

                    prevBtn?.addEventListener('click', () => show(index - 1));
                    nextBtn?.addEventListener('click', () => show(index + 1));

                    dotsFinal.forEach((dot) => {
                        dot.addEventListener('click', () => {
                            const to = parseInt(dot.getAttribute('data-dot') || '0', 10);
                            show(to);
                        });
                    });

                    root.addEventListener('keydown', (e) => {
                        if (e.key === 'ArrowLeft') show(index - 1);
                        if (e.key === 'ArrowRight') show(index + 1);
                    });

                    // Init: set first indicator active
                    show(0);
                })();
            </script>
        @endpush
    @endif
@endsection
