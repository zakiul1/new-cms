@php
    $settings = app(\App\Cms\Core\Settings::class);

    $phone = (string) $settings->get('contact_phone', '', 'core');
    $email = (string) $settings->get('contact_email', '', 'core');

    $o = theme_options();

    $logoId = (int) ($o['logo_media_id'] ?? 0);
    $logo = $logoId ? \App\Models\Media::query()->whereKey($logoId)->first() : null;
    $logoUrl = $logo ? $logo->url() : null;

    $logoWidth = (int) ($o['logo_width'] ?? 180);
    if ($logoWidth <= 0) $logoWidth = 180;
@endphp

<header class="border-b border-slate-200 bg-white">
    <div class="cms-container mx-auto px-4">
        <div class="flex items-center justify-between gap-4 py-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="Home">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; height:auto;">
                @else
                    <span class="text-xl font-extrabold tracking-tight">{{ config('app.name', 'Siatex') }}</span>
                @endif
            </a>

            <div class="flex items-center gap-6 text-sm text-slate-700">
                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-2 hover:text-slate-900">
                        <span aria-hidden="true">📞</span>
                        <span class="font-medium">{{ $phone }}</span>
                    </a>
                @endif

                @if ($email !== '')
                    <a href="mailto:{{ $email }}" class="flex items-center gap-2 hover:text-slate-900">
                        <span aria-hidden="true">✉️</span>
                        <span class="font-medium">{{ $email }}</span>
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.icons', '') !!}
            </div>
        </div>
    </div>
</header>
