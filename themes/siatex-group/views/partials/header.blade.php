@php
    $settings = app(\App\Cms\Core\Settings::class);
    $status = (string) $settings->get('status', '', 'core');
    $phone = (string) $settings->get('contact_phone', '', 'core');
    $email = (string) $settings->get('contact_email', '', 'core');

    $o = theme_options();

    $logoId = (int) ($o['logo_media_id'] ?? 0);
    $logo = $logoId ? \App\Models\Media::query()->whereKey($logoId)->first() : null;
    $logoUrl = $logo ? $logo->url() : null;

    $logoWidth = (int) ($o['logo_width'] ?? 200);
    if ($logoWidth <= 0) {
        $logoWidth = 200;
    }

    $logoNaturalWidth = 0;
    $logoNaturalHeight = 0;

    if ($logo instanceof \App\Models\Media) {
        try {
            $logoNaturalWidth = (int) ($logo->width ?? 0);
            $logoNaturalHeight = (int) ($logo->height ?? 0);

            if ($logoNaturalWidth <= 0 || $logoNaturalHeight <= 0) {
                $meta = $logo->meta ?? [];

                if (is_string($meta) && trim($meta) !== '') {
                    $decoded = json_decode($meta, true);
                    $meta = is_array($decoded) ? $decoded : [];
                }

                if (is_array($meta)) {
                    $logoNaturalWidth = (int) (data_get($meta, 'width', 0) ?: data_get($meta, 'image.width', 0));
                    $logoNaturalHeight = (int) (data_get($meta, 'height', 0) ?: data_get($meta, 'image.height', 0));
                }
            }
        } catch (\Throwable $e) {
            $logoNaturalWidth = 0;
            $logoNaturalHeight = 0;
        }
    }

    $logoHeight = 40;
    if ($logoNaturalWidth > 0 && $logoNaturalHeight > 0) {
        $calculatedHeight = (int) round(($logoNaturalHeight / $logoNaturalWidth) * $logoWidth);
        if ($calculatedHeight > 0) {
            $logoHeight = $calculatedHeight;
        }
    }

    // Favicon
    $faviconId = (int) ($o['favicon_media_id'] ?? 0);
    $favicon = $faviconId ? \App\Models\Media::query()->whereKey($faviconId)->first() : null;
    $faviconUrl = $favicon ? $favicon->url() : null;
@endphp

<header id="site-header" class="bg-white">
    <div class="cms-container mx-auto px-4">
        <div class="flex items-center justify-between gap-4 py-7">
            <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="Home">
                @if ($logoUrl)
                    <img class="logo-img" src="{{ $logoUrl }}" alt="{{ config('app.name', 'Siatex') }}"
                        width="{{ $logoWidth }}" height="{{ $logoHeight }}"
                        style="width: {{ $logoWidth }}px; height:auto;" decoding="async">
                @else
                    <span class="text-xl font-extrabold tracking-tight">{{ config('app.name', 'Siatex') }}</span>
                @endif
            </a>

            <div class="flex items-center gap-6 text-sm text-slate-700 data-cms-header-actions">
                @if ($status !== '')
                    <div class="hidden text-slate-600 md:block">
                        {{ $status }}
                    </div>
                @endif

                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                        class="hidden items-center gap-2 hover:text-slate-900 md:flex">
                        <span aria-hidden="true">📞</span>
                        <span class="font-medium">{{ $phone }}</span>
                    </a>
                @endif

                @if ($email !== '')
                    <a href="mailto:{{ $email }}"
                        class="hidden items-center gap-2 hover:text-slate-900 md:flex">
                        <span aria-hidden="true">✉️</span>
                        <span class="font-medium">{{ $email }}</span>
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.icons', '') !!}
            </div>
        </div>
    </div>
</header>
