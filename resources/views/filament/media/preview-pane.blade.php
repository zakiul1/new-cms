@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Media $record */
    $thumb = $record->thumbUrl() ?: $record->url();

    $type = (string) ($record->mime_type ?: 'application/octet-stream');
    $sizeKb = number_format(((int) $record->size) / 1024, 1);

    $variants = $record->variants ?? collect();

    $title = (string) ($record->title ?: ($record->original_filename ?: 'Media #' . $record->id));
@endphp

<div class="space-y-4">
    {{-- Header (keeps the panel feeling premium) --}}
    <div class="min-w-0">
        <div class="truncate text-sm font-semibold text-gray-900" title="{{ $title }}">
            {{ $title }}
        </div>
        <div class="mt-0.5 truncate text-xs text-gray-500" title="{{ $record->original_filename }}">
            {{ $record->original_filename ?: '—' }}
        </div>
    </div>

    {{-- Preview (fixed frame to avoid jumpy layout) --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
        <div class="relative w-full overflow-hidden rounded-lg bg-white">
            {{-- Fixed height container so everything stays consistent --}}
            <div class="h-[320px] w-full">
                @if ($record->isImage())
                    <img src="{{ $thumb }}" alt="" class="h-full w-full object-contain" loading="lazy" />
                @else
                    <div class="flex h-full w-full items-center justify-center text-sm text-gray-600">
                        Preview not available for this file type.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick actions (stopPropagation safe if you render inside clickable areas) --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ $record->url() }}" target="_blank" rel="noopener"
            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-800">
            Open file
        </a>

        <button type="button"
            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-800"
            onclick="navigator.clipboard.writeText('{{ $record->url() }}')">
            Copy URL
        </button>
    </div>

    {{-- Info --}}
    <div class="rounded-xl border border-gray-200 bg-white p-3">
        <div class="text-sm font-semibold text-gray-900">File info</div>

        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-gray-700">
            <div class="text-gray-500">Type</div>
            <div class="truncate" title="{{ $type }}">{{ $type }}</div>

            <div class="text-gray-500">Size</div>
            <div>{{ $sizeKb }} KB</div>

            <div class="text-gray-500">Dimensions</div>
            <div>
                @if ($record->width && $record->height)
                    {{ $record->width }} × {{ $record->height }}
                @else
                    —
                @endif
            </div>

            <div class="text-gray-500">Uploaded</div>
            <div class="truncate" title="{{ optional($record->created_at)->toDayDateTimeString() ?: '' }}">
                {{ optional($record->created_at)->toDayDateTimeString() ?: '—' }}
            </div>
        </div>
    </div>

    {{-- Variants --}}
    @if ($record->isImage())
        <div class="rounded-xl border border-gray-200 bg-white p-3">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-gray-900">Variants</div>

                {{-- small helper note --}}
                <div class="text-[11px] text-gray-500">
                    {{ $variants->count() ? $variants->count() . ' generated' : 'processing…' }}
                </div>
            </div>

            <div class="mt-3 space-y-2 text-xs">
                {{-- Original --}}
                <div
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50 px-2 py-2">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-800">Original</div>
                        <div class="truncate text-gray-500" title="{{ $record->url() }}">{{ $record->url() }}</div>
                    </div>

                    <button type="button"
                        class="shrink-0 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-800"
                        onclick="navigator.clipboard.writeText('{{ $record->url() }}')">
                        Copy
                    </button>
                </div>

                {{-- Generated variants --}}
                @foreach ($variants as $v)
                    <div
                        class="flex items-start justify-between gap-3 rounded-lg border border-gray-100 bg-white px-2 py-2">
                        <div class="min-w-0">
                            <div class="font-medium text-gray-800">{{ strtoupper((string) $v->key) }}</div>
                            <div class="truncate text-gray-500" title="{{ $v->url() }}">{{ $v->url() }}</div>
                        </div>

                        <button type="button"
                            class="shrink-0 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-800"
                            onclick="navigator.clipboard.writeText('{{ $v->url() }}')">
                            Copy
                        </button>
                    </div>
                @endforeach

                @if ($variants->count() === 0)
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-gray-600">
                        No variants yet (maybe still processing). Use <span class="font-medium">Regenerate
                            variants</span> if needed.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
