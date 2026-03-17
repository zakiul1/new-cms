@php
    $id = $node['id'] ?? null;
    $item = $items[$id] ?? null;

    if (!$item) {
        return;
    }

    $children = $node['children'] ?? [];
    $isCollapsed = (bool) ($collapsed[$id] ?? false);

    $linkedType = $item['linked_type'] ?? null;
    $linkedId = $item['linked_id'] ?? null;
    $kind = $item['kind'] ?? 'custom';

    $title =
        $item['label'] ??
        ($item['title'] ??
            ($item['menu_title'] ?? ($item['name'] ?? ($item['post_title'] ?? ($item['linked_title'] ?? 'Untitled')))));

    $url = $item['url'] ?? '';
    $target = $item['target'] ?? '_self';
    $cssClass = $item['css_class'] ?? '';
    $rel = $item['rel'] ?? '';
    $description = $item['description'] ?? '';
    $column = $item['column'] ?? null;

    // existing / custom fields
    $menuType = $item['menu_type'] ?? null;
    $megaEnabled = !empty($item['mega_enabled']);
    $megaColumns = $item['mega_columns'] ?? null;
    $megaColumn = $item['mega_column'] ?? ($item['column'] ?? 1);
    $megaContinuation = !empty($item['mega_continuation']);
    $icon = $item['icon'] ?? null;
@endphp

<li wire:key="menu-item-{{ $id }}" data-id="{{ $id }}" data-menu-item="1" class="menu-item-shell"
    style="--menu-depth: {{ $level }};">
    <div class="menu-item-bar">
        <div class="menu-item-handle">
            <div class="menu-item-title-row">
                <div class="menu-item-title-main">
                    <span
                        class="menu-item-drag-handle text-gray-500 hover:text-gray-800 cursor-grab active:cursor-grabbing"
                        data-drag-handle="1" role="button" tabindex="0" title="Drag menu item"
                        aria-label="Drag menu item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </span>

                    <span class="menu-item-title-text">{{ $title }}</span>

                    <span class="menu-item-title-meta">
                        @if ($kind === 'custom')
                            Custom Link
                        @elseif ($kind === 'page')
                            Page
                        @elseif ($kind === 'post')
                            Post
                        @elseif ($kind === 'term')
                            Term
                        @else
                            {{ ucfirst($kind) }}
                        @endif
                    </span>

                    <span class="menu-item-meta-badges">
                        @if (!empty($column))
                            <span class="menu-item-meta-badge">Column {{ $column }}</span>
                        @endif

                        @if ($megaEnabled)
                            <span class="menu-item-meta-badge">Mega Menu</span>
                        @endif

                        @if (!empty($megaColumns))
                            <span class="menu-item-meta-badge">{{ $megaColumns }} Columns</span>
                        @endif

                        @if ($megaContinuation)
                            <span class="menu-item-meta-badge">Continuation</span>
                        @endif

                        @if (!empty($children))
                            <span class="menu-item-meta-badge">
                                {{ count($children) }} sub item{{ count($children) > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </span>
                </div>

                <div class="menu-item-actions">
                    <button type="button" class="text-gray-500 hover:text-red-600"
                        wire:click="removeItem({{ $id }})" data-no-drag="1" title="Delete item"
                        aria-label="Delete item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M3 6h18M8 6V4h8v2m-7 4v6m4-6v6M6 6l1 14h10l1-14" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <button type="button" class="menu-item-toggle" wire:click="toggleCollapse({{ $id }})"
                        data-no-drag="1" aria-expanded="{{ $isCollapsed ? 'false' : 'true' }}"
                        title="{{ $isCollapsed ? 'Expand' : 'Collapse' }}">
                        @if ($isCollapsed)
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        @else
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if (!$isCollapsed)
        <div class="menu-item-settings" data-no-drag="1">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Navigation Label</label>
                    <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                        wire:change="updateItemField({{ $id }}, 'label', $event.target.value)"
                        value="{{ $title }}">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">URL</label>
                    <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                        wire:change="updateItemField({{ $id }}, 'url', $event.target.value)"
                        value="{{ $url }}">
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Target</label>
                        <select class="w-full border border-gray-300 px-3 py-2 text-sm"
                            wire:change="updateItemField({{ $id }}, 'target', $event.target.value)">
                            <option value="_self" @selected($target === '_self')>Same tab</option>
                            <option value="_blank" @selected($target === '_blank')>New tab</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Column</label>
                        <select class="w-full border border-gray-300 px-3 py-2 text-sm"
                            wire:change="updateItemField({{ $id }}, 'column', $event.target.value)">
                            <option value="">None</option>
                            <option value="1" @selected((string) $column === '1')>Column 1</option>
                            <option value="2" @selected((string) $column === '2')>Column 2</option>
                            <option value="3" @selected((string) $column === '3')>Column 3</option>
                            <option value="4" @selected((string) $column === '4')>Column 4</option>
                            <option value="5" @selected((string) $column === '5')>Column 5</option>
                            <option value="6" @selected((string) $column === '6')>Column 6</option>
                        </select>
                    </div>
                </div>

                <div class="border border-gray-200 rounded p-3 bg-gray-50">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-600">
                        Mega Menu Settings
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="flex items-center gap-2">
                            <input id="mega_enabled_{{ $id }}" type="checkbox"
                                wire:change="updateItemField({{ $id }}, 'mega_enabled', $event.target.checked)"
                                @checked($megaEnabled)>
                            <label for="mega_enabled_{{ $id }}" class="text-sm text-gray-700">
                                Enable Mega Menu
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="mega_continuation_{{ $id }}" type="checkbox"
                                wire:change="updateItemField({{ $id }}, 'mega_continuation', $event.target.checked)"
                                @checked($megaContinuation)>
                            <label for="mega_continuation_{{ $id }}" class="text-sm text-gray-700">
                                Continuation Group
                            </label>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Mega Menu Columns</label>
                            <select class="w-full border border-gray-300 px-3 py-2 text-sm"
                                wire:change="updateItemField({{ $id }}, 'mega_columns', $event.target.value)">
                                <option value="">Default</option>
                                <option value="2" @selected((string) $megaColumns === '2')>2 Columns</option>
                                <option value="3" @selected((string) $megaColumns === '3')>3 Columns</option>
                                <option value="4" @selected((string) $megaColumns === '4')>4 Columns</option>
                                <option value="5" @selected((string) $megaColumns === '5')>5 Columns</option>
                                <option value="6" @selected((string) $megaColumns === '6')>6 Columns</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Mega Column</label>
                            <select class="w-full border border-gray-300 px-3 py-2 text-sm"
                                wire:change="updateItemField({{ $id }}, 'mega_column', $event.target.value)">
                                <option value="1" @selected((string) $megaColumn === '1')>Column 1</option>
                                <option value="2" @selected((string) $megaColumn === '2')>Column 2</option>
                                <option value="3" @selected((string) $megaColumn === '3')>Column 3</option>
                                <option value="4" @selected((string) $megaColumn === '4')>Column 4</option>
                                <option value="5" @selected((string) $megaColumn === '5')>Column 5</option>
                                <option value="6" @selected((string) $megaColumn === '6')>Column 6</option>
                            </select>
                        </div>
                    </div>

                    @if (array_key_exists('menu_type', $item))
                        <div class="mt-3">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Menu Type</label>
                            <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                                wire:change="updateItemField({{ $id }}, 'menu_type', $event.target.value)"
                                value="{{ $menuType }}">
                        </div>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Title Attribute</label>
                    <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                        wire:change="updateItemField({{ $id }}, 'description', $event.target.value)"
                        value="{{ $description }}">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">CSS Classes</label>
                    <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                        wire:change="updateItemField({{ $id }}, 'css_class', $event.target.value)"
                        value="{{ $cssClass }}">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Link Relationship (XFN)</label>
                    <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                        wire:change="updateItemField({{ $id }}, 'rel', $event.target.value)"
                        value="{{ $rel }}">
                </div>

                @if (!empty($icon))
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Icon</label>
                        <input type="text" class="w-full border border-gray-300 px-3 py-2 text-sm"
                            wire:change="updateItemField({{ $id }}, 'icon', $event.target.value)"
                            value="{{ $icon }}">
                    </div>
                @endif

                @if ($linkedType || $linkedId)
                    <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                        Linked source:
                        <span class="font-medium">{{ $linkedType ?: '-' }}</span>
                        @if ($linkedId)
                            #{{ $linkedId }}
                        @endif
                    </div>
                @endif

                <div class="pt-1">
                    <button type="button" class="text-sm text-red-600 hover:text-red-700"
                        wire:click="removeItem({{ $id }})" data-no-drag="1">
                        Remove
                    </button>
                </div>
            </div>
        </div>
    @endif

    <ul data-menu-ul="1" data-children-ul="1" class="menu-children-list">
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
