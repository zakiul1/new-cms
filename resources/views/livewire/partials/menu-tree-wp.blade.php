@php
    $id = (int) ($node['id'] ?? 0);
    $row = $items[$id] ?? null;

    if (!is_array($row)) {
        $row = [
            'label' => 'Item #' . $id,
            'url' => '',
            'is_enabled' => true,
            'target' => '',
            'rel' => '',
            'css_class' => '',
            'css_id' => '',
            'icon' => '',
            'description' => '',
            'visibility' => [],
            'data' => [],
        ];
    }

    // ✅ Default should be collapsed (true)
    $isCollapsed = (bool) ($collapsed[$id] ?? true);

    $children = $node['children'] ?? [];
    $level = (int) ($level ?? 0);

    // type label
    $typeLabel = 'Custom Link';
    if (!empty($row['data']['type_label'])) {
        $typeLabel = (string) $row['data']['type_label'];
    }
@endphp

<li data-id="{{ $id }}" class="bg-white">
    {{-- indent like WP --}}
    <div class="border border-gray-200 rounded-md" style="margin-left: {{ $level * 18 }}px;">
        {{-- header row (✅ whole row is the draggable row) --}}
        <div class="flex items-center justify-between px-3 py-2 cursor-grab active:cursor-grabbing select-none"
            data-row="1">
            <div class="flex items-center gap-2 min-w-0">
                {{-- icon only (NOT required for drag anymore) --}}
                <span class="text-gray-400" title="Drag">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9 6h.01M9 12h.01M9 18h.01M15 6h.01M15 12h.01M15 18h.01" stroke="currentColor"
                            stroke-width="3" stroke-linecap="round" />
                    </svg>
                </span>

                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate">
                        {{ $row['label'] ?? 'Item #' . $id }}
                        <span class="ml-2 text-xs text-gray-500 font-normal">{{ $typeLabel }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- remove (✅ no-drag so clicking doesn't start drag) --}}
                <button type="button" class="text-gray-400 hover:text-red-600" data-no-drag
                    wire:click="removeItem({{ $id }})" title="Remove">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 7h12M9 7V5h6v2M8 7l1 14h6l1-14" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                {{-- expand/collapse (✅ no-drag) --}}
                <button type="button" class="text-gray-500 hover:text-gray-800" data-no-drag
                    wire:click="toggleCollapse({{ $id }})" title="Expand / Collapse">
                    @if ($isCollapsed)
                        {{-- right arrow --}}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    @else
                        {{-- down arrow --}}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    @endif
                </button>
            </div>
        </div>

        {{-- details (only show when expanded) --}}
        @if (!$isCollapsed)
            <div class="px-3 pb-3 border-t border-gray-200" data-no-drag>
                <div class="grid grid-cols-1 gap-3 mt-3">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Navigation Label</div>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                            wire:model.defer="items.{{ $id }}.label" wire:change="markItemsDirty">
                    </div>

                    <div>
                        <div class="text-xs text-gray-500 mb-1">URL</div>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                            wire:model.defer="items.{{ $id }}.url" wire:change="markItemsDirty"
                            placeholder="https://...">
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-gray-500 mb-1">Target</div>
                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                wire:model.defer="items.{{ $id }}.target" wire:change="markItemsDirty"
                                placeholder="_blank">
                        </div>

                        <div>
                            <div class="text-xs text-gray-500 mb-1">Rel</div>
                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                wire:model.defer="items.{{ $id }}.rel" wire:change="markItemsDirty"
                                placeholder="nofollow">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-gray-500 mb-1">CSS Class</div>
                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                wire:model.defer="items.{{ $id }}.css_class" wire:change="markItemsDirty">
                        </div>

                        <div>
                            <div class="text-xs text-gray-500 mb-1">CSS ID</div>
                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                wire:model.defer="items.{{ $id }}.css_id" wire:change="markItemsDirty">
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500 mb-1">Description</div>
                        <textarea class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" rows="2"
                            wire:model.defer="items.{{ $id }}.description" wire:change="markItemsDirty"></textarea>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- children UL (must exist for nesting drops like your JS expects) --}}
    {{-- ✅ min-height helps intentional drops; still blocked by onMove unless cursor is indented --}}
    <ul data-menu-ul="1" data-children-ul="1" class="mt-2 space-y-2 min-h-[8px]">
        @foreach ($children as $child)
            @include('livewire.partials.menu-tree-wp', [
                'node' => $child,
                'items' => $items,
                'collapsed' => $collapsed,
                'level' => $level + 1,
            ])
        @endforeach
    </ul>
</li>
