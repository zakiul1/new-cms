@php
    $state = value($data) ?? [];
    $last = value($lastGenerated) ?? '—';

    $dir = trim((string) ($state['directory'] ?? ''), '/');
    $path = $dir === '' ? 'sitemap.xml' : $dir . '/sitemap.xml';

    $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
    $robots = (bool) ($state['show_in_robots'] ?? true);
@endphp

<div class="space-y-3 text-sm">
    <div>
        <div class="text-xs text-gray-500">Sitemap URL</div>
        <div class="font-medium">{{ url('/sitemap.xml') }}</div>
    </div>

    <div>
        <div class="text-xs text-gray-500">Generated?</div>
        <div class="font-medium">
            {!! $exists ? '✅ sitemap.xml exists' : '❌ Not generated yet' !!}
        </div>
    </div>

    <div>
        <div class="text-xs text-gray-500">Robots.txt</div>
        <div class="font-medium">
            {!! $robots ? '✅ Sitemap advertised' : '❌ Not advertised' !!}
        </div>
    </div>

    <div>
        <div class="text-xs text-gray-500">Last Generated</div>
        <div class="font-medium">{{ $last }}</div>
    </div>
</div>
