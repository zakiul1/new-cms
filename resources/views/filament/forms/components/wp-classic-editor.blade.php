@php
    $statePath = $getStatePath();
    $height = $getHeight();
    $toolbar = implode(' | ', $getToolbar());
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div wire:ignore x-data="wpClassicEditor({
        state: @entangle($statePath),
        height: @js($height),
        toolbar: @js($toolbar),
        tinymceSrc: @js(asset('build/tinymce/tinymce.min.js')),
    })" x-init="init()" class="w-full">
        <div class="border bg-white">
            {{-- Tabs --}}
            <div class="flex items-center justify-between border-b px-3 py-2">
                <div class="flex gap-2">
                    <button type="button" class="px-3 py-1 text-sm"
                        :class="mode === 'visual' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700'"
                        @click="switchToVisual()">
                        Visual
                    </button>

                    <button type="button" class="px-3 py-1 text-sm"
                        :class="mode === 'code' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700'"
                        @click="switchToCode()">
                        Code
                    </button>
                </div>
            </div>

            {{-- Visual --}}
            <div x-show="mode === 'visual'" class="p-2">
                <textarea x-ref="editor"></textarea>
            </div>

            {{-- Code --}}
            <div x-show="mode === 'code'" class="p-2">
                <textarea x-ref="code" class="w-full  border p-3 font-mono text-sm" :style="`height:${height}px;`"
                    @input="onCodeInput()"></textarea>
            </div>
        </div>

        <script>
            function wpClassicEditor({
                state,
                height,
                toolbar,
                tinymceSrc
            }) {
                return {
                    mode: 'visual',
                    height,
                    toolbar,
                    state, // ✅ entangled Livewire state

                    editor: null,
                    editorId: 'tinymce-' + Math.random().toString(36).slice(2),
                    codeDirty: false,

                    init() {
                        if (this.$refs.editor.dataset.initialized === '1') return;
                        this.$refs.editor.dataset.initialized = '1';

                        const ensureTinyLoaded = () => {
                            if (window.tinymce) return Promise.resolve();
                            if (window.__tinymceLoadingPromise) return window.__tinymceLoadingPromise;

                            window.__tinymceLoadingPromise = new Promise((resolve, reject) => {
                                const s = document.createElement('script');
                                s.src = tinymceSrc;
                                s.onload = () => resolve();
                                s.onerror = () => reject(new Error('TinyMCE failed to load'));
                                document.head.appendChild(s);
                            });

                            return window.__tinymceLoadingPromise;
                        };

                        const waitUntilVisible = (el) =>
                            new Promise((resolve) => {
                                const tick = () => {
                                    const r = el.getBoundingClientRect();
                                    if (r.width > 0 && r.height > 0 && getComputedStyle(el).display !== 'none') {
                                        return resolve();
                                    }
                                    requestAnimationFrame(tick);
                                };
                                tick();
                            });

                        const boot = async () => {
                            const el = this.$refs.editor;
                            el.id = this.editorId;

                            await ensureTinyLoaded();
                            await waitUntilVisible(el);

                            if (window.tinymce?.get(this.editorId)) {
                                window.tinymce.get(this.editorId).remove();
                            }

                            window.tinymce.init({
                                selector: '#' + this.editorId,
                                license_key: 'gpl',

                                height: this.height,
                                menubar: false,
                                branding: false,

                                plugins: 'link lists',
                                toolbar: this.toolbar.replace('| code', '').replace('code', ''),

                                block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Pre=pre',

                                content_style: 'body { font-family: ui-sans-serif, system-ui; font-size: 14px; line-height: 1.7; padding: 10px; }',

                                setup: (editor) => {
                                    this.editor = editor;

                                    editor.on('init', () => {
                                        editor.setContent(this.state || '');
                                    });

                                    const sync = () => {
                                        const html = editor.getContent();
                                        this.state = html; // ✅ this is what makes Filament save

                                        if (this.mode === 'code' && !this.codeDirty) {
                                            this.$refs.code.value = html;
                                        }
                                    };

                                    editor.on('change keyup paste input undo redo', sync);
                                },
                            });
                        };

                        boot();

                        // If Livewire changes state (record load), update editor
                        this.$watch('state', (value) => {
                            if (!this.editor) return;

                            const next = value ?? '';
                            const cur = this.editor.getContent();

                            if (cur !== next) this.editor.setContent(next);

                            if (this.mode === 'code' && !this.codeDirty) {
                                this.$refs.code.value = next;
                            }
                        });

                        // Repaint when tabs clicked
                        document.addEventListener('click', (e) => {
                            if (e.target?.closest('[role="tab"], .fi-tabs button, .fi-tabs-tab')) {
                                setTimeout(() => this.refresh(), 80);
                            }
                        });
                    },

                    refresh() {
                        if (!this.editor) return;
                        this.editor.execCommand('mceRepaint');
                        this.editor.theme?.resizeTo?.(null, this.height);
                    },

                    switchToCode() {
                        this.mode = 'code';
                        this.codeDirty = false;

                        const html = this.editor ? this.editor.getContent() : (this.state || '');
                        this.$refs.code.value = html;

                        this.$nextTick(() => this.$refs.code.focus());
                    },

                    switchToVisual() {
                        if (!this.editor) {
                            this.mode = 'visual';
                            return;
                        }

                        if (this.mode === 'code') {
                            const html = this.$refs.code.value || '';
                            this.codeDirty = false;

                            this.editor.setContent(html);
                            this.state = html; // ✅ persist
                        }

                        this.mode = 'visual';
                        setTimeout(() => this.refresh(), 50);
                    },

                    onCodeInput() {
                        this.codeDirty = true;

                        const html = this.$refs.code.value || '';
                        this.state = html; // ✅ persist

                        if (this.editor) {
                            this.editor.setContent(html);
                        }
                    },
                }
            }
        </script>
    </div>
</x-dynamic-component>
