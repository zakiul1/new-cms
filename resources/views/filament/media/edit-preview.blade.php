@php
    /** @var \App\Models\Media $record */

    $url = $record->url();
    $thumb = $record->thumbUrl() ?: $url;

    $type = (string) ($record->mime_type ?: 'application/octet-stream');
    $sizeKb = number_format(((int) $record->size) / 1024, 1);

    $dims = $record->width && $record->height ? "{$record->width} × {$record->height}" : '—';

    // ✅ cache buster (changes after replace because updated_at changes)
    $v = optional($record->updated_at)->timestamp ?: time();
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
        <div class="h-[340px] w-full overflow-hidden rounded-lg bg-white">
            @if ($record->isImage())
                <img src="{{ $thumb }}?v={{ $v }}" alt="" class="h-full w-full object-contain"
                    loading="lazy" />
            @else
                <div class="flex h-full w-full items-center justify-center text-sm text-gray-600">
                    Preview not available for this file type.
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3">
        <div class="text-sm font-semibold text-gray-900">File info</div>

        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-gray-700">
            <div class="text-gray-500">Type</div>
            <div class="truncate" title="{{ $type }}">{{ $type }}</div>

            <div class="text-gray-500">Size</div>
            <div>{{ $sizeKb }} KB</div>

            <div class="text-gray-500">Dimensions</div>
            <div>{{ $dims }}</div>

            <div class="text-gray-500">Uploaded</div>
            <div class="truncate" title="{{ optional($record->created_at)->toDayDateTimeString() ?: '' }}">
                {{ optional($record->created_at)->toDayDateTimeString() ?: '—' }}
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ $url }}" target="_blank" rel="noopener"
                class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-800">
                Open file
            </a>

            <button type="button"
                class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-800"
                onclick="navigator.clipboard.writeText('{{ $url }}')">
                Copy URL
            </button>
        </div>
    </div>
</div>
