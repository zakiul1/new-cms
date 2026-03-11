<div class="space-y-4" x-data="{ tab: @entangle('activeTab').live }">
    {{-- Toasts --}}
    <div x-data="{
        toasts: [],
        push(t) {
            const payload = Array.isArray(t) ? t[0] : t;
            const id = Date.now() + Math.random();
            this.toasts.push({ id, ...(payload || {}) });
            const ttl = payload?.timeout ?? 2500;
            if (ttl > 0) setTimeout(() => this.remove(id), ttl);
        },
        remove(id) {
            this.toasts = this.toasts.filter(x => x.id !== id);
        }
    }" x-on:toast.window="push($event.detail)"
        class="fixed bottom-5 right-5 z-50 w-[340px] max-w-[90vw] space-y-2">
        <template x-for="t in toasts" :key="t.id">
            <div class="flex items-start gap-3 rounded-md border bg-white px-4 py-3 shadow"
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

                    <template x-if="!['success', 'error', 'warning'].includes(t.type)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 8h.01M12 12v4M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900" x-text="t.title ?? 'Changed'"></div>
                    <div class="mt-0.5 text-sm text-gray-600" x-text="t.message ?? ''"></div>
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

    {{-- Top tabs --}}
    <div class="flex items-center gap-2">
        <button type="button" class="rounded-md border px-3 py-2 text-sm"
            :class="tab === 'edit' ? 'border-gray-300 bg-white text-gray-900' : 'border-gray-200 bg-gray-50 text-gray-600'"
            x-on:click="tab='edit'; $wire.openEditMenusPanel()">
            Edit Menus
        </button>

        <button type="button" class="rounded-md border px-3 py-2 text-sm"
            :class="tab === 'locations' ? 'border-gray-300 bg-white text-gray-900' : 'border-gray-200 bg-gray-50 text-gray-600'"
            x-on:click="tab='locations'; $wire.openManageLocationsPanel()">
            Manage Locations
        </button>

        <button type="button" class="rounded-md border px-3 py-2 text-sm"
            :class="tab === 'create' ? 'border-gray-300 bg-white text-gray-900' : 'border-gray-200 bg-gray-50 text-gray-600'"
            x-on:click="tab='create'; $wire.openCreateMenuPanel()">
            Create New Menu
        </button>

        <div class="flex-1"></div>

        @if ($hasUnsavedChanges)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                You have unsaved changes.
            </div>
        @endif
    </div>

    {{-- Create tab --}}
    <div x-show="tab === 'create'" x-cloak>
        <div class="rounded-md border border-gray-200 bg-white p-4">
            <div class="text-base font-semibold text-gray-900">Create New Menu</div>
            <div class="mt-1 text-sm text-gray-500">Create a new menu and then edit its structure.</div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:w-80">
                    <div class="mb-1 text-xs text-gray-500">Menu Name</div>
                    <input type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        wire:model.defer="newMenuName" placeholder="New menu name...">
                    @error('newMenuName')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <button type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white"
                    wire:click="saveCreateMenu">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7a3 3 0 0 1 3-3h10l3 3v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z" stroke="currentColor"
                            stroke-width="2" />
                        <path d="M8 4v6h8V4" stroke="currentColor" stroke-width="2" />
                        <path d="M8 20v-6h8v6" stroke="currentColor" stroke-width="2" />
                    </svg>
                    Save Menu
                </button>

                <button type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700"
                    wire:click="cancelCreateMenu">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- Locations tab --}}
    <div x-show="tab === 'locations'" x-cloak>
        <div class="rounded-md border border-gray-200 bg-white p-4">
            <div class="text-base font-semibold text-gray-900">Manage Locations</div>
            <div class="mt-1 text-sm text-gray-500">
                Assign the selected menu to a theme location and click Save.
            </div>

            <div class="mt-4 grid grid-cols-1 items-end gap-4 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <div class="mb-1 text-xs text-gray-500">Select a menu to edit</div>

                    @if ($menus->count())
                        <select class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            wire:change="selectMenuAndEdit($event.target.value)">
                            @foreach ($menus as $m)
                                <option value="{{ $m->id }}" @selected($activeMenuId === $m->id)>
                                    {{ $m->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                            No menus found yet.
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-5">
                    <div class="mb-1 text-xs text-gray-500">Menu location</div>
                    <select class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        wire:model="draftLocationKey" wire:change="setDraftLocation($event.target.value)"
                        @disabled(!$activeMenuId)>
                        <option value="">— Not assigned —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->key }}">{{ $loc->label }} ({{ $loc->key }})</option>
                        @endforeach
                    </select>
                    <div class="mt-1 text-xs text-gray-500">
                        This does not save automatically. Click Save.
                    </div>
                </div>

                <div class="flex gap-2 lg:col-span-2">
                    <button type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                        wire:click="saveLocationAssignment" @disabled(!$activeMenuId)>
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

    {{-- Edit tab --}}
    <div x-show="tab === 'edit'" x-cloak class="space-y-4">
        <div class="rounded-md border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="text-sm text-gray-700">Select a menu to edit:</div>

                    @if ($menus->count())
                        <select class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                            wire:change="selectMenuAndEdit($event.target.value)">
                            @foreach ($menus as $m)
                                <option value="{{ $m->id }}" @selected($activeMenuId === $m->id)>
                                    {{ $m->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                            No menus found yet.
                        </div>
                    @endif

                    <button type="button" class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                        x-on:click="tab='create'; $wire.openCreateMenuPanel()">
                        create a new menu
                    </button>

                    <button type="button" class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                        x-on:click="tab='locations'; $wire.openManageLocationsPanel()">
                        Manage Locations
                    </button>
                </div>

                @if ($activeMenuId)
                    <div class="flex flex-wrap gap-2">
                        @if (!$isRenaming)
                            <button type="button"
                                class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                                wire:click="startRename">
                                Rename
                            </button>
                        @endif

                        <button type="button" class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                            wire:click="duplicateActiveMenu">
                            Duplicate
                        </button>

                        <button type="button"
                            class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700"
                            x-on:click="if(confirm('Delete this menu and all items?')) $wire.deleteActiveMenu()">
                            Delete
                        </button>
                    </div>
                @endif
            </div>

            @if ($isRenaming)
                <div class="mt-4 rounded-md border border-gray-200 bg-white p-3">
                    <div class="mb-2 text-sm font-medium text-gray-900">Rename menu</div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="flex-1">
                            <input type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                wire:model.defer="renameValue" placeholder="Menu name...">
                            @error('renameValue')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white"
                            wire:click="saveRename">
                            Save
                        </button>

                        <button type="button"
                            class="rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-800"
                            wire:click="cancelRename">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif
        </div>

        @if (!$activeMenuId)
            <div class="rounded-md border border-gray-200 bg-white p-6 text-center">
                <div class="text-base font-semibold text-gray-900">No menu selected</div>
                <div class="mt-1 text-sm text-gray-500">Create a new menu to begin.</div>
                <div class="mt-4">
                    <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white"
                        x-on:click="tab='create'; $wire.openCreateMenuPanel()">
                        Create Menu
                    </button>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                {{-- Left column --}}
                <div class="lg:col-span-4">
                    <div class="rounded-md border border-gray-200 bg-white">
                        <div class="border-b border-gray-200 px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">Add menu items</div>
                        </div>

                        <div class="space-y-3 p-4">
                            {{-- Pages --}}
                            <details open class="rounded-md border border-gray-200">
                                <summary
                                    class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm font-medium text-gray-900">
                                    <span>Pages</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        aria-hidden="true">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </summary>

                                <div class="space-y-2 px-3 pb-3">
                                    <input type="text"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        wire:model.live.debounce.400ms="searchPages" placeholder="Search pages...">

                                    <div class="max-h-56 overflow-auto rounded-md border border-gray-200 p-2">
                                        @foreach ($pages as $p)
                                            <label class="flex items-center gap-2 py-1 text-sm text-gray-700">
                                                <input type="checkbox" wire:model="selectedPageIds"
                                                    value="{{ $p->id }}">
                                                <span>{{ $p->title ?? ($p->name ?? '#' . $p->id) }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <button type="button"
                                        class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                                        wire:click="addSelectedPages">
                                        Add to Menu
                                    </button>
                                </div>
                            </details>

                            {{-- Posts --}}
                            <details class="rounded-md border border-gray-200">
                                <summary
                                    class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm font-medium text-gray-900">
                                    <span>Posts</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        aria-hidden="true">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </summary>

                                <div class="space-y-2 px-3 pb-3">
                                    <input type="text"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        wire:model.live.debounce.400ms="searchPosts" placeholder="Search posts...">

                                    <div class="max-h-56 overflow-auto rounded-md border border-gray-200 p-2">
                                        @foreach ($posts as $p)
                                            <label class="flex items-center gap-2 py-1 text-sm text-gray-700">
                                                <input type="checkbox" wire:model="selectedPostIds"
                                                    value="{{ $p->id }}">
                                                <span>{{ $p->title ?? ($p->name ?? '#' . $p->id) }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <button type="button"
                                        class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                                        wire:click="addSelectedPosts">
                                        Add to Menu
                                    </button>
                                </div>
                            </details>

                            {{-- Terms --}}
                            <details class="rounded-md border border-gray-200">
                                <summary
                                    class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm font-medium text-gray-900">
                                    <span>Categories / Terms</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        aria-hidden="true">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </summary>

                                <div class="space-y-2 px-3 pb-3">
                                    <input type="text"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        wire:model.live.debounce.400ms="searchTerms" placeholder="Search terms...">

                                    <div class="max-h-56 overflow-auto rounded-md border border-gray-200 p-2">
                                        @foreach ($terms as $t)
                                            <label class="flex items-center gap-2 py-1 text-sm text-gray-700">
                                                <input type="checkbox" wire:model="selectedTermIds"
                                                    value="{{ $t->id }}">
                                                <span>{{ $t->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <button type="button"
                                        class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                                        wire:click="addSelectedTerms">
                                        Add to Menu
                                    </button>
                                </div>
                            </details>

                            {{-- Custom link --}}
                            <details class="rounded-md border border-gray-200">
                                <summary
                                    class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm font-medium text-gray-900">
                                    <span>Custom Links</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        aria-hidden="true">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </summary>

                                <div class="space-y-2 px-3 pb-3">
                                    <input type="text"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        wire:model.defer="customLabel" placeholder="Label">

                                    <input type="text"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        wire:model.defer="customUrl" placeholder="https://example.com">

                                    <button type="button"
                                        class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm"
                                        wire:click="addCustomLink">
                                        Add to Menu
                                    </button>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                {{-- Right column --}}
                <div class="lg:col-span-8">
                    <div class="rounded-md border border-gray-200 bg-white">
                        <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Menu structure</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    Drag each item into the order you prefer. Drag under another item to make it a
                                    sub-item.
                                </div>
                            </div>

                            <button type="button"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white"
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

                            <div class="mt-4">
                                <button type="button"
                                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white"
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
        @endif

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
                                filter: 'input, textarea, select, button, a, [data-no-drag]',
                                preventOnFilter: true,
                                animation: 150,
                                fallbackOnBody: true,
                                swapThreshold: 0.65,
                                direction: 'vertical',
                                emptyInsertThreshold: 16,
                                delay: 80,
                                delayOnTouchOnly: true,

                                onStart: () => {
                                    __menuDragging = true;
                                },

                                onMove: (evt, originalEvent) => {
                                    const toUl = evt.to;
                                    const isChildrenUl = toUl && toUl.dataset && toUl.dataset.childrenUl === '1';
                                    if (!isChildrenUl) return true;

                                    const parentLi = toUl.closest('li[data-id]');
                                    if (!parentLi) return true;

                                    const row = parentLi.querySelector('[data-row="1"]') || parentLi.querySelector(
                                        'div.border');
                                    if (!row) return true;

                                    const rect = row.getBoundingClientRect();
                                    const threshold = rect.left + 40;
                                    return originalEvent.clientX >= threshold;
                                },

                                onEnd: () => {
                                    __menuDragging = false;

                                    const root = document.querySelector(
                                        '#menu-tree-root > ul[data-menu-ul][data-root-ul="1"]'
                                    );
                                    if (!root) return;

                                    const tree = buildTreeFromDom(root);

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
                            if (__menuDragging) return;
                            setTimeout(initSortables, 0);
                        });

                        if (Livewire.hook) {
                            Livewire.hook('morph.updated', () => {
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
