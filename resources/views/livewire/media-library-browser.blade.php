<div class="space-y-4">
    {{-- Tabs + counter --}}
    <div class="flex items-center gap-2">
        <x-filament::button type="button" size="sm" :color="$tab === 'library' ? 'primary' : 'gray'" wire:click="$set('tab','library')">
            Media Library
        </x-filament::button>

        <x-filament::button type="button" size="sm" :color="$tab === 'upload' ? 'primary' : 'gray'" wire:click="$set('tab','upload')">
            Upload Files
        </x-filament::button>

        <div class="ms-auto text-xs text-gray-500">
            Selected: {{ count($selected) }}
            @if ($maxItems)
                / Max {{ $maxItems }}
            @endif
        </div>
    </div>

    @if ($tab === 'upload')
        {{-- Upload tab --}}
        <div class="space-y-3">
            <div class="text-sm text-gray-600">
                Upload one or more files. Uploaded files will appear in the library and be auto-selected.
            </div>

            <input type="file" multiple wire:model="uploads" class="block w-full text-sm" />

            @error('uploads')
                <div class="text-sm text-danger-600">{{ $message }}</div>
            @enderror
            @error('uploads.*')
                <div class="text-sm text-danger-600">{{ $message }}</div>
            @enderror

            <div class="flex items-center gap-2">
                <x-filament::button type="button" wire:click="upload" wire:loading.attr="disabled">
                    Upload
                </x-filament::button>

                <div wire:loading wire:target="uploads,upload" class="text-sm text-gray-500">
                    Uploading...
                </div>
            </div>
        </div>
    @else
        {{-- Library tab (WP layout) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- Left: search + grid --}}
            <div class="lg:col-span-8 space-y-3">
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input type="search" placeholder="Search media..."
                            wire:model.live.debounce.300ms="search" />
                    </x-filament::input.wrapper>
                </div>

                {{-- Selected strip (like WP) --}}
                @if (count($selected))
                    <div class="flex flex-wrap gap-2 p-2 border rounded-lg bg-gray-50">
                        @foreach ($selected as $sid)
                            @php
                                $m = $selectedMedia?->get((int) $sid);
                                if (!$m) {
                                    continue;
                                }

                                // ✅ prefer jpeg in admin picker
                                $thumb = $m->thumbUrl('jpeg') ?: $m->url();
                            @endphp

                            <button type="button" class="relative group" wire:click="setActive({{ (int) $m->id }})">
                                <img src="{{ $thumb }}" class="w-14 h-14 object-cover rounded-md border"
                                    alt="">
                                <button type="button"
                                    class="absolute -top-2 -right-2 hidden group-hover:flex items-center justify-center bg-white border rounded-full w-6 h-6 text-xs"
                                    wire:click.stop="removeSelected({{ (int) $m->id }})" title="Remove">
                                    ✕
                                </button>
                            </button>
                        @endforeach

                        <div class="ms-auto">
                            <x-filament::button type="button" color="gray" size="sm"
                                wire:click="clearSelection">
                                Clear
                            </x-filament::button>
                        </div>
                    </div>
                @endif

                {{-- Grid --}}
                <div
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-[55vh] overflow-auto pr-1">
                    @foreach ($media as $item)
                        @php
                            $label = $item->title ?: ($item->original_filename ?: 'Media #' . $item->id);

                            // ✅ prefer jpeg in admin picker
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
                                    <div
                                        class="absolute top-2 left-2 bg-gray-900/70 text-white text-[10px] px-2 py-0.5 rounded">
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
            </div>

            {{-- Right: attachment details --}}
            <div class="lg:col-span-4">
                <div class="border rounded-xl p-4 space-y-3">
                    <div class="text-sm font-semibold">Attachment details</div>

                    @if ($activeId)
                        @if ($active)
                            @php
                                $preview = $active->thumbUrl('jpeg') ?: $active->url();
                            @endphp

                            <img src="{{ $preview }}" class="w-full h-48 object-cover rounded-lg border"
                                alt="">

                            <div class="space-y-2">
                                <div>
                                    <label class="text-xs text-gray-600">Title</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model.live="edit.title" />
                                    </x-filament::input.wrapper>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-600">Alt</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model.live="edit.alt" />
                                    </x-filament::input.wrapper>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-600">Caption</label>
                                    <x-filament::input.wrapper>
                                        <textarea class="w-full rounded-md border-gray-300" rows="2" wire:model.live="edit.caption"></textarea>
                                    </x-filament::input.wrapper>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-600">Description</label>
                                    <x-filament::input.wrapper>
                                        <textarea class="w-full rounded-md border-gray-300" rows="3" wire:model.live="edit.description"></textarea>
                                    </x-filament::input.wrapper>
                                </div>

                                <div class="flex items-center gap-2 pt-2">
                                    <x-filament::button type="button" size="sm" wire:click="saveDetails">
                                        Save
                                    </x-filament::button>

                                    <div class="text-xs text-gray-500 ms-auto">
                                        #{{ (int) $active->id }}
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-gray-500">Active item not found.</div>
                        @endif
                    @else
                        <div class="text-sm text-gray-500">Click an image to see details.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer actions --}}
        <div class="flex items-center justify-end gap-2 pt-2 border-t">
            <x-filament::button type="button" wire:click="apply">
                Use selected
            </x-filament::button>
        </div>
    @endif
</div>
