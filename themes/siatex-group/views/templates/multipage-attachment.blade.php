{{-- themes/siatex-group/views/templates/multipage-attachment.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $hooks = app(\App\Cms\Hooks\Hooks::class);

        // Admin edit url (multipage)
        if (!isset($adminEditUrl)) {
            if (class_exists(\Plugins\MultiPage\Filament\Resources\MultiPageResource::class)) {
                $adminEditUrl = \Plugins\MultiPage\Filament\Resources\MultiPageResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: 'admin',
                );
            } else {
                $adminEditUrl = url('/lara-admin');
            }
        }

        // -----------------------------
        // Sanitizers (copied from attachment.blade.php style)
        // -----------------------------
        $allowedHtml =
            '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a><h1><h2><h3><h4><h5><h6>' .
            '<div><span><section><article><header><footer>' .
            '<picture><source><img>' .
            '<button>';

        $removeScriptStyleBlocks = function (string $html): string {
            $html = preg_replace('~<\s*script\b[^>]*>.*?<\s*/\s*script\s*>~is', '', $html) ?? $html;
            $html = preg_replace('~<\s*style\b[^>]*>.*?<\s*/\s*style\s*>~is', '', $html) ?? $html;
            return $html;
        };

        /**
         * ✅ IMPORTANT:
         * DO NOT call $hooks->applyFilters(CMS_THE_CONTENT) here,
         * because do_shortcode() in this CMS already runs the content pipeline (filters + shortcodes).
         * Calling both can cause LinkMate (and other filters) to run twice.
         */
        $runShortcodes = function (string $html, array $ctx) {
            if (function_exists('do_shortcode')) {
                try {
                    return (string) do_shortcode($html, $ctx);
                } catch (\Throwable $e) {
                    return $html;
                }
            }
            return $html;
        };

        $sanitizeRichHtml = function (string $html, array $ctx) use (
            $allowedHtml,
            $removeScriptStyleBlocks,
            $runShortcodes,
        ) {
            $html = trim($html);
            if ($html === '') {
                return '';
            }

            $html = $runShortcodes($html, $ctx);
            $html = $removeScriptStyleBlocks($html);

            return strip_tags($html, $allowedHtml);
        };

        $sanitizePlainText = function (string $text, array $ctx) use ($runShortcodes) {
            $text = trim($text);
            if ($text === '') {
                return '';
            }

            $text = $runShortcodes($text, $ctx);

            return trim(strip_tags($text));
        };

        $ctx = ['post' => $post];

        // -----------------------------
        // Data
        // -----------------------------

        // ✅ Title supports {segment-n} + [segment-n] too
        $titleRaw = (string) ($post->title ?? '');
        $title = $sanitizePlainText($titleRaw, $ctx);
        if ($title === '') {
            $title = $titleRaw;
        }

        $rawContent = (string) ($post->content_html ?? data_get($post->content_json ?? [], 'html', ''));
        $heroHtml = $sanitizeRichHtml($rawContent, $ctx);

        // ✅ After Banner
        $afterBannerRaw = (string) data_get($post->meta_json ?? [], 'after_banner', '');
        $afterBannerHtml = $sanitizeRichHtml($afterBannerRaw, $ctx);

        $subTitleRaw = (string) data_get($post->meta_json ?? [], 'subtitle', '');
        $subTitle = $sanitizePlainText($subTitleRaw, $ctx);

        $subDescRaw = (string) data_get($post->meta_json ?? [], 'sub_description', '');
        $subDescHtml = $sanitizeRichHtml($subDescRaw, $ctx);

        // ✅ Product image (first only)
        $productImage = null;
        if (method_exists($post, 'mediaPivot')) {
            $productImage = $post
                ->mediaPivot()
                ->wherePivot('role', 'product')
                ->orderBy('post_media.sort_order')
                ->first();
        }

        // ✅ Company info (shortcodes supported)
        $companyInfo = '';
        if (class_exists(\Plugins\MultiPage\Support\MultiPageSettings::class)) {
            $mpSettings = \Plugins\MultiPage\Support\MultiPageSettings::load();
            $companyInfo = (string) ($mpSettings['company_info'] ?? '');
        }
        $companyInfoHtml = $sanitizeRichHtml($companyInfo, $ctx);
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    {{-- HERO (7 / 5) --}}
    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-8">
            <div class="grid gap-8 bg-slate-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">

                {{-- IMAGE (5 cols) --}}
                <div class="order-1 lg:order-2 lg:col-span-5 lg:sticky lg:top-24 lg:self-start">
                    @if ($productImage)
                        {!! cms_picture(
                            $productImage,
                            [
                                'alt' => e($title),
                                'class' => 'w-full object-contain',
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

                {{-- CONTENT (7 cols) --}}
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
                        data-item-id="{{ (int) $post->id }}" data-item-type="multipage"
                        data-item-title="{{ e($title) }}"
                        data-item-url="{{ e(url('/' . ltrim((string) $post->slug, '/'))) }}"
                        data-item-image="{{ $productImage ? e((string) $productImage->url('medium')) : '' }}">
                        Get Price
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- After Banner (shortcode area) --}}
    @if ($afterBannerHtml !== '')
        <section class="mt-8">
            <div class="cms-container mx-auto px-4">
                {!! $afterBannerHtml !!}
            </div>
        </section>
    @endif

    {{-- 8 / 4 section --}}
    <section class="mt-8 bg-white">
        <div class="cms-container mx-auto px-4 py-8">
            <div class="grid gap-8 lg:grid-cols-12 lg:items-start">

                {{-- LEFT (8) --}}
                <div class="lg:col-span-8">
                    @if ($subTitle !== '')
                        <h2 class="text-3xl font-bold leading-tight text-slate-900">
                            {{ $subTitle }}
                        </h2>
                    @endif

                    @if ($subDescHtml !== '')
                        <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                            {!! $subDescHtml !!}
                        </div>
                    @endif
                </div>

                {{-- RIGHT (4) sticky --}}
                <div class="lg:col-span-4 lg:sticky lg:top-28 lg:self-start">
                    <div class="rounded bg-slate-100 p-6">
                        {!! $companyInfoHtml !!}
                    </div>
                </div>

            </div>
        </div>
    </section>
@endsection
