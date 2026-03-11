<div class="space-y-4" x-data="{ open: @entangle('isOpen').live }" x-show="open" x-cloak
    x-on:cms-media-browser-visibility.window="if ($event.detail && typeof $event.detail.open !== 'undefined') open = !!$event.detail.open">

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:max-w-md">
            <x-filament::input type="text" wire:model.live="search" placeholder="Search media..." />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($type === 'image')
                <span
                    class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-700">
                    Image only
                </span>
            @endif

            <input type="file" wire:model="uploads" @if ($multiple) multiple @endif
                class="block text-sm" />

            <x-filament::button type="button" wire:click="upload" icon="heroicon-o-arrow-up-tray">
                Upload
            </x-filament::button>

            <x-filament::button type="button" wire:click="confirm" icon="heroicon-o-check" color="primary"
                :disabled="empty($selectedIds)">
                Use selected
            </x-filament::button>

            <x-filament::button type="button" wire:click="close" color="gray">
                Close
            </x-filament::button>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($items as $m)
            @php
                $title = $m->title ?: ($m->original_filename ?: 'Media #' . $m->id);
                $thumb = $m->thumbUrl('jpeg') ?: $m->url();
                $selected = in_array((int) $m->id, $selectedIds, true);
                $processing = method_exists($m, 'isImage') && $m->isImage() && is_null($m->processed_at);
                $isImage = method_exists($m, 'isImage') ? $m->isImage() : true;
            @endphp

            <button type="button" wire:click="toggle({{ (int) $m->id }})"
                class="relative overflow-hidden rounded-lg border text-left transition {{ $selected ? 'border-primary-400 ring-2 ring-primary-500' : 'border-gray-200 hover:border-gray-300' }}"
                title="{{ $title }}">

                <img src="{{ $thumb }}" class="h-24 w-full object-cover" alt="{{ $title }}" />

                <div class="truncate p-2 text-xs text-gray-700">
                    {{ $title }}
                </div>

                @if (!$isImage)
                    <div class="absolute left-1 top-1 rounded bg-amber-600 px-2 py-0.5 text-[10px] text-white">
                        File
                    </div>
                @endif

                @if ($processing)
                    <div class="absolute left-1 top-1 rounded bg-gray-900/70 px-2 py-0.5 text-[10px] text-white">
                        Processing…
                    </div>
                @endif

                @if ($selected)
                    <div class="absolute right-1 top-1 rounded bg-primary-600 px-2 py-0.5 text-xs text-white">
                        Selected
                    </div>
                @endif
            </button>
        @endforeach
    </div>

    <div>
        {{ $items->links() }}
    </div>
</div>
