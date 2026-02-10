<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    {{-- Left list --}}
    <div class="lg:col-span-4">
        <div class="rounded-2xl border bg-white p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold text-base">Siatex Sliders</div>
                <button wire:click="createNew" class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">
                    + New
                </button>
            </div>

            <div class="mt-4 space-y-2">
                @foreach ($sliders as $s)
                    @php
                        $sSettings = (array) ($s->settings_json ?? []);

                        $rendererVariant = (string) ($sSettings['variant'] ?? '');
                        if ($rendererVariant === 'siatex-cinematic') {
                            $rendererVariant = 'siatex-default'; // old data fallback
                        }

                        $sv = (string) ($sSettings['siatex_variant'] ?? 'default');
                        if ($sv === 'cinematic') {
                            $sv = 'default';
                        }

                        $label = match ($rendererVariant) {
                            'siatex-cinematic-pro' => 'Siatex: Cinematic Pro',
                            'siatex-default' => 'Siatex: Default',
                            default => 'Siatex: ' . ucfirst($sv),
                        };
                    @endphp

                    <div
                        class="rounded-xl border px-3 py-3
                            {{ $editingId === $s->id ? 'ring-2 ring-gray-200' : '' }}
                            {{ $s->is_active ? '' : 'opacity-70' }}">
                        <div class="flex items-start justify-between gap-3">
                            <button wire:click="edit({{ $s->id }})" class="text-left flex-1 hover:opacity-90">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium text-sm">{{ $s->name ?: 'Untitled' }}</div>

                                    {{-- ✅ Active badge --}}
                                    @if ($s->is_active)
                                        <span
                                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] leading-4">
                                            Active
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] leading-4 text-gray-500">
                                            Inactive
                                        </span>
                                    @endif
                                </div>

                                <div class="text-xs text-gray-500 mt-0.5">
                                    Key: <span class="font-mono">{{ $s->key }}</span> · {{ $label }}
                                </div>
                            </button>

                            {{-- Delete slider --}}
                            <button type="button" x-data
                                @click="if(confirm('Delete this slider?')) { $wire.deleteSlider({{ $s->id }}) }"
                                class="rounded-lg border px-2.5 py-1.5 text-xs hover:bg-gray-50">
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach

                @if ($sliders->isEmpty())
                    <div class="text-sm text-gray-500 py-6 text-center">
                        No Siatex sliders yet.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Builder --}}
    <div class="lg:col-span-8">
        <div class="rounded-2xl border bg-white p-5">
            {{-- ✅ Empty state: do not show form until New/Edit --}}
            @if (!$showForm)
                <div class="py-16 text-center">
                    <div class="text-base font-semibold">No slider selected</div>
                    <div class="mt-2 text-sm text-gray-500">
                        Select a slider from the left, or click <b>+ New</b> to create one.
                    </div>

                    <button wire:click="createNew"
                        class="mt-6 inline-flex items-center justify-center rounded-xl bg-black text-white px-4 py-2 text-sm hover:opacity-90">
                        + Create Slider
                    </button>
                </div>
            @else
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-semibold text-base">
                            {{ $editingId ? 'Edit Slider' : 'Create Slider' }}
                        </div>

                        <div class="text-sm text-gray-500 mt-1">
                            Tip: Choose a <b>Media Category</b> to auto-add slides. Title & Subtitle are global.
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="preview" class="rounded border-gray-300" />
                            Preview
                        </label>

                        <button wire:click="save"
                            class="rounded-xl bg-black text-white px-4 py-2 text-sm hover:opacity-90">
                            Save
                        </button>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm font-medium">Name</div>
                        <input wire:model.live="name" class="mt-1 w-full rounded-xl border px-3 py-2"
                            placeholder="Homepage Hero" />
                    </div>

                    {{-- Key field --}}
                    <div>
                        <div class="text-sm font-medium">Key</div>
                        <input wire:model.live="key" class="mt-1 w-full rounded-xl border px-3 py-2"
                            placeholder="home-hero" />
                        <div class="text-xs text-gray-500 mt-1">
                            Use in theme:
                            <code class="px-1 py-0.5 border rounded">slider_render('{{ $key ?: 'home-hero' }}')</code>
                        </div>
                    </div>

                    {{-- Variant --}}
                    <div>
                        <div class="text-sm font-medium">Variant</div>

                        @php
                            $variantOptions = $variants ?? [
                                'default' => 'Siatex: Default',
                                'cinematic-pro' => 'Siatex: Cinematic Pro (GSAP + Swiper)',
                            ];
                        @endphp

                        <select wire:model.live="siatex_variant" class="mt-1 w-full rounded-xl border px-3 py-2">
                            @foreach ($variantOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        <div class="text-xs text-gray-500 mt-1">
                            <b>Default</b> = normal slider layout. <br>
                            <b>Cinematic Pro</b> = full-width hero with smooth video-like motion (Swiper + GSAP). <br>
                            For Cinematic Pro, navigation & indicators are <b>off by default</b>.
                        </div>
                    </div>

                    {{-- active --}}
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="is_active" class="rounded border-gray-300" />
                            Active
                        </label>
                    </div>

                    {{-- category (auto sync on change) --}}
                    <div class="md:col-span-2">
                        <div>
                            <div class="text-sm font-medium">Media Category</div>
                            <div class="text-xs text-gray-500">
                                Select category → slides auto-added (you can remove slides after).
                            </div>
                        </div>

                        <select wire:model.live="category_id" class="mt-2 w-full rounded-xl border px-3 py-2">
                            <option value="">— Select category —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>

                        @if (!$editingId)
                            <div class="mt-2 text-xs text-gray-500">
                                Save first to attach slides.
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="text-sm font-medium">Title</div>
                        <input wire:model.live="title" class="mt-1 w-full rounded-xl border px-3 py-2"
                            placeholder="Apparel Manufacturing, Made Simple" />
                    </div>

                    <div>
                        <div class="text-sm font-medium">Subtitle</div>
                        <input wire:model.live="subtitle" class="mt-1 w-full rounded-xl border px-3 py-2"
                            placeholder="OEM Apparel, Private Label..." />
                    </div>

                    {{-- Default-only settings (layout) --}}
                    <div class="{{ $siatex_variant === 'cinematic-pro' ? 'opacity-50 pointer-events-none' : '' }}">
                        <div class="text-sm font-medium">Layout (Desktop)</div>
                        <select wire:model.live="layout" class="mt-1 w-full rounded-xl border px-3 py-2">
                            <option value="image-left">Image Left / Content Right</option>
                            <option value="image-right">Image Right / Content Left</option>
                        </select>
                        <div class="text-xs text-gray-500 mt-1">Mobile: image always on top.</div>
                    </div>

                    <div>
                        <div class="text-sm font-medium">Height</div>
                        <input wire:model.live="height" class="mt-1 w-full rounded-xl border px-3 py-2"
                            placeholder="560px or 100vh" />
                    </div>

                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="show_navigation" class="rounded border-gray-300" />
                            Navigation arrows
                        </label>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="show_indicators" class="rounded border-gray-300" />
                            Indicators
                        </label>

                        <div class="{{ $siatex_variant === 'cinematic-pro' ? 'opacity-50 pointer-events-none' : '' }}">
                            <div class="text-sm font-medium">Indicator style</div>
                            <select wire:model.live="indicator_style" class="mt-1 w-full rounded-xl border px-3 py-2">
                                <option value="dots">Dots</option>
                                <option value="flat">Flat</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-medium">Speed (ms)</div>
                        <input type="number" wire:model.live="delay" class="mt-1 w-full rounded-xl border px-3 py-2"
                            min="1000" step="500" />
                    </div>

                    <div>
                        <div class="text-sm font-medium">Background color</div>
                        <input wire:model.live="bg_color" class="mt-1 w-full rounded-xl border px-3 py-2"
                            placeholder="#f5f5f5" />
                    </div>

                    {{-- ✅ Cinematic Pro controls --}}
                    @if ($siatex_variant === 'cinematic-pro')
                        <div wire:key="cinepro-{{ $editingId ?? 'new' }}"
                            class="md:col-span-2 rounded-2xl border bg-gray-50 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-medium text-sm">Cinematic Pro motion</div>
                                <div class="text-xs text-gray-500">Video-like movement</div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <div class="text-sm font-medium">Motion duration (sec)</div>
                                    <input type="number" step="0.5" min="2"
                                        wire:model.live="motion_duration"
                                        class="mt-1 w-full rounded-xl border px-3 py-2" />
                                </div>

                                <div>
                                    <div class="text-sm font-medium">Zoom min</div>
                                    <input type="number" step="0.01" min="1" wire:model.live="zoom_min"
                                        class="mt-1 w-full rounded-xl border px-3 py-2" />
                                </div>

                                <div>
                                    <div class="text-sm font-medium">Zoom max</div>
                                    <input type="number" step="0.01" min="1" wire:model.live="zoom_max"
                                        class="mt-1 w-full rounded-xl border px-3 py-2" />
                                </div>

                                <div>
                                    <div class="text-sm font-medium">Pan strength (%)</div>
                                    <input type="number" step="0.25" min="0"
                                        wire:model.live="pan_strength"
                                        class="mt-1 w-full rounded-xl border px-3 py-2" />
                                </div>

                                <div>
                                    <div class="text-sm font-medium">Rotate (deg)</div>
                                    <input type="number" step="0.1" min="0" wire:model.live="rotate"
                                        class="mt-1 w-full rounded-xl border px-3 py-2" />
                                </div>

                                <div>
                                    <div class="text-sm font-medium">Fade speed (ms)</div>
                                    <input type="number" step="50" min="100" wire:model.live="fade_ms"
                                        class="mt-1 w-full rounded-xl border px-3 py-2" />
                                </div>

                                <div class="md:col-span-3">
                                    <div class="text-sm font-medium">Overlay (0.00 - 0.75)</div>
                                    <div class="mt-1 flex items-center gap-3">
                                        <input type="number" step="0.01" min="0" max="0.75"
                                            wire:model.live="overlay_opacity"
                                            class="w-36 rounded-xl border px-3 py-2" />
                                        <div class="text-xs text-gray-600">
                                            Higher value = darker overlay. Recommended: <b>0.30 - 0.60</b>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-xs text-gray-600 mt-3">
                                Tip: For stronger “video” feel → set Zoom max ~1.22 and Pan ~3.0. For subtle → Zoom max
                                1.14 and Pan 1.5.
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Slides (✅ UPDATED: show thumbnails + media info) --}}
                <div class="mt-8 border-t pt-6">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm">Slides</div>

                        @php
                            $freshSlider = $editingId ? \App\Models\Slider::find($editingId) : null;

                            // ✅ Load slides WITH media
                            $freshSlides = $freshSlider
                                ? $freshSlider->slides()->with('media')->orderBy('sort_order')->get()
                                : collect();

                            $slidesCount = $freshSlides->count();
                        @endphp

                        <div class="text-xs text-gray-500">
                            Total: {{ $slidesCount }} · Remove now (Add/Reorder will be next step).
                        </div>
                    </div>

                    @if (!$editingId)
                        <div class="mt-3 text-sm text-gray-500">Save first to manage slides.</div>
                    @elseif ($freshSlides->isEmpty())
                        <div class="mt-3 text-sm text-gray-500">
                            No slides yet. Select a category and slides will auto-add after save / change.
                        </div>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach ($freshSlides as $slide)
                                @php
                                    $media = $slide->media;

                                    $mediaLabel =
                                        (string) ($media->title ?? '') !== ''
                                            ? (string) $media->title
                                            : ((string) ($media->name ?? '') !== ''
                                                ? (string) $media->name
                                                : 'Image');

                                    // ✅ Try common fields; adjust if your Media model uses other names
                                    $thumb = $media?->thumbUrl() ?? ($media?->isImage() ? $media?->url() : null);
                                @endphp

                                <div class="flex items-center justify-between rounded-xl border px-3 py-3 gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="h-12 w-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0 border">
                                            @if ($thumb)
                                                <img src="{{ $thumb }}" alt=""
                                                    class="h-full w-full object-cover" />
                                            @else
                                                <div
                                                    class="h-full w-full flex items-center justify-center text-[10px] text-gray-500">
                                                    No<br>thumb
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <div class="text-sm font-medium truncate">
                                                Slide #{{ $slide->sort_order }} · {{ $mediaLabel }}
                                            </div>

                                            <div class="text-xs text-gray-500 truncate">
                                                Media ID: {{ $slide->media_id }}
                                                @if ($media && !empty($media->mime_type))
                                                    · {{ $media->mime_type }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <button wire:click="removeSlide({{ $slide->id }})"
                                        class="text-sm rounded-lg border px-3 py-1.5 hover:bg-gray-50 flex-shrink-0">
                                        Remove
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Bottom Save button --}}
                <div class="mt-8 flex items-center justify-end border-t pt-6">
                    <button wire:click="save"
                        class="rounded-xl bg-black text-white px-5 py-2.5 text-sm hover:opacity-90">
                        Save changes
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
