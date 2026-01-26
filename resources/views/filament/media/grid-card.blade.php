@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Media $record */
    $record = $getRecord();

    $title = (string) ($record->title ?: ($record->original_filename ?: 'Media #' . $record->id));
    $thumb = $record->thumbUrl() ?: $record->url();

    $sizeKb = number_format(((int) $record->size) / 1024, 1);
    $timeText = optional($record->created_at)->diffForHumans() ?: '';

    $type = (string) ($record->mime_type ?: 'application/octet-stream');
    $typeLabel = strtoupper((string) strtok($type, '/'));

    $editUrl = \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $record]);
@endphp

<div class="w-full min-w-0 max-w-full">
    {{-- ✅ WHOLE TILE IS SQUARE --}}
    <div class="group relative aspect-square w-full overflow-hidden rounded-xl bg-white ">
        {{-- Top: image region (always square-ish inside tile) --}}
        <div class="relative h-[72%] w-full overflow-hidden bg-gray-100">
            <img src="{{ $thumb }}" alt="" loading="lazy"
                class="absolute inset-0 h-full w-full object-cover" />

            {{-- Type badge --}}
            <div
                class="absolute left-2 top-2 rounded-md bg-white/90 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                {{ $typeLabel }}
            </div>

            {{-- Hover actions --}}
            <div class="pointer-events-none absolute inset-0 opacity-0 transition group-hover:opacity-100">
                <div class="absolute inset-0 bg-black/10"></div>

                <div class="pointer-events-auto absolute right-2 top-2 flex gap-2">
                    <a href="{{ $editUrl }}"
                        class="rounded-md bg-white/95 px-2 py-1 text-[11px] font-medium text-gray-800"
                        onclick="event.stopPropagation();">
                        Edit
                    </a>

                    <a href="{{ $record->url() }}" target="_blank" rel="noopener"
                        class="rounded-md bg-white/95 px-2 py-1 text-[11px] font-medium text-gray-800"
                        onclick="event.stopPropagation();">
                        View
                    </a>
                </div>
            </div>
        </div>

        {{-- Bottom: meta region (fixed height, never grows) --}}
        <div class="h-[28%] w-full px-2 py-2 min-w-0">
            <div class="truncate text-xs font-semibold text-gray-900" title="{{ $title }}">
                {{ Str::limit($title, 40) }}
            </div>

            <div class="mt-0.5 flex items-center justify-between gap-2 text-[11px] text-gray-500 min-w-0">
                <span class="truncate min-w-0">{{ $timeText }}</span>
                <span class="shrink-0 whitespace-nowrap">{{ $sizeKb }} KB</span>
            </div>
        </div>
    </div>
</div>
