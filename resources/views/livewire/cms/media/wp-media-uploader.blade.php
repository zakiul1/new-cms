{{-- resources/views/livewire/cms/media/wp-media-uploader.blade.php --}}

<div x-data="{
    isDropping: false,
    uploading: false,
    progress: 0,

    // Convert dropped files -> set into input -> trigger wire:model upload
    dropFiles(files) {
        const list = Array.from(files || []);
        if (!list.length) return;

        const dt = new DataTransfer();
        list.forEach(f => dt.items.add(f));

        this.$refs.picker.files = dt.files;
        this.$refs.picker.dispatchEvent(new Event('change', { bubbles: true }));
    },
}" class="space-y-4" {{-- ✅ Livewire upload progress events --}}
    x-on:livewire-upload-start.window="uploading=true; progress=0"
    x-on:livewire-upload-progress.window="progress = $event.detail.progress ?? 0"
    x-on:livewire-upload-finish.window="uploading=false; progress=100; setTimeout(()=>progress=0,300)"
    x-on:livewire-upload-error.window="uploading=false; progress=0">

    {{-- FULL PAGE MAGNET OVERLAY --}}
    <div x-cloak x-show="isDropping" x-transition.opacity class="fixed inset-0 z-[999999] bg-black/25"
        style="display:none;" @dragover.prevent
        @drop.prevent.stop="
            isDropping=false;
            dropFiles($event.dataTransfer?.files);
        ">
        <div class="flex h-full w-full items-center justify-center">
            <div
                class="pointer-events-none rounded-xl border border-dashed border-slate-300 bg-white px-10 py-8 text-center shadow-lg">
                <div class="text-sm font-semibold text-slate-900">Drop files to upload</div>
                <div class="mt-1 text-xs text-slate-500">Release to start uploading</div>
            </div>
        </div>
    </div>

    {{-- Global drag detector (no JS file needed) --}}
    <div class="hidden" x-init="const prevent = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(n => window.addEventListener(n, prevent, { passive: false }));
    
    window.addEventListener('dragenter', (e) => {
        prevent(e);
        isDropping = true;
    }, { passive: false });
    window.addEventListener('dragover', (e) => {
        prevent(e);
        isDropping = true;
    }, { passive: false });
    window.addEventListener('dragleave', (e) => {
        prevent(e);
        if (
            e.clientX <= 0 || e.clientY <= 0 ||
            e.clientX >= window.innerWidth || e.clientY >= window.innerHeight
        ) isDropping = false;
    }, { passive: false });
    window.addEventListener('drop', (e) => {
        prevent(e);
        isDropping = false;
    }, { passive: false });"></div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

        {{-- LEFT: uploader --}}
        <div class="lg:col-span-8">
            <div class="rounded-lg border border-dashed border-gray-300 bg-white">
                <div class="p-6 text-center">
                    <div class="text-sm font-semibold text-gray-900">Drop files to upload</div>
                    <div class="mt-1 text-xs text-gray-500">or</div>

                    <label
                        class="mt-3 inline-flex cursor-pointer items-center rounded-md border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-900">
                        Select Files

                        {{-- ✅ KEY: wire:model does the upload --}}
                        <input x-ref="picker" type="file" class="hidden" multiple wire:model="files"
                            @change="$event.target.value=null" />
                    </label>

                    <div class="mt-3 text-[11px] text-gray-500">
                        Maximum upload file size: {{ (int) config('cms-media.max_upload_mb', 50) }} MB.
                    </div>

                    @error('files.*')
                        <div class="mt-2 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                {{-- realtime progress --}}
                <div x-show="uploading" class="border-t border-gray-100 px-4 py-3">
                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <span>Uploading...</span>
                        <span x-text="progress + '%'"></span>
                    </div>

                    <div class="mt-2 h-2 w-full overflow-hidden rounded bg-gray-100">
                        <div class="h-2 rounded bg-primary-600 transition-[width] duration-150"
                            :style="`width: ${progress}%`"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Category --}}
        <div class="lg:col-span-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="text-sm font-semibold text-gray-900">Category</div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Select category</label>
                    <select wire:model="categoryId"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>

                    <p class="mt-2 text-[11px] text-gray-500">
                        If none selected → uploaded items become <span class="font-semibold">Uncategorized</span>.
                    </p>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Create new category</label>

                    <div class="flex items-center gap-2">
                        <input type="text" wire:model.defer="newCategoryName" placeholder="New category name..."
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm" />
                        <button type="button" wire:click="createCategory" wire:loading.attr="disabled"
                            class="shrink-0 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-900 disabled:opacity-60">
                            <span wire:loading.remove>Create</span>
                            <span wire:loading>Creating…</span>
                        </button>
                    </div>

                    @error('newCategoryName')
                        <div class="mt-2 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Uploaded rows --}}
    @if (($uploadedMedia ?? collect())->count())
        <div class="rounded-lg border border-gray-200 bg-white">
            @foreach ($uploadedMedia as $media)
                @php
                    $thumb = $media->isImage() ? ($media->thumbUrl() ?: $media->url()) : null;
                    $fileLabel = $media->original_filename ?: $media->filename;
                    $copyUrl = $media->url();
                    $editUrl = \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $media->id]);
                @endphp

                <div class="flex items-center justify-between gap-4 border-b border-gray-100 p-4 last:border-b-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded bg-gray-100 border border-gray-200">
                            @if ($thumb)
                                <img src="{{ $thumb }}" class="h-full w-full object-contain" alt="" />
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[10px] text-gray-400">
                                    FILE
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-gray-900">
                                {{ $media->title ?: 'Untitled' }}
                            </div>
                            <div class="truncate text-xs text-gray-500">
                                {{ $fileLabel }}
                            </div>

                            <div class="mt-1 flex items-center gap-3 text-xs">
                                <a href="{{ $editUrl }}" class="text-primary-600 hover:underline">Edit</a>
                                <button type="button" class="text-primary-600 hover:underline" x-data
                                    @click.prevent="navigator.clipboard.writeText(@js($copyUrl));">
                                    Copy URL to clipboard
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-xs text-gray-500 shrink-0">
                        {{ $media->created_at?->format('Y-m-d H:i') }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
