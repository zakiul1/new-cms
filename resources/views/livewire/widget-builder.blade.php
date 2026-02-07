<div class="space-y-6">
    <x-filament::section>
        <div class="text-lg font-semibold">Widgets</div>
        <div class="text-sm text-gray-500">Add widgets to widget areas and reorder them with drag & drop.</div>
    </x-filament::section>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- LEFT: Areas + Add --}}
        <div class="lg:col-span-4 space-y-4">
            <x-filament::section>
                <div class="text-sm font-semibold mb-2">Widget Areas</div>

                <div class="space-y-1">
                    @foreach ($areas as $a)
                        <button type="button" wire:click="selectArea('{{ $a->key }}')"
                            class="w-full text-left rounded-lg border px-3 py-2 text-sm
                                {{ $activeAreaKey === $a->key ? 'bg-gray-50 font-medium' : 'bg-white' }}">
                            {{ $a->label }}
                            <div class="text-xs text-gray-500">{{ $a->key }}</div>
                        </button>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm font-semibold mb-2">Add Widget</div>

                <div class="space-y-3">
                    <x-filament::input.wrapper>
                        <select class="fi-input w-full" wire:model.live="newWidgetType">
                            @foreach ($widgetTypeOptions as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model.defer="newWidgetTitle" placeholder="Widget title (optional)" />
                    </x-filament::input.wrapper>

                    <x-filament::button wire:click="createAndPlaceWidget">
                        Create & Place
                    </x-filament::button>

                    <div class="pt-3 border-t"></div>

                    <div class="text-xs text-gray-500">Place existing widget (re-use)</div>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model.live.debounce.300ms="searchExisting"
                            placeholder="Search existing..." />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <select class="fi-input w-full" wire:model="existingWidgetId">
                            <option value="">Select widget...</option>
                            @foreach ($existingWidgets as $w)
                                <option value="{{ $w->id }}">
                                    #{{ $w->id }} — {{ $w->title ?: '(no title)' }} ({{ $w->type }})
                                </option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>

                    <x-filament::button color="gray" wire:click="placeExistingWidget">
                        Add to Area
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>

        {{-- RIGHT: Placements + Editor --}}
        <div class="lg:col-span-8 space-y-4">
            <x-filament::section>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold">Area Widgets</div>
                        <div class="text-sm text-gray-500">Drag & drop to reorder.</div>
                    </div>
                </div>

                <div class="my-4 border-t"></div>

                <div id="widget-area-root">
                    <ul class="space-y-2 list-none p-0 m-0" data-widget-ul>
                        @foreach ($placements as $pid => $p)
                            @php($wid = (int) ($p['widget_id'] ?? 0))
                            @php($w = $widgets[$wid] ?? null)

                            <li class="rounded-lg border p-3 bg-white list-none" data-id="{{ $pid }}"
                                wire:key="placement-{{ $pid }}">

                                <div class="flex items-start gap-3">
                                    <button type="button" class="cursor-move select-none" data-drag-handle
                                        title="Drag">☰</button>

                                    <button type="button" class="flex-1 text-left"
                                        wire:click="selectPlacement({{ $pid }})">
                                        <div class="text-sm font-medium">
                                            {{ $w['title'] ?? '(no title)' }}
                                            <span class="text-xs text-gray-500">({{ $w['type'] ?? 'unknown' }})</span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Widget #{{ $wid }} • Placement #{{ $pid }}
                                        </div>
                                    </button>

                                    <x-filament::button size="sm" color="danger"
                                        x-on:click="if(confirm('Remove from this area?')) $wire.removePlacement({{ $pid }})">
                                        Remove
                                    </x-filament::button>
                                </div>

                                @if ($activePlacementId === (int) $pid && $wid > 0 && is_array($w))
                                    <div class="mt-4 space-y-4">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                                            <x-filament::input.wrapper>
                                                <x-filament::input
                                                    wire:model.live.debounce.600ms="widgets.{{ $wid }}.title"
                                                    placeholder="Title" />
                                            </x-filament::input.wrapper>

                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="checkbox"
                                                    wire:model.live="widgets.{{ $wid }}.is_enabled">
                                                Enabled
                                            </label>
                                        </div>

                                        {{-- Settings (basic MVP: JSON editor). Later we can render Filament schema dynamically. --}}
                                        <div class="rounded-lg border p-3">
                                            <div class="text-sm font-medium mb-2">Settings</div>
                                            <textarea class="fi-input w-full min-h-[120px]" wire:model.live.debounce.800ms="widgets.{{ $wid }}.settings"></textarea>
                                            <div class="text-xs text-gray-500 mt-1">
                                                For now this is JSON-like array editing. Next step: render widget schema
                                                fields (Text/Menu) WP-style.
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                            <div class="rounded-lg border p-3 space-y-2">
                                                <div class="text-sm font-medium">Premium: Overrides</div>

                                                <x-filament::input.wrapper>
                                                    <x-filament::input
                                                        wire:model.live.debounce.600ms="placements.{{ $pid }}.overrides.title"
                                                        placeholder="Title override (optional)" />
                                                </x-filament::input.wrapper>

                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox"
                                                        wire:model.live="placements.{{ $pid }}.overrides.hide_title">
                                                    Hide title
                                                </label>

                                                <x-filament::input.wrapper>
                                                    <x-filament::input
                                                        wire:model.live.debounce.600ms="placements.{{ $pid }}.overrides.css_class"
                                                        placeholder="Extra CSS class" />
                                                </x-filament::input.wrapper>

                                                <x-filament::input.wrapper>
                                                    <select class="fi-input w-full"
                                                        wire:model.live="placements.{{ $pid }}.overrides.wrapper_tag">
                                                        <option value="">Default wrapper</option>
                                                        <option value="div">div</option>
                                                        <option value="aside">aside</option>
                                                        <option value="section">section</option>
                                                    </select>
                                                </x-filament::input.wrapper>
                                            </div>

                                            <div class="rounded-lg border p-3 space-y-2">
                                                <div class="text-sm font-medium">Premium: Visibility</div>

                                                <x-filament::input.wrapper>
                                                    <select class="fi-input w-full"
                                                        wire:model.live="placements.{{ $pid }}.visibility.auth">
                                                        <option value="any">Any</option>
                                                        <option value="guest">Guest only</option>
                                                        <option value="auth">Logged-in only</option>
                                                    </select>
                                                </x-filament::input.wrapper>

                                                <x-filament::input.wrapper>
                                                    <x-filament::input
                                                        wire:model.live.debounce.600ms="placements.{{ $pid }}.visibility.roles_csv"
                                                        placeholder="Roles CSV (admin, editor)" />
                                                </x-filament::input.wrapper>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <x-filament::button color="primary"
                                                wire:click="saveWidget({{ $wid }})">
                                                Save Widget
                                            </x-filament::button>

                                            <x-filament::button color="gray"
                                                wire:click="savePlacement({{ $pid }})">
                                                Save Placement
                                            </x-filament::button>

                                            <x-filament::button color="danger"
                                                x-on:click="if(confirm('Delete the widget completely? (removes from all areas)')) $wire.deleteWidget({{ $wid }})">
                                                Delete Widget
                                            </x-filament::button>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-filament::section>
        </div>
    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
            <script>
                function initWidgetSortables() {
                    if (typeof Sortable === 'undefined') return;

                    document.querySelectorAll('ul[data-widget-ul]').forEach((ul) => {
                        if (ul._sortable) {
                            ul._sortable.destroy();
                            ul._sortable = null;
                        }

                        ul._sortable = new Sortable(ul, {
                            animation: 150,
                            direction: 'vertical',
                            handle: '[data-drag-handle]',
                            draggable: 'li[data-id]',
                            onEnd: () => {
                                const root = document.querySelector('#widget-area-root ul[data-widget-ul]');
                                if (!root) return;

                                const ids = Array.from(root.querySelectorAll(':scope > li[data-id]'))
                                    .map(li => parseInt(li.dataset.id, 10))
                                    .filter(Boolean);

                                @this.reorder(ids);
                            },
                        });
                    });
                }

                document.addEventListener('livewire:init', () => {
                    initWidgetSortables();

                    Livewire.on('widget-builder-init', () => {
                        setTimeout(initWidgetSortables, 0);
                    });

                    document.addEventListener('livewire:navigated', () => {
                        setTimeout(initWidgetSortables, 0);
                    });

                    if (Livewire.hook) {
                        Livewire.hook('morph.updated', () => {
                            setTimeout(initWidgetSortables, 0);
                        });
                    }
                });
            </script>
        @endpush
    @endonce
</div>
