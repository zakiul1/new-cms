{{-- resources/views/livewire/partials/menu-tree-items.blade.php --}}

@foreach ($nodes as $node)
    @php($id = (int) ($node['id'] ?? 0))
    @continue($id <= 0)

    @php($isCollapsed = (bool) ($collapsed[$id] ?? false))
    @php($lastSaved = (int) ($savedAt[$id] ?? 0))
    @php($showSaved = $lastSaved > 0 && time() - $lastSaved <= 3)

    <li wire:key="menu-item-{{ $id }}" data-id="{{ $id }}"
        class="rounded-lg border p-3 bg-white list-none">
        <div class="flex items-start gap-3">
            <button type="button" class="cursor-move select-none" data-drag-handle title="Drag">☰</button>

            <div class="flex-1">
                <div class="flex items-start justify-between gap-3">
                    <button type="button" class="text-sm font-medium text-left"
                        wire:click="toggleCollapse({{ $id }})">
                        {{ $isCollapsed ? '▶' : '▼' }}
                        {{ $items[$id]['label'] ?? 'Menu Item' }}
                    </button>

                    <div class="flex items-center gap-3">
                        @if ($showSaved)
                            <span class="text-xs text-green-600">Saved</span>
                        @endif

                        <x-filament::button size="sm" color="danger"
                            x-on:click="if(confirm('Remove this item (and children)?')) $wire.removeItem({{ $id }})">
                            Remove
                        </x-filament::button>
                    </div>
                </div>

                @if (!$isCollapsed)
                    <div class="mt-3 space-y-3">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.live.debounce.800ms="items.{{ $id }}.label"
                                    placeholder="Label" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.live.debounce.800ms="items.{{ $id }}.url"
                                    placeholder="/slug or https://..." />
                            </x-filament::input.wrapper>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="items.{{ $id }}.is_enabled">
                            Enabled
                        </label>

                        <details>
                            <summary class="cursor-pointer text-sm font-medium">Advanced (Premium)</summary>

                            <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-2">
                                <x-filament::input.wrapper>
                                    <x-filament::input wire:model.live.debounce.800ms="items.{{ $id }}.target"
                                        placeholder="target (e.g. _blank)" />
                                </x-filament::input.wrapper>

                                <x-filament::input.wrapper>
                                    <x-filament::input wire:model.live.debounce.800ms="items.{{ $id }}.rel"
                                        placeholder="rel (e.g. nofollow ugc)" />
                                </x-filament::input.wrapper>

                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        wire:model.live.debounce.800ms="items.{{ $id }}.css_class"
                                        placeholder="CSS class" />
                                </x-filament::input.wrapper>

                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        wire:model.live.debounce.800ms="items.{{ $id }}.css_id"
                                        placeholder="CSS id" />
                                </x-filament::input.wrapper>

                                <x-filament::input.wrapper>
                                    <x-filament::input wire:model.live.debounce.800ms="items.{{ $id }}.icon"
                                        placeholder="Icon (string)" />
                                </x-filament::input.wrapper>

                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        wire:model.live.debounce.800ms="items.{{ $id }}.description"
                                        placeholder="Description" />
                                </x-filament::input.wrapper>
                            </div>

                            <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-2">
                                <div class="rounded-lg border p-3">
                                    <div class="text-sm font-medium mb-2">Visibility</div>

                                    <div class="space-y-2 text-sm">
                                        <label class="flex items-center gap-2">
                                            <input type="radio" value="any"
                                                wire:model.live="items.{{ $id }}.visibility.auth">
                                            Any
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="radio" value="guest"
                                                wire:model.live="items.{{ $id }}.visibility.auth">
                                            Guest only
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="radio" value="auth"
                                                wire:model.live="items.{{ $id }}.visibility.auth">
                                            Logged-in only
                                        </label>

                                        <div class="pt-2">
                                            <div class="text-xs text-gray-500 mb-1">Roles (comma separated)</div>
                                            <x-filament::input.wrapper>
                                                <x-filament::input
                                                    wire:model.live.debounce.800ms="items.{{ $id }}.visibility.roles_csv"
                                                    placeholder="admin, editor" />
                                            </x-filament::input.wrapper>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-lg border p-3">
                                    <div class="text-sm font-medium mb-2">Mega Menu</div>

                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox"
                                            wire:model.live="items.{{ $id }}.data.mega.enabled">
                                        Enable mega menu (top-level)
                                    </label>

                                    <div class="mt-2">
                                        <div class="text-xs text-gray-500 mb-1">Columns</div>
                                        <x-filament::input.wrapper>
                                            <select class="fi-input w-full"
                                                wire:model.live="items.{{ $id }}.data.mega.columns">
                                                <option value="">Default</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                            </select>
                                        </x-filament::input.wrapper>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                @endif
            </div>
        </div>

        {{-- ✅ Always present child dropzone (even if empty) --}}
        <ul class="space-y-2 mt-3 pl-6 border-l min-h-[14px] list-none p-0 m-0" data-menu-ul data-children-ul="1">
            @if (!empty($node['children']) && is_array($node['children']))
                @include('livewire.partials.menu-tree-items', [
                    'nodes' => $node['children'],
                    'collapsed' => $collapsed,
                    'items' => $items,
                    'savedAt' => $savedAt,
                ])
            @endif
        </ul>
    </li>
@endforeach
