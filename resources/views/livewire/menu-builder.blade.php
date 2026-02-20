<div class="space-y-4" x-data="{
    tab: @entangle('activeTab').live,
}">
    {{-- ✅ TOAST NOTIFICATIONS (BOTTOM RIGHT) --}}
    <div x-data="{
        toasts: [],
        push(t) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, ...t });
            const ttl = t.timeout ?? 2500;
            if (ttl > 0) setTimeout(() => this.remove(id), ttl);
        },
        remove(id) {
            this.toasts = this.toasts.filter(x => x.id !== id);
        }
    }" x-on:toast.window="push($event.detail)"
        class="fixed bottom-5 right-5 z-50 space-y-2 w-[340px] max-w-[90vw]">
        <template x-for="t in toasts" :key="t.id">
            <div class="rounded-md border px-4 py-3 shadow bg-white flex items-start gap-3"
                :class="t.type === 'success' ? 'border-green-200' :
                    t.type === 'error' ? 'border-red-200' :
                    t.type === 'warning' ? 'border-amber-200' :
                    'border-gray-200'">
                <div class="mt-0.5">
                    <template x-if="t.type === 'success'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </template>

                    <template x-if="t.type === 'error'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 9v4m0 4h.01M10.3 3.9l-8.5 14.7A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.4L13.7 3.9a2 2 0 0 0-3.4 0z"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </template>

                    <template x-if="t.type === 'warning'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 9v4m0 4h.01M10.3 3.9l-8.5 14.7A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.4L13.7 3.9a2 2 0 0 0-3.4 0z"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </template>

                    <template x-if="!['success','error','warning'].includes(t.type)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 8h.01M12 12v4M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </template>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900" x-text="t.title ?? 'Changed'"></div>
                    <div class="text-sm text-gray-600 mt-0.5" x-text="t.message ?? ''"></div>
                </div>

                <button type="button" class="text-gray-400 hover:text-gray-700" x-on:click="remove(t.id)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    {{-- TOP TABS (WP style) --}}
    <div class="flex items-center gap-2">
        <button type="button" class="px-3 py-2 text-sm border rounded-md"
            :class="tab === 'edit' ? 'bg-white text-gray-900 border-gray-300' : 'bg-gray-50 text-gray-600 border-gray-200'"
            x-on:click="tab='edit'; $wire.setTab('edit')">
            Edit Menus
        </button>

        <button type="button" class="px-3 py-2 text-sm border rounded-md"
            :class="tab === 'locations' ? 'bg-white text-gray-900 border-gray-300' : 'bg-gray-50 text-gray-600 border-gray-200'"
            x-on:click="tab='locations'; $wire.setTab('locations')">
            Manage Locations
        </button>

        <button type="button" class="px-3 py-2 text-sm border rounded-md"
            :class="tab === 'create' ? 'bg-white text-gray-900 border-gray-300' : 'bg-gray-50 text-gray-600 border-gray-200'"
            x-on:click="tab='create'; $wire.setTab('create')">
            Create New Menu
        </button>

        <div class="flex-1"></div>

        {{-- Unsaved changes indicator (optional) --}}
        @if ($hasUnsavedChanges)
            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-md">
                You have unsaved changes.
            </div>
        @endif
    </div>

    {{-- ========================================================= --}}
    {{-- TAB: CREATE NEW MENU (with Save button) --}}
    {{-- ========================================================= --}}
    <div x-show="tab === 'create'" x-cloak>
        <div class="border border-gray-200 rounded-md bg-white p-4">
            <div class="text-base font-semibold text-gray-900">Create New Menu</div>
            <div class="text-sm text-gray-500 mt-1">Create a new menu and then edit its structure.</div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:w-80">
                    <div class="text-xs text-gray-500 mb-1">Menu Name</div>
                    <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                        wire:model.defer="newMenuName" placeholder="New menu name...">
                    @error('newMenuName')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <button type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white"
                    wire:click="saveCreateMenu">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7a3 3 0 0 1 3-3h10l3 3v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z" stroke="currentColor"
                            stroke-width="2" />
                        <path d="M8 4v6h8V4" stroke="currentColor" stroke-width="2" />
                        <path d="M8 20v-6h8v6" stroke="currentColor" stroke-width="2" />
                    </svg>
                    Save Menu
                </button>
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- TAB: MANAGE LOCATIONS (with Save button) --}}
    {{-- ========================================================= --}}
    <div x-show="tab === 'locations'" x-cloak>
        <div class="border border-gray-200 rounded-md bg-white p-4">
            <div class="text-base font-semibold text-gray-900">Manage Locations</div>
            <div class="text-sm text-gray-500 mt-1">
                Assign the selected menu to a theme location and click Save.
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-12 items-end">
                {{-- Select menu --}}
                <div class="lg:col-span-5">
                    <div class="text-xs text-gray-500 mb-1">Select a menu to edit</div>
                    <select class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                        wire:change="selectMenu($event.target.value)">
                        @foreach ($menus as $m)
                            <option value="{{ $m->id }}" @selected($activeMenuId === $m->id)>
                                {{ $m->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Location dropdown (DRAFT) --}}
                <div class="lg:col-span-5">
                    <div class="text-xs text-gray-500 mb-1">Menu location</div>
                    <select class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                        wire:model="draftLocationKey" wire:change="setDraftLocation($event.target.value)">
                        <option value="">— Not assigned —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->key }}">{{ $loc->label }} ({{ $loc->key }})</option>
                        @endforeach
                    </select>
                    <div class="text-xs text-gray-500 mt-1">
                        This does not save automatically. Click Save Location.
                    </div>
                </div>

                {{-- Save location --}}
                <div class="lg:col-span-2 flex gap-2">
                    <button type="button"
                        class="inline-flex w-full items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white"
                        wire:click="saveLocationAssignment">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7a3 3 0 0 1 3-3h10l3 3v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z"
                                stroke="currentColor" stroke-width="2" />
                            <path d="M8 4v6h8V4" stroke="currentColor" stroke-width="2" />
                        </svg>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- TAB: EDIT MENUS (WP layout) --}}
    {{-- ========================================================= --}}
    <div x-show="tab === 'edit'" x-cloak class="space-y-4">
        {{-- WP-style header row: select menu --}}
        <div class="border border-gray-200 rounded-md bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="text-sm text-gray-700">
                        Select a menu to edit:
                    </div>

                    <select class="border border-gray-300 rounded-md px-3 py-2 text-sm"
                        wire:change="selectMenu($event.target.value)">
                        @foreach ($menus as $m)
                            <option value="{{ $m->id }}" @selected($activeMenuId === $m->id)>
                                {{ $m->name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="button" class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                        x-on:click="tab='create'; $wire.setTab('create')">
                        create a new menu
                    </button>

                    <button type="button" class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                        x-on:click="tab='locations'; $wire.setTab('locations')">
                        Manage Locations
                    </button>
                </div>

                {{-- menu actions --}}
                <div class="flex flex-wrap gap-2">
                    @if (!$isRenaming)
                        <button type="button" class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                            wire:click="startRename">
                            Rename
                        </button>
                    @endif

                    <button type="button" class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                        wire:click="duplicateActiveMenu">
                        Duplicate
                    </button>

                    <button type="button"
                        class="px-3 py-2 text-sm border border-red-300 rounded-md bg-red-50 text-red-700"
                        x-on:click="if(confirm('Delete this menu and all items?')) $wire.deleteActiveMenu()">
                        Delete
                    </button>
                </div>
            </div>

            @if ($isRenaming)
                <div class="mt-4 border border-gray-200 rounded-md p-3 bg-white">
                    <div class="text-sm font-medium text-gray-900 mb-2">Rename menu</div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="flex-1">
                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                wire:model.defer="renameValue" placeholder="Menu name...">
                            @error('renameValue')
                                <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="button" class="px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white"
                            wire:click="saveRename">
                            Save
                        </button>

                        <button type="button"
                            class="px-4 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-800 border border-gray-300"
                            wire:click="cancelRename">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- MAIN: Two columns like WP --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- LEFT: Add menu items --}}
            <div class="lg:col-span-4">
                <div class="border border-gray-200 rounded-md bg-white">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <div class="text-sm font-semibold text-gray-900">Add menu items</div>
                    </div>

                    <div class="p-4 space-y-3">
                        {{-- Pages --}}
                        <details open class="border border-gray-200 rounded-md">
                            <summary
                                class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-900 flex items-center justify-between">
                                <span>Pages</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    aria-hidden="true">
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>

                            <div class="px-3 pb-3 space-y-2">
                                <input type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                    wire:model.live.debounce.400ms="searchPages" placeholder="Search pages...">

                                <div class="max-h-56 overflow-auto border border-gray-200 rounded-md p-2">
                                    @foreach ($pages as $p)
                                        <label class="flex items-center gap-2 py-1 text-sm text-gray-700">
                                            <input type="checkbox" wire:model="selectedPageIds"
                                                value="{{ $p->id }}">
                                            <span>{{ $p->title ?? ($p->name ?? '#' . $p->id) }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <button type="button"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                                    wire:click="addSelectedPages">
                                    Add to Menu
                                </button>
                            </div>
                        </details>

                        {{-- Posts --}}
                        <details class="border border-gray-200 rounded-md">
                            <summary
                                class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-900 flex items-center justify-between">
                                <span>Posts</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    aria-hidden="true">
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>

                            <div class="px-3 pb-3 space-y-2">
                                <input type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                    wire:model.live.debounce.400ms="searchPosts" placeholder="Search posts...">

                                <div class="max-h-56 overflow-auto border border-gray-200 rounded-md p-2">
                                    @foreach ($posts as $p)
                                        <label class="flex items-center gap-2 py-1 text-sm text-gray-700">
                                            <input type="checkbox" wire:model="selectedPostIds"
                                                value="{{ $p->id }}">
                                            <span>{{ $p->title ?? ($p->name ?? '#' . $p->id) }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <button type="button"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                                    wire:click="addSelectedPosts">
                                    Add to Menu
                                </button>
                            </div>
                        </details>

                        {{-- Categories / Terms --}}
                        <details class="border border-gray-200 rounded-md">
                            <summary
                                class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-900 flex items-center justify-between">
                                <span>Categories / Terms</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    aria-hidden="true">
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>

                            <div class="px-3 pb-3 space-y-2">
                                <input type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                    wire:model.live.debounce.400ms="searchTerms" placeholder="Search terms...">

                                <div class="max-h-56 overflow-auto border border-gray-200 rounded-md p-2">
                                    @foreach ($terms as $t)
                                        <label class="flex items-center gap-2 py-1 text-sm text-gray-700">
                                            <input type="checkbox" wire:model="selectedTermIds"
                                                value="{{ $t->id }}">
                                            <span>{{ $t->name }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <button type="button"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                                    wire:click="addSelectedTerms">
                                    Add to Menu
                                </button>
                            </div>
                        </details>

                        {{-- Custom link --}}
                        <details class="border border-gray-200 rounded-md">
                            <summary
                                class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-900 flex items-center justify-between">
                                <span>Custom Links</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    aria-hidden="true">
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>

                            <div class="px-3 pb-3 space-y-2">
                                <input type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                    wire:model.defer="customLabel" placeholder="Label">
                                <input type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                    wire:model.defer="customUrl" placeholder="https://example.com">

                                <button type="button"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50"
                                    wire:click="addCustomLink">
                                    Add to Menu
                                </button>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Menu Structure --}}
            <div class="lg:col-span-8">
                <div class="border border-gray-200 rounded-md bg-white">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Menu structure</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Drag each item into the order you prefer. Drag under another item to make it a sub-item.
                            </div>
                        </div>

                        {{-- TOP SAVE BUTTON (always enabled) --}}
                        <button type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white"
                            wire:click="saveMenu">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                aria-hidden="true">
                                <path d="M4 7a3 3 0 0 1 3-3h10l3 3v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z"
                                    stroke="currentColor" stroke-width="2" />
                                <path d="M8 4v6h8V4" stroke="currentColor" stroke-width="2" />
                            </svg>
                            Save Menu
                        </button>
                    </div>

                    <div class="p-4">
                        <div id="menu-tree-root">
                            <ul data-menu-ul="1" data-root-ul="1" class="space-y-2">
                                @foreach ($tree as $node)
                                    @include('livewire.partials.menu-tree-wp', [
                                        'node' => $node,
                                        'items' => $items,
                                        'collapsed' => $collapsed,
                                        'level' => 0,
                                    ])
                                @endforeach
                            </ul>
                        </div>

                        {{-- BOTTOM SAVE BUTTON (always enabled) --}}
                        <div class="mt-4">
                            <button type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white"
                                wire:click="saveMenu">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    aria-hidden="true">
                                    <path d="M4 7a3 3 0 0 1 3-3h10l3 3v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z"
                                        stroke="currentColor" stroke-width="2" />
                                    <path d="M8 4v6h8V4" stroke="currentColor" stroke-width="2" />
                                </svg>
                                Save Menu
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sortable script (kept, but now improved: whole-row draggable + prevent accidental nesting + prevent snap-back) --}}
        @once
            @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
                <script>
                    function directLis(ul) {
                        return Array.from(ul.children).filter(el => el.matches('li[data-id]'));
                    }

                    function directChildUl(li) {
                        return Array.from(li.children).find(el => el.matches('ul[data-children-ul="1"]')) || null;
                    }

                    function buildTreeFromDom(ul) {
                        return directLis(ul).map(li => {
                            const id = parseInt(li.dataset.id, 10);
                            const childUl = directChildUl(li);
                            return {
                                id,
                                children: childUl ? buildTreeFromDom(childUl) : []
                            };
                        });
                    }

                    // prevent re-init while dragging (stops snap-back)
                    let __menuDragging = false;
                    let __reorderTimer = null;

                    function initSortables() {
                        if (typeof Sortable === 'undefined') return;

                        document.querySelectorAll('ul[data-menu-ul]').forEach((ul) => {
                            if (ul._sortable) {
                                ul._sortable.destroy();
                                ul._sortable = null;
                            }

                            ul._sortable = new Sortable(ul, {
                                group: 'menu-tree',

                                // ✅ WP-style: whole row draggable (not just a handle)
                                // We avoid dragging from inputs/buttons via filter
                                filter: 'input, textarea, select, button, a, [data-no-drag]',
                                preventOnFilter: true,

                                animation: 150,
                                fallbackOnBody: true,
                                swapThreshold: 0.65,
                                direction: 'vertical',
                                emptyInsertThreshold: 16,

                                // ✅ reduce accidental drags
                                delay: 80,
                                delayOnTouchOnly: true,

                                onStart: () => {
                                    __menuDragging = true;
                                },

                                // ✅ prevent accidental nesting unless user drags to the right
                                onMove: (evt, originalEvent) => {
                                    const toUl = evt.to;
                                    const isChildrenUl = toUl && toUl.dataset && toUl.dataset.childrenUl === '1';
                                    if (!isChildrenUl) return true;

                                    const parentLi = toUl.closest('li[data-id]');
                                    if (!parentLi) return true;

                                    // This selector should match your item "row" wrapper inside the LI.
                                    // Add data-row="1" to that row in menu-tree-wp.blade.php for best accuracy.
                                    const row = parentLi.querySelector('[data-row="1"]') || parentLi.querySelector(
                                        'div.border');
                                    if (!row) return true;

                                    const rect = row.getBoundingClientRect();
                                    const threshold = rect.left + 40; // adjust 30-60px as you like
                                    return originalEvent.clientX >= threshold;
                                },

                                onEnd: () => {
                                    __menuDragging = false;

                                    const root = document.querySelector(
                                        '#menu-tree-root > ul[data-menu-ul][data-root-ul="1"]');
                                    if (!root) return;

                                    const tree = buildTreeFromDom(root);

                                    // ✅ debounce reorder so Livewire doesn't thrash DOM
                                    if (__reorderTimer) clearTimeout(__reorderTimer);
                                    __reorderTimer = setTimeout(() => {
                                        @this.reorder(tree);
                                    }, 50);
                                },
                            });
                        });
                    }

                    document.addEventListener('livewire:init', () => {
                        initSortables();

                        Livewire.on('menu-builder-init', () => {
                            // don't rebuild sortable mid-drag
                            if (__menuDragging) return;
                            setTimeout(initSortables, 0);
                        });

                        if (Livewire.hook) {
                            Livewire.hook('morph.updated', () => {
                                // don't rebuild sortable mid-drag
                                if (__menuDragging) return;
                                setTimeout(initSortables, 0);
                            });
                        }
                    });
                </script>
            @endpush
        @endonce
    </div>
</div>
