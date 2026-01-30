{{-- resources/views/livewire/theme-customizer.blade.php --}}

<div class="min-h-screen bg-gray-50">
    {{-- Top bar --}}
    <div class="sticky top-0 z-40 border-b bg-white/90 backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="{{ url('/lara-admin/themes') }}"
                    class="rounded-xl border bg-white px-4 py-2 text-sm hover:bg-gray-50">
                    ← Back to Admin
                </a>

                <div
                    class="h-9 w-9 rounded-xl bg-gray-900 text-white flex items-center justify-center text-sm font-semibold">
                    TC
                </div>
                <div>

                    <div class="text-sm font-semibold leading-4">Theme Customizer</div>
                    <div class="text-xs text-gray-500">Theme: <span
                            class="font-medium text-gray-700">{{ $theme }}</span></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Device --}}
                <div class="flex items-center gap-2 rounded-xl border bg-white px-2 py-1">
                    <div class="text-xs text-gray-500 px-1">Device</div>
                    <select wire:model.live="device" class="rounded-lg border-0 bg-transparent text-sm focus:ring-0">
                        <option value="desktop">Desktop</option>
                        <option value="tablet">Tablet</option>
                        <option value="mobile">Mobile</option>
                    </select>
                </div>

                {{-- Preview --}}
                <div class="flex items-center gap-2 rounded-xl border bg-white px-2 py-1">
                    <div class="text-xs text-gray-500 px-1">Preview</div>
                    <select wire:model.live="preview" class="rounded-lg border-0 bg-transparent text-sm focus:ring-0">
                        <option value="home">Home</option>
                        <option value="post">Post</option>
                        <option value="page">Page</option>
                    </select>

                    @if ($preview === 'post')
                        <select wire:model.live="previewId" class="rounded-lg border bg-white text-sm">
                            @foreach ($postOptions as $p)
                                <option value="{{ $p['id'] }}">{{ $p['title'] }}</option>
                            @endforeach
                        </select>
                    @endif

                    @if ($preview === 'page')
                        <select wire:model.live="previewId" class="rounded-lg border bg-white text-sm">
                            @foreach ($pageOptions as $p)
                                <option value="{{ $p['id'] }}">{{ $p['title'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Actions --}}
                <button type="button" wire:click="publish"
                    class="rounded-xl bg-gray-900 px-4 py-2 text-sm text-white hover:bg-black">
                    Publish
                </button>

                <button type="button" wire:click="resetDraft"
                    class="rounded-xl border bg-white px-4 py-2 text-sm hover:bg-gray-50">
                    Reset Draft
                </button>
            </div>
        </div>

        @if (session('customizer_notice'))
            <div class="border-t bg-green-50 px-4 py-2 text-sm text-green-800">
                {{ session('customizer_notice') }}
            </div>
        @endif
    </div>

    {{-- Main --}}
    <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr]">
        {{-- Left panel --}}
        <aside class="border-r bg-white">
            <div class="p-4 space-y-4">

                {{-- Brand / Media --}}
                <div class="rounded-2xl border bg-white p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-semibold">Brand</div>
                            <div class="text-xs text-gray-500">Logo, favicon & basics</div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        {{-- Logo --}}
                        <div class="flex items-start gap-3">
                            <div class="w-16 text-xs text-gray-500 pt-2">Logo</div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openMediaPicker('logo_media_id','image')"
                                        class="rounded-xl border px-3 py-2 text-xs hover:bg-gray-50">
                                        Choose
                                    </button>

                                    <button type="button" wire:click="clearMedia('logo_media_id')"
                                        class="rounded-xl border px-3 py-2 text-xs hover:bg-gray-50">
                                        Clear
                                    </button>
                                </div>

                                @if ($logoUrl)
                                    <div class="mt-3 rounded-xl border bg-gray-50 p-3">
                                        <img src="{{ $logoUrl }}" alt="Logo preview" class="h-10 object-contain">
                                        <div class="mt-2 text-[11px] text-gray-500 truncate">{{ $logoUrl }}</div>
                                    </div>
                                @else
                                    <div class="mt-3 text-xs text-gray-400">No logo selected.</div>
                                @endif
                            </div>
                        </div>

                        {{-- Favicon --}}
                        <div class="flex items-start gap-3">
                            <div class="w-16 text-xs text-gray-500 pt-2">Favicon</div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openMediaPicker('favicon_media_id','image')"
                                        class="rounded-xl border px-3 py-2 text-xs hover:bg-gray-50">
                                        Choose
                                    </button>

                                    <button type="button" wire:click="clearMedia('favicon_media_id')"
                                        class="rounded-xl border px-3 py-2 text-xs hover:bg-gray-50">
                                        Clear
                                    </button>
                                </div>

                                @if ($faviconUrl)
                                    <div class="mt-3 inline-flex items-center gap-3 rounded-xl border bg-gray-50 p-3">
                                        <img src="{{ $faviconUrl }}" alt="Favicon preview"
                                            class="h-10 w-10 rounded-lg object-cover border">
                                        <div>
                                            <div class="text-xs font-medium">Selected</div>
                                            <div class="text-[11px] text-gray-500 truncate max-w-[220px]">
                                                {{ $faviconUrl }}</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-3 text-xs text-gray-400">No favicon selected.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Colors --}}
                <div class="rounded-2xl border bg-white p-4">
                    <div>
                        <div class="text-sm font-semibold">Colors</div>
                        <div class="text-xs text-gray-500">Primary & UI colors</div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <label class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Primary</span>
                            <input type="color" wire:model.live="data.primary"
                                class="h-9 w-14 rounded-lg border bg-white" />
                        </label>

                        <label class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Accent</span>
                            <input type="color" wire:model.live="data.accent"
                                class="h-9 w-14 rounded-lg border bg-white" />
                        </label>

                        <label class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Background</span>
                            <input type="color" wire:model.live="data.background"
                                class="h-9 w-14 rounded-lg border bg-white" />
                        </label>

                        <label class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Text</span>
                            <input type="color" wire:model.live="data.text"
                                class="h-9 w-14 rounded-lg border bg-white" />
                        </label>
                    </div>
                </div>

                {{-- Layout --}}
                <div class="rounded-2xl border bg-white p-4">
                    <div>
                        <div class="text-sm font-semibold">Layout</div>
                        <div class="text-xs text-gray-500">Header & style</div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Header layout</div>
                                <select wire:model.live="data.header_layout"
                                    class="w-full rounded-xl border px-3 py-2 text-sm">
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="split">Split</option>
                                </select>
                            </div>

                            <div>
                                <div class="text-xs text-gray-600 mb-1">Container</div>
                                <select wire:model.live="data.container_width"
                                    class="w-full rounded-xl border px-3 py-2 text-sm">
                                    <option value="default">Default</option>
                                    <option value="wide">Wide</option>
                                    <option value="full">Full</option>
                                </select>
                            </div>
                        </div>

                        <label class="flex items-center justify-between rounded-xl border px-3 py-2">
                            <span class="text-sm">Sticky header</span>
                            <input type="checkbox" wire:model.live="data.header_sticky"
                                class="h-5 w-5 rounded border-gray-300" />
                        </label>

                        <label class="flex items-center justify-between rounded-xl border px-3 py-2">
                            <span class="text-sm">Rounded corners</span>
                            <input type="checkbox" wire:model.live="data.rounded"
                                class="h-5 w-5 rounded border-gray-300" />
                        </label>

                        <label class="flex items-center justify-between rounded-xl border px-3 py-2">
                            <span class="text-sm">Shadows</span>
                            <input type="checkbox" wire:model.live="data.shadows"
                                class="h-5 w-5 rounded border-gray-300" />
                        </label>
                    </div>
                </div>

                {{-- Custom CSS --}}
                <div class="rounded-2xl border bg-white p-4">
                    <div>
                        <div class="text-sm font-semibold">Custom CSS</div>
                        <div class="text-xs text-gray-500">Advanced tweaks</div>
                    </div>

                    <textarea wire:model.live.debounce.300ms="data.custom_css"
                        class="mt-3 w-full rounded-xl border px-3 py-2 text-sm font-mono" rows="6"
                        placeholder="/* Your CSS here */"></textarea>

                    <div class="mt-2 text-xs text-gray-500">
                        Tip: keep it minimal. This is applied on preview pages.
                    </div>
                </div>
            </div>
        </aside>

        {{-- Right preview --}}
        <main class="p-4">
            <div class="mx-auto {{ $this->iframeWidthClass }} overflow-hidden rounded-2xl border bg-white shadow-sm">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">Live Preview</div>
                        <div class="text-xs text-gray-500 truncate">
                            {{ $this->previewUrl }}
                        </div>
                    </div>

                    <button type="button" wire:click="refreshPreview"
                        class="rounded-xl border bg-white px-3 py-2 text-sm hover:bg-gray-50">
                        Refresh
                    </button>
                </div>

                <iframe id="customizerPreview" src="{{ $this->previewUrl }}" class="w-full"
                    style="height: calc(100vh - 170px);"></iframe>
            </div>
        </main>
    </div>

    {{-- ✅ Media Picker Modal (your custom browser) --}}
    @if ($mediaPickerOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-6xl overflow-hidden rounded-2xl border bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <div class="text-sm font-semibold">Select Media</div>
                        <div class="text-xs text-gray-500">
                            Target: <span class="font-medium text-gray-700">{{ $mediaTargetKey }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="closeMediaPicker"
                            class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">
                            Close
                        </button>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model.live.debounce.300ms="mediaSearch"
                            placeholder="Search media..." class="w-full rounded-xl border px-3 py-2 text-sm">

                        <button type="button" wire:click="$set('mediaSearch','')"
                            class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">
                            Clear
                        </button>
                    </div>

                    @php
                        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|null $picker */
                        $picker = $mediaPicker ?? null;

                        $selectedId = (int) ($data[$mediaTargetKey] ?? 0);
                    @endphp

                    @if (!$picker)
                        <div class="text-sm text-gray-500">Loading…</div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            @forelse ($picker as $m)
                                @php
                                    $title = $m->title ?: ($m->original_filename ?: 'Media #' . $m->id);
                                    $thumb = $m->thumbUrl() ?: $m->url();
                                    $isSelected = (int) $m->id === $selectedId;
                                @endphp

                                <button type="button" wire:click="selectMedia({{ (int) $m->id }})"
                                    class="group overflow-hidden rounded-xl border text-left hover:bg-gray-50 focus:outline-none {{ $isSelected ? 'ring-2 ring-gray-900' : '' }}">
                                    <div class="relative aspect-square bg-gray-100">
                                        <img src="{{ $thumb }}" alt="{{ $title }}"
                                            class="h-full w-full object-cover">

                                        @if ($isSelected)
                                            <div
                                                class="absolute top-2 right-2 rounded-full bg-gray-900 px-2 py-1 text-[10px] text-white">
                                                Selected
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-2">
                                        <div class="text-xs font-medium truncate">{{ $title }}</div>
                                        <div class="text-[10px] text-gray-500 truncate">{{ (string) $m->mime_type }}
                                        </div>
                                    </div>
                                </button>
                            @empty
                                <div class="col-span-full text-sm text-gray-500">
                                    No media found.
                                </div>
                            @endforelse
                        </div>

                        <div class="pt-3">
                            {{ $picker->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Iframe refresh hook --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('customizer-refresh', () => {
                const iframe = document.getElementById('customizerPreview');
                if (!iframe) return;

                const url = new URL(iframe.src);
                url.searchParams.set('_t', Date.now());
                iframe.src = url.toString();
            });
        });
    </script>
</div>
