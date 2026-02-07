<div class="w-full h-full" x-data="{
    copy(text) { try { navigator.clipboard.writeText(text || ''); } catch (e) {} },
}">

    {{-- WP-like top tabs --}}
    <div class="border-b border-gray-200 bg-white">
        <div class="flex items-center justify-between px-4 py-3">
            <div class="text-sm font-semibold text-gray-900">Media Library</div>

            <div class="flex items-center gap-2">
                <x-filament::button type="button" size="sm" color="gray" wire:click="clearSelection"
                    :disabled="count($selected) === 0">
                    Clear
                </x-filament::button>

                <x-filament::button type="button" size="sm" wire:click="apply" :disabled="count($selected) === 0">
                    Use selected
                </x-filament::button>
            </div>
        </div>

        <div class="flex items-center gap-2 px-4">
            <button type="button" wire:click="setTab('upload')"
                class="px-3 py-2 text-sm border-b-2 -mb-px {{ $tab === 'upload' ? 'border-primary-600 text-primary-700 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                Upload files
            </button>

            <button type="button" wire:click="setTab('library')"
                class="px-3 py-2 text-sm border-b-2 -mb-px {{ $tab === 'library' ? 'border-primary-600 text-primary-700 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                Media Library
            </button>
        </div>
    </div>

    {{-- Body: take all available modal height --}}
    <div class="flex w-full h-[70vh] min-h-0 bg-white">

        {{-- LEFT --}}
        <div class="flex-1 min-w-0 min-h-0 border-r border-gray-200 bg-white">

            {{-- UPLOAD TAB --}}
            @if ($tab === 'upload')
                <div class="p-4 h-full overflow-auto">
                    <livewire:cms.media.wp-media-uploader :wire:key="'wp-uploader-'.$statePath" />

                    <div class="mt-4 text-xs text-gray-500">
                        After upload completes, it will automatically switch to the Media Library tab.
                    </div>
                </div>
            @endif

            {{-- LIBRARY TAB --}}
            @if ($tab === 'library')
                <div class="flex flex-col h-full min-h-0">

                    {{-- Filters row --}}
                    <div class="p-3 border-b border-gray-100 bg-gray-50 shrink-0">
                        <div class="flex flex-wrap items-center gap-2">

                            <select class="border-gray-300 text-sm" wire:model.live="type">
                                <option value="all">All media items</option>
                                <option value="image">Images</option>
                                <option value="video">Video</option>
                                <option value="pdf">PDF</option>
                                <option value="other">Other</option>
                            </select>

                            <select class="border-gray-300 text-sm" wire:model.live="date">
                                @foreach ($dateOptions as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <select class="border-gray-300 text-sm" wire:model.live="category">
                                @foreach ($categoryOptions as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <div class="flex-1 min-w-[220px]">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="search" placeholder="Search media..."
                                        wire:model.live.debounce.300ms="search" />
                                </x-filament::input.wrapper>
                            </div>

                            <div class="text-xs text-gray-500">
                                Selected: {{ count($selected) }}
                                @if ($maxItems)
                                    / Max {{ $maxItems }}
                                @endif
                            </div>
                        </div>

                        {{-- Selected strip (WP: square + hover minus) --}}
                        {{--    @if ($multiple && count($selected))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($selected as $sid)
                                    @php
                                        $m =
                                            $selectedMedia?->get((int) $sid) ?:
                                            \App\Models\Media::query()->find((int) $sid);
                                        if (!$m) {
                                            continue;
                                        }
                                        $thumb = $m->thumbUrl('jpeg') ?: $m->url();
                                    @endphp

                                    <button type="button" wire:click="toggle({{ (int) $m->id }})"
                                        class="group relative w-12 h-12 border overflow-hidden bg-white hover:ring-2 hover:ring-primary-600">
                                        <img src="{{ $thumb }}" class="w-full h-full object-cover"
                                            alt="">

                                        <span
                                            class="absolute top-1 right-1 w-5 h-5 bg-primary-600 text-white text-xs flex items-center justify-center group-hover:hidden">
                                            ✓
                                        </span>

                                        <span
                                            class="absolute inset-0 hidden group-hover:flex items-center justify-center bg-black/35">
                                            <span
                                                class="w-6 h-6 bg-white text-black text-lg leading-none flex items-center justify-center">
                                                −
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif --}}
                    </div>

                    {{-- Grid scroll area --}}
                    <div class="p-3 flex-1 min-h-0 overflow-auto">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            @foreach ($media as $item)
                                @php
                                    $label = $item->title ?: ($item->original_filename ?: 'Media #' . $item->id);
                                    $thumb = $item->thumbUrl('jpeg') ?: $item->url();
                                    $isSelected = in_array((int) $item->id, $selected, true);
                                    $isActive = (int) $activeId === (int) $item->id;
                                    $processing = $item->isImage() && is_null($item->processed_at);
                                @endphp

                                <button type="button" wire:click="toggle({{ (int) $item->id }})"
                                    class="group relative text-left border overflow-hidden bg-white
                                           {{ $isActive ? 'ring-2 ring-primary-600' : '' }}
                                           {{ $isSelected ? 'border-primary-600' : 'border-gray-200' }}">
                                    <div class="relative">
                                        <img src="{{ $thumb }}" class="w-full aspect-square object-cover"
                                            alt="">

                                        @if ($processing)
                                            <div
                                                class="absolute top-2 left-2 bg-gray-900/70 text-white text-[10px] px-2 py-0.5">
                                                Processing…
                                            </div>
                                        @endif

                                        {{-- WP style check / hover minus --}}
                                        <div
                                            class="absolute top-2 right-2 w-6 h-6 border flex items-center justify-center text-sm
                                            {{ $isSelected ? 'bg-primary-600 text-white border-primary-600' : 'bg-white opacity-0 group-hover:opacity-100' }}">
                                            @if ($isSelected)
                                                <span class="group-hover:hidden">✓</span>
                                                <span class="hidden group-hover:inline">−</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="p-2 text-xs text-gray-700 truncate" title="{{ $label }}">
                                        {{ $label }}
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            {{ $media->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT sidebar --}}
        <div class="w-[340px] shrink-0 bg-gray-50 min-h-0">
            <div class="h-full overflow-auto p-4">
                @if ($tab !== 'library')
                    <div class="text-sm text-gray-600">
                        Upload files to add them to your library.
                    </div>
                @elseif ($multiple && count($selected) > 1)
                    <div class="text-sm font-semibold text-gray-900">Selection</div>
                    <div class="mt-2 text-sm text-gray-700">
                        {{ count($selected) }} items selected.
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($selected as $sid)
                            @php
                                $m = $selectedMedia?->get((int) $sid);
                                if (!$m) {
                                    continue;
                                }
                                $thumb = $m->thumbUrl('jpeg') ?: $m->url();
                            @endphp

                            <div class="relative">
                                <img src="{{ $thumb }}" class="w-14 h-14 object-cover border" alt="">
                                <button type="button" wire:click="removeSelected({{ (int) $m->id }})"
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-white border text-xs flex items-center justify-center">
                                    ✕
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <x-filament::button type="button" size="sm" color="gray" wire:click="clearSelection">
                            Clear selection
                        </x-filament::button>
                    </div>
                @else
                    @php $m = $activeMedia; @endphp

                    @if (!$m)
                        <div class="text-sm text-gray-600">
                            Select an item to view details.
                        </div>
                    @else
                        <div class="text-sm font-semibold text-gray-900">Attachment Details</div>

                        <div class="mt-3 border bg-white p-2">
                            @php $thumb = $m->thumbUrl('jpeg') ?: $m->url(); @endphp
                            <img src="{{ $thumb }}" class="w-full aspect-square object-contain bg-gray-100"
                                alt="">
                        </div>

                        <div class="mt-3 text-xs text-gray-700 space-y-1">
                            <div class="font-semibold break-words">
                                {{ $m->original_filename ?: $m->filename }}
                            </div>

                            <div>{{ $m->created_at?->format('F j, Y') }}</div>

                            @if ($m->size)
                                <div>{{ number_format(((int) $m->size) / 1024 / 1024, 2) }} MB</div>
                            @endif

                            @if ($m->width && $m->height)
                                <div>{{ (int) $m->width }} × {{ (int) $m->height }}</div>
                            @endif

                            <div class="pt-2 flex items-center gap-3 text-xs">
                                <a href="{{ \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $m->id]) }}"
                                    class="text-primary-600 hover:underline">
                                    Edit
                                </a>

                                <button type="button" class="text-primary-600 hover:underline"
                                    x-on:click="copy('{{ $m->url() }}')">
                                    Copy URL
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-filament::button type="button" class="w-full" wire:click="apply" :disabled="count($selected) === 0">
                                Use selected
                            </x-filament::button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Bottom bar --}}
    <div class="border-t border-gray-200 bg-white px-4 py-3 flex items-center justify-between">
        <div class="text-xs text-gray-500">
            @if ($tab === 'library')
                Showing {{ $media->count() }} items (page)
            @endif
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button type="button" color="gray" wire:click="clearSelection" :disabled="count($selected) === 0">
                Clear
            </x-filament::button>

            <x-filament::button type="button" wire:click="apply" :disabled="count($selected) === 0">
                Use selected
            </x-filament::button>
        </div>
    </div>

    {{-- ✅ IMPORTANT: define uploader JS ONCE here so Upload tab always has it --}}
    @once
        <script>
            (function() {
                if (typeof window.wpMediaUploader === 'function') return;

                window.wpMediaUploader = function() {
                    return {
                        isDropping: false,
                        uploading: false,
                        progress: 0,

                        queue: [],
                        workerRunning: false,
                        _listenersBound: false,

                        boot() {
                            if (this._listenersBound) return;
                            this._listenersBound = true;

                            const opts = {
                                capture: true,
                                passive: false
                            };
                            const prevent = (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                            };

                            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((name) => {
                                window.addEventListener(name, prevent, opts);
                                document.addEventListener(name, prevent, opts);
                            });

                            window.addEventListener('dragenter', (e) => {
                                prevent(e);
                                this.isDropping = true;
                            }, opts);
                            window.addEventListener('dragover', (e) => {
                                prevent(e);
                                this.isDropping = true;
                            }, opts);

                            window.addEventListener('dragleave', (e) => {
                                prevent(e);
                                if (
                                    e.clientX <= 0 || e.clientY <= 0 ||
                                    e.clientX >= window.innerWidth || e.clientY >= window.innerHeight
                                ) this.isDropping = false;
                            }, opts);

                            window.addEventListener('drop', (e) => {
                                prevent(e);
                                this.isDropping = false;

                                const files = e.dataTransfer?.files ?? null;
                                if (files && files.length) this.startUpload(files);
                            }, opts);
                        },

                        onDrop(e) {
                            this.isDropping = false;
                            const files = e.dataTransfer?.files ?? null;
                            if (files && files.length) this.startUpload(files);
                        },

                        startUpload(fileList) {
                            const incoming = Array.from(fileList || []);
                            if (!incoming.length) return;

                            this.queue = (this.queue || []).concat(incoming);
                            if (this.workerRunning) return;

                            this.workerRunning = true;
                            this.runQueue();
                        },

                        async runQueue() {
                            try {
                                while ((this.queue || []).length) {
                                    const batch = this.queue.splice(0, 5);

                                    this.uploading = true;
                                    this.progress = 0;

                                    await new Promise((resolve, reject) => {
                                        const wire = this.$wire; // must be Alpine’s $wire

                                        if (!wire || typeof wire.uploadMultiple !== 'function') {
                                            console.error('[WP-UPLOADER] $wire.uploadMultiple not ready',
                                                wire);
                                            this.uploading = false;
                                            this.progress = 0;
                                            return reject(new Error('$wire.uploadMultiple not ready'));
                                        }

                                        wire.uploadMultiple(
                                            'files',
                                            batch,
                                            () => {
                                                this.uploading = false;
                                                this.progress = 100;

                                                wire.call('filesUploaded');

                                                setTimeout(() => {
                                                    this.progress = 0;
                                                }, 300);
                                                resolve();
                                            },
                                            (err) => {
                                                console.error('[WP-UPLOADER] upload error', err);
                                                this.uploading = false;
                                                this.progress = 0;
                                                reject(err);
                                            },
                                            (event) => {
                                                const p = event?.detail?.progress ?? 0;
                                                this.progress = Math.max(0, Math.min(100, p));
                                            }
                                        );
                                    });

                                    await new Promise(r => setTimeout(r, 150));
                                }
                            } finally {
                                this.workerRunning = false;
                                this.uploading = false;
                                this.progress = 0;
                            }
                        },
                    };
                };
            })
            ();
        </script>
    @endonce

</div>
