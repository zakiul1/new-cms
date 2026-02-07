<div class="space-y-6">
    {{-- TOP: Menu Settings (full width) --}}
    <x-filament::section>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <div class="text-lg font-semibold text-slate-900 dark:text-gray-100">Menu Settings</div>
                <div class="text-sm text-slate-500 dark:text-gray-400">
                    Create menus, rename, duplicate, and manage structure below.
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:w-72">
                    <div class="mb-1 text-xs text-slate-500 dark:text-gray-400">Create new menu</div>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model.defer="newMenuName" placeholder="New menu name..." />
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button wire:click="createMenu">
                    Create
                </x-filament::button>
            </div>
        </div>

        <div class="my-5 border-t border-slate-200/70 dark:border-gray-700"></div>

        {{-- ✅ Menu Select + ✅ Location Assign --}}
        <div class="grid grid-cols-1 gap-4 items-end lg:grid-cols-12">
            {{-- Select menu --}}
            <div class="lg:col-span-5">
                <div class="mb-1 text-xs text-slate-500 dark:text-gray-400">Select menu to edit</div>
                <x-filament::input.wrapper>
                    <select class="fi-input w-full" wire:change="selectMenu($event.target.value)">
                        @foreach ($menus as $m)
                            <option value="{{ $m->id }}" @selected($activeMenuId === $m->id)>
                                {{ $m->name }}
                            </option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </div>

            {{-- ✅ Location dropdown --}}
            <div class="lg:col-span-4">
                <div class="mb-1 text-xs text-slate-500 dark:text-gray-400">Menu location</div>

                <x-filament::input.wrapper>
                    <select class="fi-input w-full" wire:model="activeLocationKey"
                        wire:change="assignLocation($event.target.value)">
                        <option value="">— Not assigned —</option>

                        @foreach ($locations as $loc)
                            <option value="{{ $loc->key }}">
                                {{ $loc->label }} ({{ $loc->key }})
                            </option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>

                <div class="mt-1 text-xs text-slate-400 dark:text-gray-500">
                    Assign this menu to a theme location (ex: primary, footer).
                </div>
            </div>

            {{-- Actions --}}
            <div class="lg:col-span-3 flex flex-wrap gap-2 justify-start lg:justify-end">
                @if (!$isRenaming)
                    <x-filament::button color="gray" wire:click="startRename">
                        Rename
                    </x-filament::button>
                @endif

                <x-filament::button color="gray" wire:click="duplicateActiveMenu">
                    Duplicate
                </x-filament::button>

                <x-filament::button color="danger"
                    x-on:click="if(confirm('Delete this menu and all items?')) $wire.deleteActiveMenu()">
                    Delete
                </x-filament::button>
            </div>
        </div>

        @if ($isRenaming)
            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-2 text-sm font-medium text-slate-900 dark:text-gray-100">Rename menu</div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="renameValue" placeholder="Menu name..." />
                        </x-filament::input.wrapper>
                    </div>

                    <x-filament::button wire:click="saveRename">Save</x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelRename">Cancel</x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- MAIN: Two panels --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {{-- LEFT: Add items --}}
        <div class="space-y-4 lg:col-span-4">
            <x-filament::section>
                <div class="text-lg font-semibold text-slate-900 dark:text-gray-100">Add menu items</div>
                <div class="text-sm text-slate-500 dark:text-gray-400">Select items and add them to the menu.</div>

                <div class="my-4 border-t border-slate-200/70 dark:border-gray-700"></div>

                <div class="space-y-3">
                    <details open
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <summary class="cursor-pointer font-medium text-slate-900 dark:text-gray-100">Pages</summary>
                        <div class="mt-3 space-y-2">
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.live.debounce.500ms="searchPages"
                                    placeholder="Search pages..." />
                            </x-filament::input.wrapper>

                            <div
                                class="max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                                @foreach ($pages as $p)
                                    <label class="flex items-center gap-2 py-1">
                                        <input type="checkbox" wire:model="selectedPageIds" value="{{ $p->id }}">
                                        <span class="text-sm text-slate-700 dark:text-gray-200">
                                            {{ $p->title ?? ($p->name ?? '#' . $p->id) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-filament::button size="sm" wire:click="addSelectedPages">
                                Add to Menu
                            </x-filament::button>
                        </div>
                    </details>

                    <details
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <summary class="cursor-pointer font-medium text-slate-900 dark:text-gray-100">Posts</summary>
                        <div class="mt-3 space-y-2">
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.live.debounce.500ms="searchPosts"
                                    placeholder="Search posts..." />
                            </x-filament::input.wrapper>

                            <div
                                class="max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                                @foreach ($posts as $p)
                                    <label class="flex items-center gap-2 py-1">
                                        <input type="checkbox" wire:model="selectedPostIds" value="{{ $p->id }}">
                                        <span class="text-sm text-slate-700 dark:text-gray-200">
                                            {{ $p->title ?? ($p->name ?? '#' . $p->id) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-filament::button size="sm" wire:click="addSelectedPosts">
                                Add to Menu
                            </x-filament::button>
                        </div>
                    </details>

                    <details
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <summary class="cursor-pointer font-medium text-slate-900 dark:text-gray-100">Categories / Terms
                        </summary>
                        <div class="mt-3 space-y-2">
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.live.debounce.500ms="searchTerms"
                                    placeholder="Search terms..." />
                            </x-filament::input.wrapper>

                            <div
                                class="max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                                @foreach ($terms as $t)
                                    <label class="flex items-center gap-2 py-1">
                                        <input type="checkbox" wire:model="selectedTermIds" value="{{ $t->id }}">
                                        <span
                                            class="text-sm text-slate-700 dark:text-gray-200">{{ $t->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <x-filament::button size="sm" wire:click="addSelectedTerms">
                                Add to Menu
                            </x-filament::button>
                        </div>
                    </details>

                    <details
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <summary class="cursor-pointer font-medium text-slate-900 dark:text-gray-100">Custom Link
                        </summary>
                        <div class="mt-3 space-y-2">
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.defer="customLabel" placeholder="Label" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input wire:model.defer="customUrl" placeholder="https://example.com" />
                            </x-filament::input.wrapper>

                            <x-filament::button size="sm" wire:click="addCustomLink">
                                Add to Menu
                            </x-filament::button>
                        </div>
                    </details>
                </div>
            </x-filament::section>
        </div>

        {{-- RIGHT: Menu structure --}}
        <div class="space-y-4 lg:col-span-8">
            <x-filament::section>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold text-slate-900 dark:text-gray-100">Menu Structure</div>
                        <div class="text-sm text-slate-500 dark:text-gray-400">
                            Drag & drop to reorder or nest items.
                            <span class="ml-2 text-xs text-slate-400 dark:text-gray-500">(Drop onto the indented
                                dropzone to nest)</span>
                        </div>
                    </div>
                    <div class="text-sm text-slate-500 dark:text-gray-400">
                        Auto-save: <span class="font-medium text-slate-900 dark:text-gray-200">ON</span>
                    </div>
                </div>

                <div class="my-4 border-t border-slate-200/70 dark:border-gray-700"></div>

                <div id="menu-tree-root" class="space-y-2">
                    @include('livewire.partials.menu-tree', [
                        'nodes' => $tree,
                        'collapsed' => $collapsed,
                        'items' => $items,
                        'savedAt' => $savedAt,
                    ])
                </div>
            </x-filament::section>
        </div>
    </div>

    {{-- keep your scripts section same --}}
    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
            <script>
                // ---- helpers that do NOT use :scope (stable) ----
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

                function initSortables() {
                    if (typeof Sortable === 'undefined') return;

                    document.querySelectorAll('ul[data-menu-ul]').forEach((ul) => {
                        // destroy old instance if exists (Livewire rerenders)
                        if (ul._sortable) {
                            ul._sortable.destroy();
                            ul._sortable = null;
                        }

                        ul._sortable = new Sortable(ul, {
                            group: 'menu-tree',
                            handle: '[data-drag-handle]',
                            animation: 150,
                            fallbackOnBody: true,
                            swapThreshold: 0.65,
                            direction: 'vertical',

                            // allows dropping into empty children ULs
                            emptyInsertThreshold: 16,

                            onEnd: () => {
                                const root = document.querySelector('#menu-tree-root > ul[data-menu-ul]');
                                if (!root) return;

                                const tree = buildTreeFromDom(root);
                                @this.reorder(tree);
                            },
                        });
                    });
                }

                document.addEventListener('livewire:init', () => {
                    initSortables();

                    // your own event from reload()
                    Livewire.on('menu-builder-init', () => {
                        setTimeout(initSortables, 0);
                    });

                    // Livewire v3 DOM morph updates
                    if (Livewire.hook) {
                        Livewire.hook('morph.updated', () => {
                            setTimeout(initSortables, 0);
                        });
                    }
                });
            </script>
        @endpush
    @endonce
</div>
