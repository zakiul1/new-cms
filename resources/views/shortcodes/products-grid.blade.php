{{-- resources/views/shortcodes/products-grid.blade.php --}}

@php
    $shouldLoadCartAssets = !empty($showPriceBtn);
@endphp

@if ($shouldLoadCartAssets)
    @once
    @endonce
@endif

@php
    /** @var \Illuminate\Support\Collection|\App\Models\Media[] $items */

    $columns = (int) ($columns ?? 4);
    $mobileColumns = (int) ($mobileColumns ?? 2);

    $columns = max(1, min(12, $columns));
    $mobileColumns = max(1, min(6, $mobileColumns));

    $settings = app(\App\Cms\Core\SettingsRepository::class);

    $productStylePrefix = trim((string) $settings->get('core', 'product_style_prefix', 'Art:SC'));
    if ($productStylePrefix === '') {
        $productStylePrefix = 'Art:SC';
    }

    $quoteButtonText = trim((string) $settings->get('core', 'quote_button_text', 'Custom Quote'));
    if ($quoteButtonText === '') {
        $quoteButtonText = 'Custom Quote';
    }

    $quoteButtonLabel = trim(strip_tags(str_replace('|', ' ', $quoteButtonText)));
    if ($quoteButtonLabel === '') {
        $quoteButtonLabel = 'Custom Quote';
    }

    $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonText)));

    $mobileSizeValue = match ($mobileColumns) {
        1 => '100vw',
        2 => '50vw',
        3 => '33.33vw',
        4 => '25vw',
        5 => '20vw',
        6 => '16.66vw',
        default => '50vw',
    };

    $desktopSizeValue = match ($columns) {
        1 => '100vw',
        2 => '50vw',
        3 => '33.33vw',
        4 => '25vw',
        5 => '20vw',
        6 => '16.66vw',
        7 => '14.28vw',
        8 => '12.5vw',
        9 => '11.11vw',
        10 => '10vw',
        11 => '9.09vw',
        12 => '8.33vw',
        default => '25vw',
    };

    $imageSizes = '(max-width: 767px) ' . $mobileSizeValue . ', ' . $desktopSizeValue;
@endphp

<div class="page-container">
    <div class="my-6">
        <div class="grid gap-8 products-grid"
            style="--cols-mobile: {{ $mobileColumns }}; --cols-md: {{ $columns }};">
            @foreach ($items as $media)
                @php
                    $rawTitle = (string) ($media->title ?? ($media->slug ?? 'Product'));
                    $title = trim(strip_tags($rawTitle));
                    if ($title === '') {
                        $title = 'Product';
                    }

                    $url = '';
                    if (filled($media->slug)) {
                        $url = (string) (function_exists('cms_slug_url')
                            ? cms_slug_url((string) $media->slug)
                            : url('/' . trim((string) $media->slug, '/') . '/'));
                    }

                    $productImage = '';
                    try {
                        if ($media instanceof \App\Models\Media) {
                            $productImage =
                                (string) ($media->variantUrl('hero_sm') ?:
                                $media->variantUrl('small') ?:
                                $media->variantUrl('medium') ?:
                                $media->url());
                        }
                    } catch (\Throwable $e) {
                        $productImage = '';
                    }

                    $styleCode = trim(strip_tags($productStylePrefix . (int) $media->id));

                    $safeUrl = trim($url);
                    $safeProductImage = trim($productImage);
                    $safeCurrentUrl = trim((string) url()->current());

                    $buttonItemUrl = $safeUrl !== '' ? $safeUrl : $safeCurrentUrl;
                    $buttonAriaLabel = trim($quoteButtonLabel . ' for ' . $title);
                @endphp

                <div class="group text-center">
                    @if ($safeUrl !== '')
                        <a href="{{ $safeUrl }}"
                            class="block rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                            aria-label="{{ $title }}">
                            <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
                                @if ($media instanceof \App\Models\Media && method_exists($media, 'isImage') && $media->isImage())
                                    @if (function_exists('cms_picture'))
                                        {!! cms_picture(
                                            $media,
                                            [
                                                'alt' => $title,
                                                'class' => 'h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]',
                                                'sizes' => $imageSizes,
                                                'loading' => 'lazy',
                                                'fetchpriority' => 'auto',
                                                'decoding' => 'async',
                                            ],
                                            'small',
                                            ['thumb', 'small', 'hero_sm', 'medium'],
                                        ) !!}
                                    @elseif ($safeProductImage !== '')
                                        <img src="{{ $safeProductImage }}" alt="{{ $title }}"
                                            class="h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]"
                                            loading="lazy" fetchpriority="auto" decoding="async">
                                    @else
                                        <div class="h-full w-full bg-slate-100" aria-hidden="true"></div>
                                    @endif
                                @else
                                    <div class="h-full w-full bg-slate-100" aria-hidden="true"></div>
                                @endif
                            </div>

                            <div class="product-card-text mx-auto mt-4 w-full max-w-[220px]">
                                <div
                                    class="text-sm font-medium leading-snug decoration-[1.5px] group-hover:no-underline">
                                    {{ $styleCode }}
                                </div>

                                <p class="mt-1 line-clamp-2 text-sm font-semibold leading-snug">
                                    {{ $title }}
                                </p>
                            </div>
                        </a>
                    @else
                        <div class="block">
                            <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
                                @if ($media instanceof \App\Models\Media && method_exists($media, 'isImage') && $media->isImage())
                                    @if (function_exists('cms_picture'))
                                        {!! cms_picture(
                                            $media,
                                            [
                                                'alt' => $title,
                                                'class' => 'h-full w-full object-contain',
                                                'sizes' => $imageSizes,
                                                'loading' => 'lazy',
                                                'fetchpriority' => 'auto',
                                                'decoding' => 'async',
                                            ],
                                            'small',
                                            ['thumb', 'small', 'hero_sm', 'medium'],
                                        ) !!}
                                    @elseif ($safeProductImage !== '')
                                        <img src="{{ $safeProductImage }}" alt="{{ $title }}"
                                            class="h-full w-full object-contain" loading="lazy" fetchpriority="auto"
                                            decoding="async">
                                    @else
                                        <div class="h-full w-full bg-slate-100" aria-hidden="true"></div>
                                    @endif
                                @else
                                    <div class="h-full w-full bg-slate-100" aria-hidden="true"></div>
                                @endif
                            </div>

                            <div class="product-card-text mx-auto mt-4 w-full max-w-[220px]">
                                <div
                                    class="text-sm font-medium leading-snug decoration-[1.5px] group-hover:no-underline">
                                    {{ $styleCode }}
                                </div>

                                <p class="mt-1 line-clamp-2 text-sm font-semibold leading-snug">
                                    {{ $title }}
                                </p>
                            </div>
                        </div>
                    @endif

                    @if (!empty($showPriceBtn))
                        <button type="button"
                            class="cf-get-price mt-3 inline-flex cursor-pointer items-center justify-center text-center text-sm font-semibold text-[var(--cms-primary)] underline underline-offset-4 hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                            aria-label="{{ $buttonAriaLabel }}" data-default-label="{{ $quoteButtonLabel }}"
                            data-item-id="{{ (int) $media->id }}" data-item-type="media"
                            data-item-title="{{ $title }}" data-item-url="{{ $buttonItemUrl }}"
                            data-item-image="{{ $safeProductImage }}">
                            {!! $quoteButtonHtml !!}
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
    .product-card-text,
    .product-card-text p,
    .product-card-text div {
        font-family: var(--cms-body-font-family);
        font-size: var(--cms-body-font-size);
        font-weight: var(--cms-body-font-weight);
        line-height: var(--cms-body-line-height);
        letter-spacing: var(--cms-body-letter-spacing);
        color: var(--cms-body-color);
    }
</style>
