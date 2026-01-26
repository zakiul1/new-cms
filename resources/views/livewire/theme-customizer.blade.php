<div class="min-h-screen">
    {{-- Top bar --}}
    <div class="sticky top-0 z-50 border-b bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin/themes') }}" class="rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
                    ← Back to Admin
                </a>

                <div class="text-sm font-semibold">
                    Theme Customizer — <span class="font-mono">{{ $theme }}</span>
                </div>
            </div>

            {{-- Controls --}}
            <div class="flex items-center gap-2">
                {{-- Preview type --}}
                <select wire:model="preview" class="rounded-lg border px-3 py-2 text-sm">
                    <option value="home">Home</option>
                    <option value="post">Post</option>
                    <option value="page">Page</option>
                </select>

                {{-- Preview item (only for post/page) --}}
                @if ($preview === 'post')
                    <select wire:model="previewId" class="rounded-lg border px-3 py-2 text-sm max-w-[260px]">
                        @forelse ($postOptions as $p)
                            <option value="{{ $p['id'] }}">
                                {{ \Illuminate\Support\Str::limit($p['title'] ?: $p['slug'], 40) }}
                            </option>
                        @empty
                            <option value="">No published posts</option>
                        @endforelse
                    </select>
                @elseif ($preview === 'page')
                    <select wire:model="previewId" class="rounded-lg border px-3 py-2 text-sm max-w-[260px]">
                        @forelse ($pageOptions as $p)
                            <option value="{{ $p['id'] }}">
                                {{ \Illuminate\Support\Str::limit($p['title'] ?: $p['slug'], 40) }}
                            </option>
                        @empty
                            <option value="">No published pages</option>
                        @endforelse
                    </select>
                @endif

                {{-- Device --}}
                <select wire:model="device" class="rounded-lg border px-3 py-2 text-sm">
                    <option value="desktop">Desktop</option>
                    <option value="tablet">Tablet</option>
                    <option value="mobile">Mobile</option>
                </select>

                <button wire:click="resetDraft" class="rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
                    Reset Draft
                </button>

                <button wire:click="publish"
                    class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                    Publish
                </button>
            </div>
        </div>
    </div>

    @if (session('customizer_notice'))
        <div class="mx-auto max-w-7xl px-4 py-3">
            <div class="rounded-lg border bg-white px-3 py-2 text-sm">
                {{ session('customizer_notice') }}
            </div>
        </div>
    @endif

    {{-- Main --}}
    <div class="mx-auto grid max-w-7xl grid-cols-12 gap-4 px-4 py-4">
        {{-- Left sidebar --}}
        <aside class="col-span-12 lg:col-span-4">
            <div class="sticky top-[72px] rounded-xl border bg-white">
                <div class="border-b px-4 py-3">
                    <div class="text-sm font-semibold">Customizer</div>
                    <div class="text-xs text-gray-500">Changes preview instantly</div>
                </div>

                <div class="space-y-4 p-4">
                    {{-- ✅ HEADER (Premium) --}}
                    <div class="rounded-lg border p-3">
                        <div class="mb-2 text-sm font-semibold">Header</div>

                        @php
                            $logo = $data['logo_path'] ?? null;
                            $logoUrl = $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : null;
                        @endphp

                        <div class="space-y-3">
                            {{-- Logo preview + remove --}}
                            @if ($logoUrl)
                                <div class="flex items-center gap-3">
                                    <img src="{{ $logoUrl }}" alt="Logo"
                                        class="h-10 w-auto rounded bg-gray-50 p-1 border">
                                    <button type="button" wire:click="removeLogo"
                                        class="rounded-lg border px-3 py-2 text-xs hover:bg-gray-50">
                                        Remove
                                    </button>
                                </div>
                            @endif

                            {{-- Logo upload --}}
                            <div>
                                <div class="text-xs font-semibold mb-1">Upload Logo</div>
                                <input type="file" wire:model="logoUpload" accept="image/*"
                                    class="block w-full text-sm">
                                @error('logoUpload')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Logo width --}}
                            <label class="block text-xs">
                                Logo width (px)
                                <input type="range" min="80" max="240" step="2"
                                    wire:model="data.logo_width" class="mt-2 w-full">
                                <div class="mt-1 text-xs text-gray-600">
                                    {{ (int) ($data['logo_width'] ?? 140) }}px
                                </div>
                            </label>

                            {{-- Header layout --}}
                            <label class="block text-xs">
                                Header layout
                                <select wire:model="data.header_layout"
                                    class="mt-1 w-full rounded border px-2 py-2 text-sm">
                                    <option value="left">Left</option>
                                    <option value="center">Centered</option>
                                    <option value="split">Split</option>
                                </select>
                            </label>

                            {{-- Sticky --}}
                            <div class="flex items-center justify-between text-sm">
                                <span>Sticky header</span>
                                <input type="checkbox" wire:model="data.header_sticky">
                            </div>

                            {{-- Header colors --}}
                            <div class="grid grid-cols-2 gap-3">
                                <label class="text-xs">
                                    Header BG
                                    <input type="color" wire:model="data.header_bg" class="mt-1 h-9 w-full">
                                </label>
                                <label class="text-xs">
                                    Header Text
                                    <input type="color" wire:model="data.header_text" class="mt-1 h-9 w-full">
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Colors --}}
                    <div class="rounded-lg border p-3">
                        <div class="mb-2 text-sm font-semibold">Colors</div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs">
                                Primary
                                <input type="color" wire:model="data.primary" class="mt-1 h-9 w-full">
                            </label>
                            <label class="text-xs">
                                Accent
                                <input type="color" wire:model="data.accent" class="mt-1 h-9 w-full">
                            </label>
                            <label class="text-xs">
                                Background
                                <input type="color" wire:model="data.background" class="mt-1 h-9 w-full">
                            </label>
                            <label class="text-xs">
                                Text
                                <input type="color" wire:model="data.text" class="mt-1 h-9 w-full">
                            </label>
                        </div>
                    </div>

                    {{-- Typography --}}
                    <div class="rounded-lg border p-3">
                        <div class="mb-2 text-sm font-semibold">Typography</div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs">
                                Font
                                <select wire:model="data.font_family"
                                    class="mt-1 w-full rounded border px-2 py-2 text-sm">
                                    <option value="system">System</option>
                                    <option value="inter">Inter</option>
                                    <option value="poppins">Poppins</option>
                                    <option value="roboto">Roboto</option>
                                </select>
                            </label>

                            <label class="text-xs">
                                Base Size
                                <input type="number" min="12" max="22" wire:model="data.base_font_size"
                                    class="mt-1 w-full rounded border px-2 py-2 text-sm">
                            </label>
                        </div>
                    </div>

                    {{-- Layout --}}
                    <div class="rounded-lg border p-3">
                        <div class="mb-2 text-sm font-semibold">Layout</div>

                        <label class="text-xs">
                            Container
                            <select wire:model="data.container_width"
                                class="mt-1 w-full rounded border px-2 py-2 text-sm">
                                <option value="default">Default</option>
                                <option value="narrow">Narrow</option>
                                <option value="wide">Wide</option>
                            </select>
                        </label>

                        <div class="mt-3 flex items-center justify-between text-sm">
                            <span>Rounded</span>
                            <input type="checkbox" wire:model="data.rounded">
                        </div>

                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span>Shadows</span>
                            <input type="checkbox" wire:model="data.shadows">
                        </div>
                    </div>

                    {{-- Advanced --}}
                    <div class="rounded-lg border p-3">
                        <div class="mb-2 text-sm font-semibold">Advanced</div>
                        <textarea wire:model="data.custom_css" class="h-28 w-full rounded border p-2 text-sm" placeholder="Custom CSS..."></textarea>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Right preview --}}
        <main class="col-span-12 lg:col-span-8">
            <div class="rounded-xl border bg-white">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <div class="text-sm font-semibold">Live Preview</div>
                    <button type="button" wire:click="refreshPreview"
                        class="rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
                        Reload
                    </button>
                </div>

                <div class="p-4">
                    <div class="mx-auto w-full {{ $this->iframeWidthClass }}">
                        <div class="aspect-[16/10] overflow-hidden rounded-lg border bg-gray-50">
                            <iframe id="customizerPreview" class="h-full w-full"
                                src="{{ $this->previewUrl }}"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

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
