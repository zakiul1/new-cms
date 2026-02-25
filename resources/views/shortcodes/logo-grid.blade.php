{{-- resources/views/shortcodes/logo-grid.blade.php --}}

@php
    /**
     * Variables expected from [logo] shortcode:
     *  - $items     : Collection|array of Media items
     *  - $column    : int (desktop columns) default 4
     *  - $mobile    : int (mobile columns) default 2
     *  - $showTitle : bool (show media title under image)
     *  - $style     : string 'square'|'round' (default 'square')
     *  - $class     : string wrapper class (optional)
     */

    $items = $items ?? collect();
    if (is_array($items)) {
        $items = collect($items);
    }

    $column = max(1, min(12, (int) ($column ?? 4)));
    $mobile = max(1, min(6, (int) ($mobile ?? 2)));

    $showTitle = (bool) ($showTitle ?? false);

    // Accept "squire" typo too
    $style = strtolower(trim((string) ($style ?? 'square')));
    if ($style === 'squire') {
        $style = 'square';
    }
    $style = in_array($style, ['square', 'round'], true) ? $style : 'square';

    // Class from shortcode (can contain multiple classes)
    $class = trim((string) ($class ?? 'logo_grid'));
    $class = $class !== '' ? $class : 'logo_grid';

    // ✅ Unique scope class per render to avoid conflicts & duplicates
    $scopeClass =
        'logo-scope-' .
        substr(md5($class . '|' . $style . '|' . $column . '|' . $mobile . '|' . uniqid('', true)), 0, 10);

    // Final class attribute (NO duplication)
    $rootClassAttr = trim($class . ' ' . $scopeClass);

    // CSS variables for dynamic grid cols
    $gridStyle = "--lg-cols: {$column}; --sm-cols: {$mobile};";
@endphp

@if ($items->count() > 0)
    <div class="{{ $rootClassAttr }}">
        <div class="logo-grid" style="{{ $gridStyle }} grid-template-columns: repeat(var(--sm-cols), minmax(0, 1fr));">
            @foreach ($items as $item)
                @php
                    $title = trim((string) data_get($item, 'title', ''));

                    // Resolve image URL (support common shapes used in your CMS)
                    $imgUrl = '';
                    try {
                        if (is_object($item) && method_exists($item, 'url')) {
                            $imgUrl = (string) $item->url();
                        } elseif (is_object($item) && property_exists($item, 'url') && is_string($item->url)) {
                            $imgUrl = (string) $item->url;
                        } elseif (is_object($item) && property_exists($item, 'path') && is_string($item->path)) {
                            $imgUrl = (string) $item->path;
                        } elseif (is_string(data_get($item, 'url'))) {
                            $imgUrl = (string) data_get($item, 'url');
                        } elseif (is_string(data_get($item, 'path'))) {
                            $imgUrl = (string) data_get($item, 'path');
                        }
                    } catch (\Throwable $e) {
                        $imgUrl = '';
                    }

                    if (trim($imgUrl) === '') {
                        continue;
                    }

                    $alt = $title !== '' ? $title : 'logo';
                @endphp

                <div class="logo-item logo-item--{{ $style }}">
                    {{-- Image --}}
                    <div class="logo-imgwrap logo-imgwrap--{{ $style }}">
                        <img src="{{ $imgUrl }}" alt="{{ e($alt) }}" loading="lazy" decoding="async" />
                    </div>

                    {{-- Title --}}
                    @if ($showTitle && $title !== '')
                        <div class="logo-title logo-title--{{ $style }}">
                            {{ $title }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <style>
            /* Grid */
            .{{ $scopeClass }} .logo-grid {
                display: grid;
                gap: 20px;
                align-items: center;
                justify-items: center;
                margin-top: 60px;
            }

            @media (min-width: 768px) {
                .{{ $scopeClass }} .logo-grid {
                    grid-template-columns: repeat(var(--lg-cols), minmax(0, 1fr)) !important;
                }
            }

            /**
             * Base item: NO background by default
             * (Prevents square tile behind round logos)
             */
            .{{ $scopeClass }} .logo-item {
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                background: transparent;
                padding: 0;
            }

            /**
             * STYLE MAPPING
             * - square/squire => rectangle logo (no circle)
             * - round         => circle gray bg, centered logo
             */

            /* SQUARE */
            .{{ $scopeClass }} .logo-item--square {
                background: #f9f9f9;
                padding: 5px;
            }

            .{{ $scopeClass }} .logo-imgwrap--square {
                width: 100%;
                background: transparent;
                border-radius: 0;
                overflow: visible;
                display: block;
            }

            .{{ $scopeClass }} .logo-imgwrap--square img {
                width: 100%;
                height: auto;
                display: block;
                object-fit: contain;
            }

            /* ROUND */
            .{{ $scopeClass }} .logo-item--round {
                background: transparent !important;
                padding: 0 !important;
            }

            .{{ $scopeClass }} .logo-imgwrap--round {
                width: 140px;
                height: 140px;
                border-radius: 9999px;
                overflow: hidden;
                background: #f2f2f2;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .{{ $scopeClass }} .logo-imgwrap--round img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
            }

            /* Title */
            .{{ $scopeClass }} .logo-title {
                margin-top: 14px;
                font-size: 16px;
                line-height: 1.2;
                color: #1f5f99;
                text-align: center;
                font-weight: 500;
            }

            .{{ $scopeClass }} .logo-title--round {
                font-size: 15px;
            }

            /* Mobile tweaks */
            @media (max-width: 767px) {
                .{{ $scopeClass }} .logo-grid {
                    gap: 16px;
                    margin-top: 35px;
                }

                .{{ $scopeClass }} .logo-imgwrap--round {
                    width: 110px;
                    height: 110px;
                }

                .{{ $scopeClass }} .logo-item--square {
                    padding: 4px;
                }

                .{{ $scopeClass }} .logo-title {
                    font-size: 14px;
                }
            }
        </style>
    </div>
@endif
