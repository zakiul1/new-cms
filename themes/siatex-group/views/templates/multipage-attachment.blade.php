{{-- themes/siatex-group/views/templates/multipage-attachment.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $hooks = app(\App\Cms\Hooks\Hooks::class);

        // CMS settings
        $settings = app(\App\Cms\Core\SettingsRepository::class);

        $sloganTag = trim((string) $settings->get('core', 'slogan_tag', 'Your Tech-pack, Our production'));
        if ($sloganTag === '') {
            $sloganTag = 'Your Tech-pack, Our production';
        }

        $quoteButtonText = trim((string) $settings->get('core', 'quote_button_text', 'Custom Quote'));
        if ($quoteButtonText === '') {
            $quoteButtonText = 'Custom Quote';
        }

        $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonText)));

        $quoteButtonLabel = trim(strip_tags(str_replace('|', ' ', $quoteButtonText)));
        if ($quoteButtonLabel === '') {
            $quoteButtonLabel = 'Custom Quote';
        }

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

        $titleRaw = (string) ($post->title ?? '');
        $title = $sanitizePlainText($titleRaw, $ctx);
        if ($title === '') {
            $title = $titleRaw;
        }

        $rawContent = (string) ($post->content_html ?? data_get($post->content_json ?? [], 'html', ''));
        $heroHtml = $sanitizeRichHtml($rawContent, $ctx);

        $afterBannerRaw = (string) data_get($post->meta_json ?? [], 'after_banner', '');
        $afterBannerHtml = $sanitizeRichHtml($afterBannerRaw, $ctx);

        $subTitleRaw = (string) data_get($post->meta_json ?? [], 'subtitle', '');
        $subTitle = $sanitizePlainText($subTitleRaw, $ctx);

        $subDescRaw = (string) data_get($post->meta_json ?? [], 'sub_description', '');
        $subDescHtml = $sanitizeRichHtml($subDescRaw, $ctx);

        // Product image
        $productImage = null;
        if (method_exists($post, 'mediaPivot')) {
            $productImage = $post
                ->mediaPivot()
                ->wherePivot('role', 'product')
                ->orderBy('post_media.sort_order')
                ->first();
        }

        $productUrl = '';
        try {
            $productUrl = (string) cms_slug_url((string) $post->slug);
        } catch (\Throwable $e) {
            $productUrl = url()->current();
        }
        $safeProductUrl = trim((string) $productUrl);

        $productImageUrl = '';
        $heroPreloadHref = '';

        try {
            if ($productImage) {
                if (method_exists($productImage, 'variantUrl')) {
                    $productImageUrl = (string) ($productImage->variantUrl('medium') ?: $productImage->url());
                    $heroPreloadHref =
                        (string) ($productImage->variantUrl('hero_sm') ?:
                        $productImage->variantUrl('small') ?:
                        $productImage->variantUrl('medium') ?:
                        $productImage->url());
                } elseif (method_exists($productImage, 'url')) {
                    $productImageUrl = (string) $productImage->url('medium');
                    $heroPreloadHref = (string) $productImage->url();
                }
            }
        } catch (\Throwable $e) {
            $productImageUrl = '';
            $heroPreloadHref = '';
        }

        $safeProductImage = trim((string) $productImageUrl);
        $heroPreloadHref = trim((string) $heroPreloadHref);
        $buttonAriaLabel = trim($quoteButtonLabel . ' for ' . $title);

        $companyInfo = '';
        if (class_exists(\Plugins\MultiPage\Support\MultiPageSettings::class)) {
            $mpSettings = \Plugins\MultiPage\Support\MultiPageSettings::load();
            $companyInfo = (string) ($mpSettings['company_info'] ?? '');
        }
        $companyInfoHtml = $sanitizeRichHtml($companyInfo, $ctx);
    @endphp

    @push('head')
        @if ($heroPreloadHref !== '')
            <link rel="preload" as="image" href="{{ $heroPreloadHref }}" imagesizes="(max-width: 1024px) 100vw, 420px"
                fetchpriority="high">
        @endif
    @endpush

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="breadcrumb-text" aria-label="Breadcrumb">
            <a class="underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                href="{{ url('/') }}">
                Home
            </a>
            <span class="mx-2 text-slate-300">/</span>
            <span>{{ $title }}</span>
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
                            'hero_sm',
                            ['thumb', 'small', 'hero_sm', 'large'],
                        ) !!}
                    @else
                        <div class="h-80 w-full bg-slate-100" aria-hidden="true"></div>
                    @endif
                </div>

                {{-- CONTENT (7 cols) --}}
                <div class="order-2 min-w-0 lg:order-1 lg:col-span-7">
                    <div class="h-1 w-20 bg-red-500"></div>

                    <div class="page-slogan mt-4">
                        {{ $sloganTag }}
                    </div>

                    <h1 class="page-hero-title mt-3 break-words">
                        {{ $title }}
                    </h1>

                    @if ($heroHtml !== '')
                        <div class="page-hero-content mt-4 space-y-4">
                            {!! $heroHtml !!}
                        </div>
                    @endif

                    <a href="#"
                        class="cf-get-price mt-8 inline-flex items-center justify-center rounded bg-[var(--cms-primary)] px-6 py-3 text-center text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                        aria-label="{{ $buttonAriaLabel }}" data-default-label="{{ $quoteButtonLabel }}"
                        data-item-id="{{ (int) $post->id }}" data-item-type="multipage"
                        data-item-title="{{ e($title) }}" data-item-url="{{ $safeProductUrl }}"
                        data-item-image="{{ $safeProductImage }}">
                        {!! $quoteButtonHtml !!}
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
        <div class="page-container mx-auto px-4 py-8">
            <div class="grid gap-8 lg:grid-cols-12 lg:items-start">
                {{-- LEFT (8) --}}
                <div class="lg:col-span-8">
                    @if ($subTitle !== '')
                        <h2 class="section-subtitle">
                            {{ $subTitle }}
                        </h2>
                    @endif

                    @if ($subDescHtml !== '')
                        <div class="section-body mt-4">
                            {!! $subDescHtml !!}
                        </div>
                    @endif
                </div>

                {{-- RIGHT (4) sticky --}}
                <div class="lg:col-span-4 lg:sticky lg:top-28 lg:self-start">
                    <div class="rounded bg-slate-100 p-6 company-info-box">
                        {!! $companyInfoHtml !!}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .breadcrumb-text {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .page-slogan {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .page-hero-title {
            font-family: var(--cms-heading-font-family);
            font-size: var(--cms-h1-font-size);
            font-weight: var(--cms-heading-font-weight);
            text-transform: var(--cms-heading-text-transform);
            line-height: var(--cms-heading-line-height);
            letter-spacing: var(--cms-heading-letter-spacing);
            color: var(--cms-heading-color);
        }

        .page-hero-content,
        .page-hero-content p,
        .page-hero-content li {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .section-subtitle {
            font-family: var(--cms-heading-font-family);
            font-size: var(--cms-h1-font-size);
            font-weight: var(--cms-heading-font-weight);
            text-transform: var(--cms-heading-text-transform);
            line-height: var(--cms-heading-line-height);
            letter-spacing: var(--cms-heading-letter-spacing);
            color: var(--cms-heading-color);
        }

        .section-body,
        .section-body p,
        .section-body li,
        .company-info-box,
        .company-info-box p,
        .company-info-box li {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }
    </style>
@endsection
