@php
    /** @var \App\Filament\Forms\Components\MediaPicker $field */

    $statePath = $field->getStatePath();

    // IMPORTANT: we must pass plain strings inside Alpine attrs (avoid @js in x-data)
    $statePathJs = str_replace("'", "\\'", $statePath);

    // unique modal id per field instance
    $modalId = 'media-picker-' . md5($field->getId() . '|' . $statePath);
    $modalIdJs = str_replace("'", "\\'", $modalId);

    $multiple = $field->isMultiple();
    $maxItems = $field->getMaxItems();
    $selected = $field->getSelectedMedia();
@endphp

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    <div x-data="{
        modalId: '{{ $modalIdJs }}',
        statePath: '{{ $statePathJs }}',
        multiple: {{ $multiple ? 'true' : 'false' }},
        maxItems: {{ is_null($maxItems) ? 'null' : (int) $maxItems }},
        state: $wire.entangle('{{ $statePathJs }}').live,

        open() {
            const store = Alpine.store('wpMediaModal');
            if (store && store.open) store.open(this.modalId);
        },

        close() {
            const store = Alpine.store('wpMediaModal');
            if (store && store.close) store.close();
        },

        handleApply(e) {
            if (!e || !e.detail) return;
            if (e.detail.statePath !== this.statePath) return;

            const ids = Array.isArray(e.detail.ids) ? e.detail.ids : [];
            const clean = ids.map((x) => Number(x)).filter((n) => Number.isFinite(n) && n > 0);

            if (this.multiple) {
                this.state = clean;
            } else {
                this.state = clean.length ? clean[0] : null;
            }

            this.close();
        },
    }" x-on:media-library-apply.window="handleApply($event)" class="space-y-3">

        {{-- Preview strip --}}
        <div class="flex flex-wrap gap-2">
            @if (count($selected))
                @foreach ($selected as $m)
                    <div class="relative">
                        <img src="{{ $m['thumb'] }}" class="w-20 h-20 object-cover rounded-md border" alt="">
                    </div>
                @endforeach
            @else
                <div class="text-sm text-gray-500">
                    No {{ $multiple ? 'images' : 'image' }} selected.
                </div>
            @endif
        </div>

        {{-- Open modal --}}
        <x-filament::button type="button" x-on:click="open()">
            {{ $multiple ? 'Select images' : 'Select image' }}
        </x-filament::button>

        {{-- WP-style overlay modal (windowed, with viewport padding like WP) --}}
        <div
            x-show="$store.wpMediaModal && $store.wpMediaModal.isOpen('{{ $modalIdJs }}')"
            x-transition.opacity
            class="fixed inset-0 z-[99999] bg-black/40"
            style="display: none;"
            @keydown.escape.window="close()"
            @click.self="close()"
        >
            {{-- Padding around the modal window (WP-like) --}}
            <div class="absolute inset-0 p-6 sm:p-8">
                {{-- Modal window --}}
                <div class="h-full w-full bg-white shadow-xl border border-gray-200 rounded-md overflow-hidden flex flex-col">
                    {{-- Header --}}
                    <div class="h-14 border-b flex items-center justify-between px-4 shrink-0">
                        <div class="font-semibold">
                            {{ $field->getModalHeading() }}
                        </div>

                        <button type="button" class="text-sm px-3 py-1 rounded hover:bg-gray-100" @click="close()">
                            ✕
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="flex-1 overflow-hidden">
                        <livewire:media-library-browser
                            :state-path="$statePath"
                            :multiple="$multiple"
                            :max-items="$maxItems"
                            :selected="(array) ($field->getState() ?? [])"
                            :wire:key="$modalId . '-browser'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>

@once
    <script>
        // 1) Modal store
        document.addEventListener('alpine:init', () => {
            // If already registered, do nothing
            try {
                if (Alpine.store('wpMediaModal')) return;
            } catch (e) {}

            Alpine.store('wpMediaModal', {
                openId: null,

                open(id) {
                    this.openId = id;
                    document.documentElement.classList.add('overflow-hidden');
                },

                close() {
                    this.openId = null;
                    document.documentElement.classList.remove('overflow-hidden');
                },

                isOpen(id) {
                    return this.openId === id;
                },
            });
        });

        // 2) Uploader function (FORCE define as function)
        (() => {
            // ✅ Only skip if it's already a FUNCTION
            if (typeof window.wpMediaUploader === 'function') return;

            window.wpMediaUploader = (component) => ({
                isDropping: false,
                uploading: false,
                progress: 0,

                queue: [],
                workerRunning: false,
                _listenersBound: false,

                init() {
                    if (this._listenersBound) return;
                    this._listenersBound = true;

                    const opts = { capture: true, passive: false };
                    const prevent = (e) => { e.preventDefault(); e.stopPropagation(); };

                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((name) => {
                        window.addEventListener(name, prevent, opts);
                        document.addEventListener(name, prevent, opts);
                    });

                    window.addEventListener('dragenter', (e) => { prevent(e); this.isDropping = true; }, opts);
                    window.addEventListener('dragover',  (e) => { prevent(e); this.isDropping = true; }, opts);

                    window.addEventListener('dragleave', (e) => {
                        prevent(e);
                        if (
                            e.clientX <= 0 || e.clientY <= 0 ||
                            e.clientX >= window.innerWidth || e.clientY >= window.innerHeight
                        ) {
                            this.isDropping = false;
                        }
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
                                component.uploadMultiple(
                                    'files',
                                    batch,
                                    () => {
                                        this.uploading = false;
                                        this.progress = 100;

                                        component.call('filesUploaded');

                                        setTimeout(() => { this.progress = 0; }, 300);
                                        resolve();
                                    },
                                    (err) => {
                                        console.error(err);
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
            });
        })();
    </script>
@endonce
