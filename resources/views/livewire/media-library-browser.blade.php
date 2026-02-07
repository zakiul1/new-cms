<div class="space-y-4">
    {{-- Top bar: title + counter + actions --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <div class="text-sm font-semibold text-gray-900">Media Library</div>

            <div class="text-xs text-gray-500">
                Selected: {{ count($selected) }}
                @if ($maxItems)
                    / Max {{ $maxItems }}
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button type="button" size="sm" wire:click="apply" :disabled="count($selected) === 0">
                Use selected
            </x-filament::button>

            <x-filament::button type="button" size="sm" color="gray" wire:click="clearSelection"
                :disabled="count($selected) === 0">
                Clear
            </x-filament::button>
        </div>
    </div>

    {{-- Filters row (like Media page) --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
        <div class="lg:col-span-6">
            <x-filament::input.wrapper>
                <x-filament::input type="search" placeholder="Search media..."
                    wire:model.live.debounce.300ms="search" />
            </x-filament::input.wrapper>
        </div>

        <div class="lg:col-span-2">
            <x-filament::input.wrapper>
                <select class="w-full rounded-md border-gray-300 text-sm" wire:model.live="type">
                    <option value="all">All types</option>
                    <option value="image">Images</option>
                    <option value="video">Video</option>
                    <option value="pdf">PDF</option>
                    <option value="other">Other</option>
                </select>
            </x-filament::input.wrapper>
        </div>

        <div class="lg:col-span-2">
            <x-filament::input.wrapper>
                <select class="w-full rounded-md border-gray-300 text-sm" wire:model.live="folder">
                    <option value="">All folders</option>
                    @foreach ($folders ?? [] as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>

        <div class="lg:col-span-2">
            <x-filament::input.wrapper>
                <select class="w-full rounded-md border-gray-300 text-sm" wire:model.live="sort">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="name_asc">Title A–Z</option>
                    <option value="name_desc">Title Z–A</option>
                </select>
            </x-filament::input.wrapper>
        </div>
    </div>

    {{-- Selected strip (like WP) --}}
    @if (count($selected))
        <div class="flex flex-wrap gap-2 p-2 border rounded-lg bg-gray-50">
            @foreach ($selected as $sid)
                @php
                    $m = $selectedMedia?->get((int) $sid);
                    if (!$m) {
                        $m = \App\Models\Media::query()->find((int) $sid);
                    }
                    if (!$m) {
                        continue;
                    }

                    $thumb = $m->thumbUrl('jpeg') ?: $m->url();
                @endphp

                <div class="relative group">
                    <img src="{{ $thumb }}" class="w-14 h-14 object-cover rounded-md border" alt="">
                    <button type="button"
                        class="absolute -top-2 -right-2 hidden group-hover:flex items-center justify-center bg-white border rounded-full w-6 h-6 text-xs"
                        wire:click.stop="removeSelected({{ (int) $m->id }})" title="Remove">
                        ✕
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-[60vh] overflow-auto pr-1">
        @foreach ($media as $item)
            @php
                $label = $item->title ?: ($item->original_filename ?: 'Media #' . $item->id);
                $thumb = $item->thumbUrl('jpeg') ?: $item->url();

                $isSelected = in_array((int) $item->id, $selected, true);
                $isActive = (int) $activeId === (int) $item->id;

                $processing = $item->isImage() && is_null($item->processed_at);
            @endphp

            <button type="button"
                class="text-left border rounded-lg overflow-hidden hover:ring-2 focus:outline-none {{ $isActive ? 'ring-2 ring-primary-600' : '' }}"
                wire:click="toggle({{ (int) $item->id }})">
                <div class="relative">
                    <img src="{{ $thumb }}" class="w-full h-28 object-cover" alt="">

                    @if ($processing)
                        <div class="absolute top-2 left-2 bg-gray-900/70 text-white text-[10px] px-2 py-0.5 rounded">
                            Processing…
                        </div>
                    @endif

                    <div
                        class="absolute top-2 right-2 w-5 h-5 rounded-full border bg-white flex items-center justify-center text-xs
                        {{ $isSelected ? 'bg-primary-600 text-white border-primary-600' : '' }}">
                        {{ $isSelected ? '✓' : '' }}
                    </div>
                </div>

                <div class="p-2 text-xs text-gray-700 truncate" title="{{ $label }}">
                    {{ $label }}
                </div>
            </button>
        @endforeach
    </div>

    <div class="flex items-center justify-between">
        <div class="text-xs text-gray-500">
            Showing {{ $media->count() }} items
        </div>

        <div>
            {{ $media->links() }}
        </div>
    </div>

    {{-- Footer actions (keep bottom too) --}}
    <div class="flex items-center justify-end gap-2 pt-2 border-t">
        <x-filament::button type="button" wire:click="apply" :disabled="count($selected) === 0">
            Use selected
        </x-filament::button>
    </div>
</div>
