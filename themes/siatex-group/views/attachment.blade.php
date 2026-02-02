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
        // Category (breadcrumb only)
        // -----------------------------
        $parentPost = null;
        $category = null;

        try {
            $parentPost = $media->posts()->where('status', 'published')->latest('id')->first();
            if ($parentPost) {
                $category = $parentPost->categories()->first();
            }
        } catch (\Throwable $e) {
            $parentPost = null;
            $category = null;
        }

        // -----------------------------
        // ✅ RELATED MEDIA (random images)
        //
        // Goal:
        // - Related grid: up to 10 random images
        // - Related Links: up to 10 random image titles as links
        // - Prefer NO overlap between grid and links
        // - If there aren't enough distinct images, allow overlap so links still show
//
// NOTE:
// We keep it safe by:
// - Fetching a moderate amount and filtering by ->isImage() (no reliance on mime_type column name)
// - Then splitting into grid + links
// -----------------------------
$pool = collect();
$related = collect();
$relatedLinks = collect();

$buildPool = function (array $excludeIds = [], int $fetch = 120) {
    try {
        return \App\Models\Media::query()
            ->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds))
            ->inRandomOrder()
            ->limit($fetch)
            ->get()
            ->filter(fn($m) => $m instanceof \App\Models\Media && $m->isImage())
            ->unique('id')
            ->values();
    } catch (\Throwable $e) {
        return collect();
    }
};

// 1) First pool excluding current media
$pool = $buildPool([$media->id], 200)
    ->shuffle()
    ->values();

// 2) Split pool: first 10 -> grid, next 10 -> links
$related = $pool->take(10)->values();
$relatedLinks = $pool->slice(10, 10)->values();

// 3) If links are less than 10, try to fetch more distinct images to fill links (no overlap)
$need = 10 - $relatedLinks->count();
if ($need > 0) {
    $excludeIds = collect([$media->id])
        ->merge($related->pluck('id'))
        ->merge($relatedLinks->pluck('id'))
        ->unique()
        ->values()
        ->all();

    $extra = $buildPool($excludeIds, 250)->shuffle()->take($need)->values();
    $relatedLinks = $relatedLinks->concat($extra)->take(10)->values();
}

// 4) Still empty / still short? Allow overlap from grid so "Related Links" always shows something.
if ($relatedLinks->count() < 10) {
    $fillFromRelated = $related
        ->reject(fn($m) => $relatedLinks->contains('id', $m->id))
                ->take(10 - $relatedLinks->count())
                ->values();

            $relatedLinks = $relatedLinks->concat($fillFromRelated)->take(10)->values();
        }
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>

            @if ($category)
                <span class="mx-2 text-slate-300">/</span>
                <a class="text-slate-600 hover:underline" href="{{ cms_term_url($category) }}">
                    {{ $category->name }}
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

                {{-- RELATED GRID (max 10, 4 columns on desktop) --}}
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
                    {{-- LEFT (8) --}}
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

                    {{-- RIGHT (4) Related Links (max 10, random media titles) --}}
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
