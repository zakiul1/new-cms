@php
    $settings = app(\App\Cms\Core\Settings::class);
    $status = (string) $settings->get('status', '', 'core');
    $phone = (string) $settings->get('contact_phone', '', 'core');
    $email = (string) $settings->get('contact_email', '', 'core');

    $o = theme_options();

    $logoId = (int) ($o['logo_media_id'] ?? 0);
    $logo = $logoId ? \App\Models\Media::query()->whereKey($logoId)->first() : null;
    $logoUrl = $logo ? $logo->url() : null;

    $logoWidth = (int) ($o['logo_width'] ?? 180);
    if ($logoWidth <= 0) {
        $logoWidth = 180;
    }

    // ✅ Favicon (from Customizer option)
    $faviconId = (int) ($o['favicon_media_id'] ?? 0);
    $favicon = $faviconId ? \App\Models\Media::query()->whereKey($faviconId)->first() : null;
    $faviconUrl = $favicon ? $favicon->url() : null;
@endphp

{{-- NOTE: Header is fixed by layout shell now, so no sticky/top offsets here --}}
<header id="site-header" class="bg-white">
    <div class="cms-container mx-auto px-4">
        <div class="flex items-center justify-between gap-4 py-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="Home">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; height:auto;">
                @else
                    <span class="text-xl font-extrabold tracking-tight">{{ config('app.name', 'Siatex') }}</span>
                @endif
            </a>

            {{-- ✅ Desktop: show status + phone + email
                 ✅ Mobile: show ONLY email (phone/status hidden) --}}
            <div class="flex items-center gap-6 text-sm text-slate-700 data-cms-header-actions">
                @if ($status !== '')
                    <div class="hidden md:block text-slate-600">
                        {{ $status }}
                    </div>
                @endif

                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                        class="hidden md:flex items-center gap-2 hover:text-slate-900">
                        <span aria-hidden="true">📞</span>
                        <span class="font-medium">{{ $phone }}</span>
                    </a>
                @endif

                @if ($email !== '')
                    <a href="mailto:{{ $email }}" class="hidden md:flex items-center gap-2 hover:text-slate-900">
                        <span aria-hidden="true">✉️</span>
                        <span class="font-medium">{{ $email }}</span>
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.icons', '') !!}
            </div>
        </div>
    </div>
</header>
