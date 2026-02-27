{{-- resources/views/livewire/cms/media/wp-media-uploader.blade.php --}}

<div x-data="wpUploader({
    maxMb: @js((int) config('cms-media.max_upload_mb', 50)),
})" class="space-y-4">

    {{-- FULL PAGE MAGNET OVERLAY --}}
    <div x-cloak x-show="isDropping" x-transition.opacity class="fixed inset-0 z-[999999] bg-black/25"
        style="display:none;" @dragover.prevent
        @drop.prevent.stop="
            isDropping=false;
            addFiles($event.dataTransfer?.files);
        ">
        <div class="flex h-full w-full items-center justify-center">
            <div
                class="pointer-events-none rounded-xl border border-dashed border-slate-300 bg-white px-10 py-8 text-center shadow-lg">
                <div class="text-sm font-semibold text-slate-900">Drop files to upload</div>
                <div class="mt-1 text-xs text-slate-500">Release to start uploading</div>
            </div>
        </div>
    </div>

    {{-- Global drag detector --}}
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

                        {{-- No wire:model here --}}
                        <input x-ref="picker" type="file" class="hidden" multiple
                            @change="addFiles($event.target.files); $event.target.value=null;" />
                    </label>

                    <div class="mt-3 text-[11px] text-gray-500">
                        Maximum upload file size: {{ (int) config('cms-media.max_upload_mb', 50) }} MB.
                    </div>

                    <template x-if="errors.length">
                        <div class="mt-2 space-y-1">
                            <template x-for="(err, i) in errors" :key="i">
                                <div class="text-xs text-red-600" x-text="err"></div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- optional overall status --}}
                <div x-show="isUploading" class="border-t border-gray-100 px-4 py-3">
                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <span x-text="statusText"></span>
                        <span x-text="overallPercent + '%'"></span>
                    </div>

                    <div class="mt-2 h-2 w-full overflow-hidden rounded bg-gray-100">
                        <div class="h-2 rounded bg-primary-600 transition-[width] duration-150"
                            :style="`width: ${overallPercent}%`"></div>
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
                        @foreach ($categoryOptions ?? collect() as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
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

    {{-- ✅ ONE AREA ONLY --}}
    <div class="rounded-lg border border-gray-200 bg-white">

        {{-- Uploading rows --}}
        <template x-for="item in queue" :key="item.id">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 p-4"
                x-show="item.visible !== false">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded bg-gray-100 border border-gray-200">
                        <template x-if="item.preview">
                            <img :src="item.preview" class="h-full w-full object-contain" alt="" />
                        </template>
                        <template x-if="!item.preview">
                            <div class="flex h-full w-full items-center justify-center text-[10px] text-gray-400">FILE
                            </div>
                        </template>
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-900" x-text="item.name"></div>
                        <div class="truncate text-xs text-gray-500">
                            <span x-text="item.prettySize"></span>
                            <span class="mx-2">•</span>
                            <span x-text="item.statusLabel"
                                :class="{
                                    'text-gray-500': item.status === 'queued',
                                    'text-primary-600': item.status === 'uploading',
                                    'text-green-600': item.status === 'done',
                                    'text-red-600': item.status === 'error',
                                }"></span>
                            <template x-if="item.error">
                                <span class="ml-2 text-red-600" x-text="item.error"></span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- right side progress --}}
                <div class="shrink-0 w-40">
                    <template x-if="item.status === 'uploading'">
                        <div>
                            <div class="flex items-center justify-between text-[11px] text-gray-600">
                                <span>Uploading</span>
                                <span x-text="item.progress + '%'"></span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded bg-gray-100">
                                <div class="h-2 rounded bg-primary-600 transition-[width] duration-75"
                                    :style="`width: ${item.progress}%`"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="item.status === 'done'">
                        <div class="text-right text-xs text-green-600 font-semibold">Completed</div>
                    </template>

                    <template x-if="item.status === 'queued'">
                        <div class="text-right text-xs text-gray-500">Pending</div>
                    </template>

                    <template x-if="item.status === 'error'">
                        <div class="text-right text-xs text-red-600 font-semibold">Failed</div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Uploaded media rows --}}
        @if (($uploadedMedia ?? collect())->count())
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
                                    FILE</div>
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

                                <button type="button" class="text-primary-600 hover:underline"
                                    @click.prevent="safeCopy(@js($copyUrl))">
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
        @endif
    </div>
</div>

@once
    <script>
        if (typeof window.wpUploader !== 'function') {
            window.wpUploader = function({
                maxMb
            } = {}) {
                return {
                    isDropping: false,

                    queue: [],
                    errors: [],

                    isUploading: false,
                    statusText: 'Uploading…',
                    overallPercent: 0,

                    // ✅ copy helper (http-safe)
                    safeCopy(text) {
                        const t = String(text || '');
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(t).catch(() => this.fallbackCopy(t));
                            return;
                        }
                        this.fallbackCopy(t);
                    },

                    fallbackCopy(text) {
                        const el = document.createElement('textarea');
                        el.value = text;
                        el.setAttribute('readonly', '');
                        el.style.position = 'fixed';
                        el.style.left = '-9999px';
                        el.style.top = '-9999px';
                        document.body.appendChild(el);
                        el.select();
                        try {
                            document.execCommand('copy');
                        } catch (e) {}
                        document.body.removeChild(el);
                    },

                    addFiles(fileList) {
                        this.errors = [];

                        const files = Array.from(fileList || []);
                        if (!files.length) return;

                        const maxBytes = (Number(maxMb) || 50) * 1024 * 1024;

                        for (const f of files) {
                            if (f.size > maxBytes) {
                                this.errors.push(`${f.name} is too large. Max ${maxMb} MB.`);
                                continue;
                            }
                            this.queue.push(this.makeItem(f));
                        }

                        this.startUploads();
                    },

                    makeItem(file) {
                        const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                        return {
                            id,
                            file,
                            name: file.name,
                            size: file.size,
                            prettySize: this.humanSize(file.size),
                            status: 'queued',
                            statusLabel: 'Queued',
                            progress: 0,
                            error: null,
                            visible: true,
                            preview: (file?.type || '').startsWith('image/') ? URL.createObjectURL(file) : null,
                        };
                    },

                    humanSize(bytes) {
                        const thresh = 1024;
                        if (Math.abs(bytes) < thresh) return bytes + ' B';
                        const units = ['KB', 'MB', 'GB', 'TB'];
                        let u = -1;
                        do {
                            bytes /= thresh;
                            ++u;
                        } while (Math.abs(bytes) >= thresh && u < units.length - 1);
                        return bytes.toFixed(1) + ' ' + units[u];
                    },

                    async startUploads() {
                        if (this.isUploading) return;

                        const hasQueued = this.queue.some(i => i.status === 'queued');
                        if (!hasQueued) return;

                        this.isUploading = true;
                        this.statusText = 'Uploading…';
                        this.recalcOverall();

                        for (const item of this.queue) {
                            if (item.status !== 'queued') continue;
                            await this.uploadOne(item);
                            this.recalcOverall();
                        }

                        this.isUploading = false;
                        this.statusText = 'Done';
                        this.recalcOverall();

                        // ✅ If everything is done/hidden, cleanup queue fully
                        this.queue = this.queue.filter(i => i.visible !== false);
                    },

                    recalcOverall() {
                        const total = this.queue.length || 1;
                        const sum = this.queue.reduce((acc, i) => acc + (Number(i.progress) || 0), 0);
                        this.overallPercent = Math.min(100, Math.round(sum / total));
                    },

                    uploadOne(item) {
                        return new Promise((resolve) => {
                            item.status = 'uploading';
                            item.statusLabel = 'Uploading…';
                            item.progress = 0;
                            item.error = null;

                            if (!this.$wire || typeof this.$wire.upload !== 'function') {
                                item.status = 'error';
                                item.statusLabel = 'Failed';
                                item.error = '$wire.upload not ready';
                                item.progress = 0;
                                return resolve();
                            }

                            this.$wire.upload(
                                'singleFile',
                                item.file,

                                async () => {
                                        try {
                                            // ✅ This creates DB record + pushes into uploadedIds
                                            await this.$wire.persistSingleUpload();

                                            item.status = 'done';
                                            item.statusLabel = 'Completed';
                                            item.progress = 100;

                                            // ✅ force Livewire to re-render view so uploadedMedia rows show
                                            this.$wire.$refresh();

                                            // ✅ NEW: auto-hide/remove completed progress row
                                            setTimeout(() => {
                                                item.visible = false;

                                                // remove from queue array
                                                this.queue = this.queue.filter(q => q.id !== item
                                                    .id);

                                                // recalc overall progress + stop upload UI if nothing left
                                                this.recalcOverall();
                                                if (!this.queue.some(q => q.status === 'queued' || q
                                                        .status === 'uploading')) {
                                                    this.isUploading = false;
                                                    this.statusText = 'Done';
                                                }
                                            }, 600);

                                        } catch (e) {
                                            item.status = 'error';
                                            item.statusLabel = 'Failed';
                                            item.error = 'Server error while saving.';
                                            item.progress = 0;
                                        }

                                        if (item.preview) {
                                            try {
                                                URL.revokeObjectURL(item.preview);
                                            } catch (e) {}
                                        }
                                        resolve();
                                    },

                                    () => {
                                        item.status = 'error';
                                        item.statusLabel = 'Failed';
                                        item.error = 'Upload failed.';
                                        item.progress = 0;

                                        if (item.preview) {
                                            try {
                                                URL.revokeObjectURL(item.preview);
                                            } catch (e) {}
                                        }
                                        resolve();
                                    },

                                    (event) => {
                                        const p = event?.detail?.progress ?? 0;
                                        item.progress = Math.max(0, Math.min(100, Math.round(p)));
                                    }
                            );
                        });
                    },
                };
            }
        }
    </script>
@endonce
