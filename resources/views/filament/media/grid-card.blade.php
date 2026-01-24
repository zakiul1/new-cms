@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Media $record */
    $record = $getRecord();

    $title = (string) ($record->title ?: ($record->original_filename ?: 'Media #' . $record->id));
    $thumb = $record->thumbUrl();

    $sizeKb = number_format(((int) $record->size) / 1024, 1);
    $timeText = optional($record->created_at)->diffForHumans() ?: '';

    // tiny label for the corner badge (optional)
    $type = (string) ($record->mime_type ?: 'application/octet-stream');
    $typeLabel = strtoupper((string) strtok($type, '/'));
@endphp

<div class="w-full min-w-0 max-w-full">
    {{-- Premium tile: no shadow, clean border, consistent size --}}
    <div
        class="group flex w-full min-w-0 max-w-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white">
        {{-- Square thumb --}}
        <div class="relative w-full aspect-square overflow-hidden bg-gray-100">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy"
                    class="absolute inset-0 h-full w-full object-contain p-3" />
            @else
                <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">
                    Processing…
                </div>
            @endif

            {{-- Type badge --}}
            <div
                class="absolute left-2 top-2 rounded-md bg-white/90 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                {{ $typeLabel }}
            </div>

            {{-- Hover actions (won't break layout, stays inside) --}}
            <div class="pointer-events-none absolute inset-0 opacity-0 transition group-hover:opacity-100">
                <div class="absolute inset-0 bg-black/5"></div>

                <div class="pointer-events-auto absolute right-2 top-2 flex gap-2">
                    {{-- NOTE: clicking tile already opens Preview via recordAction('preview') --}}
                    {{-- These buttons are only for quick actions inside tile --}}
                    <button type="button"
                        class="rounded-md bg-white/95 px-2 py-1 text-[11px] font-medium text-gray-800"
                        onclick="event.stopPropagation();" title="Preview">
                        Preview
                    </button>

                    <a href="{{ $record->url() }}" target="_blank" rel="noopener"
                        class="rounded-md bg-white/95 px-2 py-1 text-[11px] font-medium text-gray-800"
                        onclick="event.stopPropagation();" title="Open file">
                        View
                    </a>
                </div>
            </div>
        </div>

        {{-- Meta (always contained, always same rhythm) --}}
        <div class="px-2 py-2 min-w-0">
            <div class="truncate text-xs font-semibold text-gray-900" title="{{ $title }}">
                {{ Str::limit($title, 34) }}
            </div>

            <div class="mt-0.5 flex items-center justify-between gap-2 text-[11px] text-gray-500 min-w-0">
                <span class="truncate min-w-0">{{ $timeText }}</span>
                <span class="shrink-0 whitespace-nowrap">{{ $sizeKb }} KB</span>
            </div>
        </div>
    </div>
</div>
