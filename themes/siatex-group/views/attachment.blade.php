{{-- themes/siatex-group/views/attachment.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Media $media */

        // -----------------------------
        // Base title + hero text
        // -----------------------------
        $title = (string) ($media->title ?: $media->original_filename ?? '');
        $title = trim($title) !== '' ? trim($title) : 'Attachment';

        $heroText = trim((string) ($media->description ?? ''));
        if ($heroText === '') {
            $heroText = trim((string) ($media->caption ?? ''));
        }

        // -----------------------------
        // Frontend meta (from meta.frontend.*)
        // -----------------------------
        $metaTitle = trim((string) data_get($media->meta ?? [], 'frontend.meta_title', ''));

        $metaDescHtml = trim((string) data_get($media->meta ?? [], 'frontend.meta_description', ''));
        if ($metaDescHtml !== '') {
            $metaDescHtml = strip_tags($metaDescHtml, '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a>');
        }

        // -----------------------------
        // Breadcrumb:
        // Prefer media_category taxonomy term, fallback to post category
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
                    ->orderBy('terms.name')
                    ->first();

                $mediaCategoryIds = $media
                    ->terms()
                    ->where('terms.taxonomy_id', $mediaCategoryTaxId)
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
                    ->whereNotIn('id', $excludeIds)
                    ->whereHas('terms', function ($q) use ($mediaCategoryTaxId, $mediaCategoryIds) {
                        $q->where('terms.taxonomy_id', $mediaCategoryTaxId)->whereIn('terms.id', $mediaCategoryIds);
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
        // Supports:
        // - meta.custom_json (array OR string JSON)
        // - meta.frontend.custom_json (legacy)
        // - if user pasted a full <script type="application/ld+json">...</script>, extract inner JSON safely
        // -----------------------------
        $customJsonRaw = data_get($media->meta ?? [], 'custom_json', null);
        if ($customJsonRaw === null || $customJsonRaw === '') {
            $customJsonRaw = data_get($media->meta ?? [], 'frontend.custom_json', null);
        }

        $customJson = null;

        if (is_array($customJsonRaw)) {
            $customJson = $customJsonRaw;
        } elseif (is_string($customJsonRaw)) {
            $str = trim($customJsonRaw);

            if ($str !== '') {
                // Avoid literal "<script" in PHP strings (formatter safe)
                $openTag = '<' . 'script';
                $closeTag = '</' . 'script' . '>';

                $openPos = stripos($str, $openTag);

                // If a script tag exists, try to extract the inner content
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

        // JSON-LD detection (basic)
        $isJsonLd = is_array($customJson) && (isset($customJson['@context']) || isset($customJson['@type']));

        // ✅ IMPORTANT:
        // If you already output JSON-LD in <head> (SEO partial), keep this false.
        $printJsonLdHere = false;
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>

            @if ($breadcrumbTerm)
                <span class="mx-2 text-slate-300">/</span>
                <a class="text-slate-600 hover:underline" href="{{ cms_term_url($breadcrumbTerm) }}">
                    {{ $breadcrumbTerm->name }}
                </a>
            @endif

            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    {{-- HERO --}}
    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-slate-50 p-6 md:p-10 lg:grid-cols-12">
                {{-- IMAGE --}}
                <div class="order-1 lg:order-2 lg:col-span-5">
                    @if ($media->isImage())
                        {!! cms_picture(
                            $media,
                            [
                                'alt' => e($title),
                                'class' => 'w-full object-contain',
                                'sizes' => '(max-width: 1024px) 100vw, 420px',
                                'loading' => 'eager',
                                'decoding' => 'async',
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

                    @if ($heroText !== '')
                        <div class="mt-4 space-y-4 text-justify text-sm leading-7 text-slate-700">
                            {!! nl2br(e($heroText)) !!}
                        </div>
                    @endif

                    <a href="#"
                        class="mt-8 inline-flex items-center rounded bg-[#1f5f99] px-6 py-3 text-sm font-semibold text-white hover:bg-[#194f7f]">
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
                                $rTitle = (string) ($r->title ?: $r->original_filename ?? '');
                                $rTitle = trim($rTitle) !== '' ? trim($rTitle) : 'Attachment';
                                $rUrl = filled($r->slug) ? url('/' . ltrim((string) $r->slug, '/')) : $r->url();
                            @endphp

                            <a href="{{ $rUrl }}" class="group block text-center">
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
                        @endforeach
                    </div>
                @endif

                {{-- META + RELATED LINKS --}}
                <div class="mt-14 grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                            {{ $metaTitle !== '' ? $metaTitle : $title }}
                        </h2>

                        @if ($metaDescHtml !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7">
                                {!! $metaDescHtml !!}
                            </div>
                        @endif
                    </div>

                    <div class="lg:col-span-4">
                        <div class="rounded bg-slate-100 p-6">
                            <div class="text-lg font-semibold text-slate-900">Related Links :</div>

                            @if ($relatedLinks->count())
                                <ul class="mt-4 space-y-3 text-sm text-slate-700">
                                    @foreach ($relatedLinks as $q)
                                        @php
                                            /** @var \App\Models\Media $q */
                                            $qTitle = (string) ($q->title ?: $q->original_filename ?? '');
                                            $qTitle = trim($qTitle) !== '' ? trim($qTitle) : 'Attachment';
                                            $qUrl = filled($q->slug)
                                                ? url('/' . ltrim((string) $q->slug, '/'))
                                                : $q->url();
                                        @endphp

                                        <li
                                            class="flex items-start gap-2 border-b border-slate-200 pb-3 last:border-b-0 last:pb-0">
                                            <span class="mt-[2px] text-slate-500">›</span>
                                            <a href="{{ $qUrl }}"
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
