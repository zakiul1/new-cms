{{-- resources/views/filament/media/upload-media.blade.php --}}

<div x-data="{
    dragCounter: 0,
    dragging: false,
    progress: 0,
    uploading: false,

    openPicker() {
        this.$refs.fileInput?.click();
    },

    onDragEnter(e) {
        e.preventDefault();
        this.dragCounter++;
        this.dragging = true;
    },

    onDragLeave(e) {
        e.preventDefault();
        this.dragCounter = Math.max(0, this.dragCounter - 1);
        if (this.dragCounter === 0) this.dragging = false;
    },

    onDragOver(e) {
        e.preventDefault();
    },

    onDrop(e) {
        e.preventDefault();
        this.dragCounter = 0;
        this.dragging = false;

        const files = Array.from(e.dataTransfer?.files ?? []);
        if (!files.length) return;

        // ✅ Upload into Livewire property (works even when dropping anywhere)
        $wire.uploadMultiple(
            'wpUploadFiles',
            files,
            () => {},
            () => {},
            (event) => { this.progress = event.detail.progress; }
        );
    },

    startUpload() {
        this.uploading = true;
        this.progress = 0;
    },
    finishUpload() {
        this.uploading = false;
        this.progress = 0;
    },
}" x-on:dragenter.window="onDragEnter($event)" x-on:dragleave.window="onDragLeave($event)"
    x-on:dragover.window="onDragOver($event)" x-on:drop.window="onDrop($event)" x-on:livewire-upload-start="startUpload()"
    x-on:livewire-upload-finish="finishUpload()" x-on:livewire-upload-error="finishUpload()"
    x-on:livewire-upload-progress="progress = $event.detail.progress" class="mx-auto w-full max-w-[1100px] px-4 py-6">
    {{-- Full page drop overlay --}}
    <div x-show="dragging" x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="rounded-2xl border border-white/20 bg-white/95 px-10 py-8 text-center shadow-xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 16V4" />
                    <path d="M7 9l5-5 5 5" />
                    <path d="M20 20H4" />
                </svg>
            </div>
            <div class="mt-4 text-lg font-semibold text-slate-900">Drop files to upload</div>
            <div class="mt-1 text-sm text-slate-600">Release to add files</div>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Media Library</h1>
            <p class="mt-1 text-sm text-slate-500">
                Upload like WordPress: drag & drop anywhere, then assign folder and categories.
            </p>
        </div>

        <a href="{{ \App\Filament\Resources\MediaResource::getUrl('index') }}"
            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
            Back to Media
        </a>
    </div>

    {{-- Card --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 p-5 md:grid-cols-12">

            {{-- Left: Dropzone --}}
            <div class="md:col-span-8">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    {{-- Hidden input used for click --}}
                    <input x-ref="fileInput" type="file" multiple class="hidden" wire:model="wpUploadFiles" />

                    <button type="button"
                        class="group flex w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-white px-6 py-10 text-center transition hover:border-primary-500 hover:bg-slate-50"
                        x-on:click="openPicker()">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M12 16V4" />
                                <path d="M7 9l5-5 5 5" />
                                <path d="M20 20H4" />
                            </svg>
                        </div>

                        <div class="mt-4 text-base font-semibold text-slate-900">
                            Drop files to upload
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            or click to select files (up to 50 at a time)
                        </div>

                        <div
                            class="mt-4 inline-flex items-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                            Select files
                        </div>

                        <div class="mt-3 text-xs text-slate-500">
                            Images generate variants automatically.
                        </div>
                    </button>

                    {{-- Upload progress --}}
                    <div x-show="uploading" class="mt-4" x-cloak>
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span>Uploading…</span>
                            <span x-text="progress + '%'"></span>
                        </div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div class="h-2 rounded-full bg-primary-600 transition-all" :style="`width:${progress}%`">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Selected files --}}
                @if (!empty($wpUploadFiles))
                    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-slate-900">
                                Selected files ({{ is_countable($wpUploadFiles) ? count($wpUploadFiles) : 0 }})
                            </div>

                            <button type="button" class="text-sm font-semibold text-slate-600 hover:text-slate-900"
                                wire:click="clearUploads" wire:loading.attr="disabled">
                                Clear all
                            </button>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($wpUploadFiles as $i => $file)
                                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3">
                                    <div class="h-12 w-12 overflow-hidden rounded-2xl bg-slate-100">
                                        @if (method_exists($file, 'temporaryUrl'))
                                            <img src="{{ $file->temporaryUrl() }}" class="h-full w-full object-cover"
                                                alt="">
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-slate-900">
                                            {{ $file->getClientOriginalName() }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ number_format(($file->getSize() ?? 0) / 1024, 1) }} KB
                                        </div>
                                    </div>

                                    <button type="button"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        wire:click="removeUploadFile({{ $i }})" wire:loading.attr="disabled">
                                        Remove
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button"
                                class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60"
                                wire:click="uploadWpMedia" wire:loading.attr="disabled">
                                Upload
                            </button>

                            <div class="text-xs text-slate-500" wire:loading>
                                Processing upload…
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: Organize --}}
            <div class="md:col-span-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-sm font-semibold text-slate-900">Organize</div>
                    <div class="mt-1 text-xs text-slate-500">Assign folder/categories for this upload batch.</div>

                    {{-- Folder --}}
                    <div class="mt-4">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-slate-700">Folder</label>
                            <button type="button" class="text-xs font-semibold text-primary-700 hover:underline"
                                wire:click="mountAction('newFolder')">
                                + New
                            </button>
                        </div>

                        <input type="text"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            placeholder="Search folder…" wire:model.live.debounce.250ms="folderSearch" />

                        <select
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:ring-primary-500"
                            wire:model.defer="wpFolderId">
                            <option value="">No folder</option>
                            @foreach ($this->folderOptions ?? [] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Categories --}}
                    <div class="mt-5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-slate-700">Categories</label>
                            <button type="button" class="text-xs font-semibold text-primary-700 hover:underline"
                                wire:click="mountAction('newCategory')">
                                + New
                            </button>
                        </div>

                        <input type="text"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            placeholder="Search categories…" wire:model.live.debounce.250ms="categorySearch" />

                        <div
                            class="mt-2 max-h-44 overflow-auto rounded-xl border border-slate-200 bg-white p-2 scroll-smooth">
                            @forelse(($this->categoryOptions ?? []) as $id => $name)
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                    <input type="checkbox"
                                        class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                        value="{{ $id }}" wire:model.defer="wpCategoryIds" />
                                    <span class="text-sm text-slate-800">{{ $name }}</span>
                                </label>
                            @empty
                                <div class="p-2 text-sm text-slate-500">
                                    No categories yet. Click “+ New”.
                                </div>
                            @endforelse
                        </div>

                        <div class="mt-2 text-xs text-slate-500">
                            If none selected → uploaded items become <b>Uncategorized</b>.
                        </div>
                    </div>

                    <div class="mt-5 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                        <div class="font-semibold text-slate-800">Tip</div>
                        <div class="mt-1">
                            Select a folder + categories first, then upload—everything will auto-assign.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ✅ REQUIRED: renders Filament action modals (this fixes “New” buttons not opening) --}}
    <x-filament-actions::modals />
</div>
