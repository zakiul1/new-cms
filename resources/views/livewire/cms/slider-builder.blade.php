<div class="w-full">
    <div class="grid grid-cols-12 gap-6">
        {{-- LEFT: Sliders list --}}
        <div class="col-span-12 lg:col-span-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Sliders</h2>
                    <button
                        wire:click="resetSlideForm"
                        class="text-xs font-semibold text-primary-600 hover:underline"
                        type="button"
                    >
                        Clear slide
                    </button>
                </div>

                <div class="mt-3 space-y-2">
                    @foreach ($sliders as $s)
                        <button
                            type="button"
                            wire:click="selectSlider({{ $s->id }})"
                            class="w-full rounded-xl border px-3 py-2 text-left text-sm transition
                                {{ $sliderId === $s->id ? 'border-primary-600 bg-primary-50' : 'border-gray-200 hover:bg-gray-50' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-gray-900">{{ $s->name }}</div>
                                    <div class="truncate text-xs text-gray-500">{{ $s->key }}</div>
                                </div>

                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px]
                                    {{ $s->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $s->is_active ? 'Active' : 'Off' }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="mt-4 border-t pt-4">
                    <div class="text-xs font-semibold text-gray-700">Create new</div>

                    <div class="mt-2 space-y-2">
                        <input wire:model="name" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Name">
                        <input wire:model="key" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Key (home-hero)">
                        <button wire:click="createSlider" type="button"
                            class="w-full rounded-xl bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Add Slider
                        </button>
                        @error('name') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                        @error('key') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Editor --}}
        <div class="col-span-12 lg:col-span-9">
            @if (!$sliderId)
                <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-gray-500">
                    Create a slider to start.
                </div>
            @else
                {{-- Top settings --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-lg font-semibold text-gray-900">Slider Settings</h1>
                            <div class="mt-1 text-xs text-gray-500">
                                Use in theme: <span class="font-mono">{!! slider_render("{{ $key ?: 'home-hero' }}") !!}</span>
                            </div>
                        </div>

                        <button
                            wire:click="deleteSlider({{ $sliderId }})"
                            type="button"
                            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100"
                        >
                            Delete slider
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Name</label>
                            <input wire:model="name" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Key</label>
                            <input wire:model="key" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300">
                            <span class="text-sm text-gray-700">Active</span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3 md:col-span-2">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" wire:model="autoplay" class="rounded border-gray-300">
                                <span class="text-sm text-gray-700">Autoplay</span>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-700">Delay (ms)</label>
                                <input wire:model="delay" type="number" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-700">Height</label>
                                <input wire:model="height" class="mt-1 w-full rounded-xl border-gray-200 text-sm" placeholder="auto or 560px">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button wire:click="saveSlider" type="button"
                            class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Save settings
                        </button>
                    </div>
                </div>

                {{-- Slides + editor --}}
                <div class="mt-6 grid grid-cols-12 gap-6">
                    {{-- Slides list --}}
                    <div class="col-span-12 lg:col-span-6">
                        <div class="rounded-2xl border border-gray-200 bg-white p-5">
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-semibold text-gray-900">Slides</h2>
                                <button wire:click="resetSlideForm" type="button"
                                    class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold hover:bg-gray-50">
                                    New slide
                                </button>
                            </div>

                            <div class="mt-4 space-y-3">
                                @forelse ($slides as $slide)
                                    @php
                                        $src = $slide->media?->thumbUrl('jpeg') ?: $slide->media?->thumbUrl() ?: $slide->media?->url();
                                    @endphp
                                    <div class="flex items-center gap-3 rounded-2xl border border-gray-200 p-3">
                                        <div class="h-14 w-14 overflow-hidden rounded-xl bg-gray-100">
                                            @if ($src)
                                                <img src="{{ $src }}" class="h-full w-full object-cover" alt="">
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold text-gray-900">
                                                {{ $slide->title ?: 'Untitled slide' }}
                                            </div>
                                            <div class="truncate text-xs text-gray-500">
                                                #{{ $slide->sort_order }} • {{ $slide->is_active ? 'Active' : 'Off' }}
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button wire:click="moveSlide({{ $slide->id }}, 'up')" type="button"
                                                class="rounded-lg border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50">
                                                ↑
                                            </button>
                                            <button wire:click="moveSlide({{ $slide->id }}, 'down')" type="button"
                                                class="rounded-lg border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50">
                                                ↓
                                            </button>

                                            <button wire:click="editSlide({{ $slide->id }})" type="button"
                                                class="rounded-lg bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700">
                                                Edit
                                            </button>

                                            <button wire:click="deleteSlide({{ $slide->id }})" type="button"
                                                class="rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500">
                                        No slides yet. Click “New slide”.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Slide editor --}}
                    <div class="col-span-12 lg:col-span-6">
                        <div class="rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 class="text-sm font-semibold text-gray-900">
                                {{ $slideId ? 'Edit Slide' : 'Create Slide' }}
                            </h2>

                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-semibold text-gray-700">Image</div>
                                    <button wire:click="openMediaPicker" type="button"
                                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold hover:bg-gray-50">
                                        Choose from Media
                                    </button>
                                </div>

                                <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50">
                                    <div class="aspect-[16/9] w-full">
                                        @if ($selectedMedia)
                                            @php
                                                $src = $selectedMedia->thumbUrl('jpeg') ?: $selectedMedia->thumbUrl() ?: $selectedMedia->url();
                                            @endphp
                                            <img src="{{ $src }}" class="h-full w-full object-cover" alt="">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-sm text-gray-500">
                                                No image selected
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @error('media_id') <div class="mt-2 text-xs text-red-600">{{ $message }}</div> @enderror
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3">
                                <input wire:model="slide_title" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Title">
                                <input wire:model="slide_subtitle" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Subtitle">

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <input wire:model="button_text" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Button text">
                                    <input wire:model="button_url" class="w-full rounded-xl border-gray-200 text-sm md:col-span-2" placeholder="Button URL">
                                </div>

                                <div class="flex items-center gap-3">
                                    <input type="checkbox" wire:model="button_new_tab" class="rounded border-gray-300">
                                    <span class="text-sm text-gray-700">Open in new tab</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input type="checkbox" wire:model="slide_active" class="rounded border-gray-300">
                                    <span class="text-sm text-gray-700">Slide active</span>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button wire:click="saveSlide" type="button"
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                                    Save slide
                                </button>
                            </div>

                            {{-- Media picker modal --}}
                            @if ($showMediaPicker)
                                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showMediaPicker', false)">
                                    <div class="w-full max-w-5xl rounded-2xl bg-white p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-sm font-semibold">Pick an image</div>
                                            <button type="button" class="rounded-lg border px-2 py-1 text-sm" wire:click="$set('showMediaPicker', false)">Close</button>
                                        </div>

                                        <div class="mt-3">
                                            <input wire:model.live="mediaSearch" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Search media...">
                                        </div>

                                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                            @foreach ($mediaResults as $m)
                                                @php
                                                    $src = $m->thumbUrl('jpeg') ?: $m->thumbUrl() ?: $m->url();
                                                    $label = $m->title ?: $m->original_filename ?: ('Media #' . $m->id);
                                                @endphp
                                                <button type="button" wire:click="pickMedia({{ $m->id }})"
                                                    class="group overflow-hidden rounded-2xl border border-gray-200 bg-white text-left hover:border-primary-400">
                                                    <div class="aspect-square w-full bg-gray-100">
                                                        <img src="{{ $src }}" class="h-full w-full object-cover" alt="">
                                                    </div>
                                                    <div class="p-2">
                                                        <div class="truncate text-xs font-semibold text-gray-900">{{ $label }}</div>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
