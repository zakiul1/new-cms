<div class="rounded-lg border bg-white p-4 space-y-2">
    <div class="text-lg font-semibold text-slate-900">{{ $title }}</div>

    @if (!empty($url))
        <div class="text-sm">
            <a href="{{ $url }}" target="_blank" class="text-primary-600 hover:underline">
                {{ $url }}
            </a>
        </div>
    @endif

    @if (!empty($excerpt))
        <div class="text-sm text-slate-600">
            {{ $excerpt }}
        </div>
    @endif

    <div class="text-xs text-slate-500">
        Preview only. Save to apply on frontend.
    </div>
</div>
