@php
    /** @var \App\Filament\Forms\Components\MediaPicker $field */

    $statePath = $field->getStatePath();

    $statePathJs = str_replace("'", "\\'", $statePath);

    $modalId = 'media-picker-' . md5($field->getId() . '|' . $statePath);
    $modalIdJs = str_replace("'", "\\'", $modalId);

    $multiple = $field->isMultiple();
    $maxItems = $field->getMaxItems();
    $selected = $field->getSelectedMedia();
    $type = method_exists($field, 'getType') ? $field->getType() : 'image';
@endphp

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            modalId: '{{ $modalIdJs }}',
            statePath: '{{ $statePathJs }}',
            multiple: {{ $multiple ? 'true' : 'false' }},
            maxItems: {{ is_null($maxItems) ? 'null' : (int) $maxItems }},
            type: '{{ $type }}',
            state: $wire.entangle('{{ $statePathJs }}').live,

            open() {
                const store = Alpine.store('wpMediaModal');
                if (store && store.open) {
                    store.open(this.modalId);
                }

                window.dispatchEvent(new CustomEvent('cms-media-browser-open', {
                    detail: {
                        targetKey: this.statePath,
                        statePath: this.statePath,
                        type: this.type,
                        source: 'filament-media-picker',
                        multiple: this.multiple,
                        maxItems: this.maxItems,
                        selected: this.currentSelectedIds(),
                    }
                }));
            },

            close() {
                const store = Alpine.store('wpMediaModal');
                if (store && store.close) {
                    store.close();
                }
            },

            currentSelectedIds() {
                if (this.multiple) {
                    return Array.isArray(this.state)
                        ? this.state.map((x) => Number(x)).filter((n) => Number.isFinite(n) && n > 0)
                        : [];
                }

                const id = Number(this.state);
                return Number.isFinite(id) && id > 0 ? [id] : [];
            },

            handleApply(e) {
                if (!e || !e.detail) return;

                const key = e.detail.targetKey ?? e.detail.statePath ?? null;
                if (key !== this.statePath) return;

                let ids = [];

                if (Array.isArray(e.detail.ids)) {
                    ids = e.detail.ids;
                } else if (e.detail.mediaId) {
                    ids = [e.detail.mediaId];
                }

                const clean = ids
                    .map((x) => Number(x))
                    .filter((n) => Number.isFinite(n) && n > 0);

                if (this.multiple) {
                    this.state = this.maxItems === null ? clean : clean.slice(0, this.maxItems);
                } else {
                    this.state = clean.length ? clean[0] : null;
                }

                this.close();
            },
        }"
        x-on:media-library-apply.window="handleApply($event)"
        x-on:cms-media-selected.window="handleApply($event)"
        class="space-y-3"
    >
        {{-- Preview strip --}}
        <div class="flex flex-wrap gap-2">
            @if (count($selected))
                @foreach ($selected as $m)
                    <div class="group relative">
                        <img src="{{ $m['thumb'] }}" class="h-20 w-20 rounded-md border object-cover" alt="{{ $m['title'] }}">

                        <button
                            type="button"
                            class="absolute -right-2 -top-2 hidden h-6 w-6 items-center justify-center rounded-full border bg-white text-xs shadow group-hover:inline-flex"
                            x-on:click.prevent="
                                if (multiple) {
                                    state = (Array.isArray(state) ? state : []).filter((id) => Number(id) !== {{ (int) $m['id'] }});
                                } else {
                                    state = null;
                                }
                            "
                            title="Remove"
                        >
                            ✕
                        </button>
                    </div>
                @endforeach
            @else
                <div class="text-sm text-gray-500">
                    No {{ $multiple ? 'images' : 'image' }} selected.
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-2">
            <x-filament::button type="button" x-on:click="open()">
                {{ $multiple ? 'Select images' : 'Select image' }}
            </x-filament::button>

            @if (count($selected))
                <x-filament::button
                    type="button"
                    color="gray"
                    x-on:click="
                        if (multiple) {
                            state = [];
                        } else {
                            state = null;
                        }
                    "
                >
                    Remove {{ $multiple ? 'all' : 'image' }}
                </x-filament::button>
            @endif
        </div>

        {{-- Modal --}}
        <div
            x-show="$store.wpMediaModal && $store.wpMediaModal.isOpen('{{ $modalIdJs }}')"
            x-transition.opacity
            class="fixed inset-0 z-[99999] bg-black/40"
            style="display: none;"
            @keydown.escape.window="close()"
            @click.self="close()"
        >
            <div class="absolute inset-0 p-6 sm:p-8">
                <div class="flex h-full w-full flex-col overflow-hidden rounded-md border border-gray-200 bg-white shadow-xl">
                    {{-- Header --}}
                    <div class="flex h-14 shrink-0 items-center justify-between border-b px-4">
                        <div class="font-semibold">
                            {{ $field->getModalHeading() }}
                        </div>

                        <button
                            type="button"
                            class="rounded px-3 py-1 text-sm hover:bg-gray-100"
                            @click="close()"
                        >
                            ✕
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="min-h-0 flex-1 overflow-hidden">
                        @livewire(
                            'media-library-browser',
                            [
                                'statePath' => $statePath,
                                'multiple' => $multiple,
                                'maxItems' => $maxItems,
                                'selected' => $multiple
                                    ? (array) ($field->getState() ?? [])
                                    : array_filter([(int) ($field->getState() ?? 0)]),
                            ],
                            key($modalId . '-browser')
                        )
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            try {
                if (Alpine.store('wpMediaModal')) return;
            } catch (e) {}

            Alpine.store('wpMediaModal', {
                openId: null,

                open(id) {
                    this.openId = id;
                    document.documentElement.classList.add('overflow-hidden');
                    document.body.classList.add('overflow-hidden');
                },

                close() {
                    this.openId = null;
                    document.documentElement.classList.remove('overflow-hidden');
                    document.body.classList.remove('overflow-hidden');
                },

                isOpen(id) {
                    return this.openId === id;
                },
            });
        });

        (() => {
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
                        ) {
                            this.isDropping = false;
                        }
                    }, opts);

                    window.addEventListener('drop', (e) => {
                        prevent(e);
                        this.isDropping = false;

                        const files = e.dataTransfer?.files ?? null;
                        if (files && files.length) {
                            this.startUpload(files);
                        }
                    }, opts);
                },

                onDrop(e) {
                    this.isDropping = false;

                    const files = e.dataTransfer?.files ?? null;
                    if (files && files.length) {
                        this.startUpload(files);
                    }
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

                                        setTimeout(() => {
                                            this.progress = 0;
                                        }, 300);

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

                            await new Promise((r) => setTimeout(r, 150));
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