<div class="space-y-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:max-w-md">
            <x-filament::input
                type="text"
                wire:model.live="search"
                placeholder="Search media..."
            />
        </div>

        <div class="flex items-center gap-2">
            <input type="file" wire:model="uploads" multiple class="block text-sm" />
            <x-filament::button type="button" wire:click="upload" icon="heroicon-o-arrow-up-tray">
                Upload
            </x-filament::button>

            <x-filament::button type="button" wire:click="confirm" icon="heroicon-o-check" color="primary">
                Use selected
            </x-filament::button>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach($items as $m)
            @php
                $title = $m->title ?: ($m->original_filename ?: ('Media #' . $m->id));
                $thumb = $m->thumbUrl() ?: $m->url();
                $selected = in_array((int) $m->id, $selectedIds, true);
            @endphp

            <button
                type="button"
                wire:click="toggle({{ (int) $m->id }})"
                class="relative rounded-lg border overflow-hidden text-left {{ $selected ? 'ring-2 ring-primary-500' : '' }}"
                title="{{ $title }}"
            >
                <img src="{{ $thumb }}" class="h-24 w-full object-cover" alt="" />
                <div class="p-2 text-xs text-gray-700 truncate">
                    {{ $title }}
                </div>

                @if($selected)
                    <div class="absolute top-1 right-1 bg-primary-600 text-white text-xs px-2 py-0.5 rounded">
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
