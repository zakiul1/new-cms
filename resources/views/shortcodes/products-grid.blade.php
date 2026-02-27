{{-- resources/views/shortcodes/products-grid.blade.php --}}
@php
    /** @var \Illuminate\Support\Collection|\App\Models\Media[] $items */

    $columns = (int) ($columns ?? 4); // desktop/tablet columns
    $mobileColumns = (int) ($mobileColumns ?? 2); // mobile columns

    // allow any number safely
    $columns = max(1, min(12, $columns));
    $mobileColumns = max(1, min(6, $mobileColumns));
@endphp
<div class="page-container">
    <div class="my-6">
        {{-- ✅ No script. Use CSS variables. --}}
        <div class="grid gap-8 products-grid"
            style="--cols-mobile: {{ $mobileColumns }}; --cols-md: {{ $columns }};">
            @foreach ($items as $media)
                @php
                    $title = (string) ($media->title ?? ($media->slug ?? 'Product'));
                    $url = filled($media->slug) ? url('/' . ltrim((string) $media->slug, '/')) : '#';

                    $productImage = '';
                    try {
                        if (method_exists($media, 'url')) {
                            $productImage = (string) $media->url('medium');
                        }
                    } catch (\Throwable $e) {
                        $productImage = '';
                    }
                @endphp

                <div class="group text-center">
                    <a href="{{ $url }}" class="block">
                        <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
                            @if (method_exists($media, 'isImage') && $media->isImage())
                                {!! cms_picture(
                                    $media,
                                    [
                                        'alt' => e($title),
                                        'class' => 'h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]',
                                        'sizes' => '(max-width: 768px) 50vw, 220px',
                                        'loading' => 'lazy',
                                        'decoding' => 'async',
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
                                {{ $title }}
                            </h3>
                        </div>
                    </a>

                    @if (!empty($showPriceBtn))
                        <button type="button"
                            class="cf-get-price cursor-pointer mt-3 inline-flex items-center justify-center text-sm font-semibold text-[#1f5f99] underline underline-offset-4 hover:text-[#194f7f]"
                            data-item-id="{{ (int) $media->id }}" data-item-type="media"
                            data-item-title="{{ e($title) }}" data-item-url="{{ e($url) }}"
                            data-item-image="{{ e($productImage) }}">
                            Get Price
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
