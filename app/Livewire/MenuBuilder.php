<?php

namespace App\Livewire;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Menus\MenuItemFactory;
use App\Models\Menu;
use App\Models\MenuAssignment;
use App\Models\MenuItem;
use App\Models\MenuLocation;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MenuBuilder extends Component
{
    public ?int $activeMenuId = null;

    /**
     * Tabs for WP-like UI (you will use it in blade):
     * - edit       : Edit Menus (structure + add items)
     * - locations  : Manage Locations
     * - create     : Create New Menu
     */
    public string $activeTab = 'edit';

    // Menu actions UI
    public string $newMenuName = '';
    public bool $isRenaming = false;
    public string $renameValue = '';

    // ✅ Location assignment (current persisted assignment)
    public ?string $activeLocationKey = null;

    // ✅ Location draft (only saved when user clicks Save)
    public ?string $draftLocationKey = null;

    // Left panel search
    public string $searchPosts = '';
    public string $searchPages = '';
    public string $searchTerms = '';

    // Left panel selected IDs
    public array $selectedPostIds = [];
    public array $selectedPageIds = [];
    public array $selectedTermIds = [];

    // Custom link
    public string $customLabel = '';
    public string $customUrl = '';

    // Right side state (DRAFT state now)
    public array $tree = [];       // nested structure (draft)
    public array $items = [];      // editable state by id (draft)
    public array $collapsed = [];  // collapse/expand per item id

    // Unsaved changes flags
    public bool $hasUnsavedChanges = false;
    public bool $structureDirty = false;
    public bool $itemsDirty = false;
    public bool $locationDirty = false;

    // Optional UI helpers
    public ?int $lastSavedAt = null;     // timestamp of last Save Menu
    public ?int $lastSavedAtLocation = null;

    public function mount(): void
    {
        $this->activeMenuId = Menu::query()->orderBy('name')->value('id');

        $this->activeLocationKey = $this->activeMenuId
            ? MenuAssignment::query()->where('menu_id', $this->activeMenuId)->value('location_key')
            : null;

        $this->draftLocationKey = $this->activeLocationKey;

        $this->reload();
    }

    public function render()
    {
        return view('livewire.menu-builder', [
            'menus' => Menu::query()->orderBy('name')->get(),
            'locations' => MenuLocation::query()->orderBy('label')->get(),
            'posts' => $this->queryPosts('post'),
            'pages' => $this->queryPosts('page'),
            'taxonomies' => Taxonomy::query()->orderBy('label')->get(),
            'terms' => $this->queryTerms(),
        ]);
    }

    // -------------------------
    // Toast helper
    // -------------------------
    private function toast(string $type, string $title, string $message, int $timeout = 2500): void
    {
        $this->dispatch('toast', [
            'type' => $type,   // success|error|warning|info
            'title' => $title,
            'message' => $message,
            'timeout' => $timeout,
        ]);
    }

    // -------------------------
    // ✅ Cache invalidation helpers (MENU)
    // -------------------------
    private function bumpMenuLocationCache(?string $locationKey): void
    {
        $locationKey = $locationKey !== null ? trim($locationKey) : null;
        if ($locationKey === null || $locationKey === '') {
            return;
        }

        /** @var CmsCacheVersions $versions */
        $versions = app(CmsCacheVersions::class);
        $versions->bump('menu_location', (string) $locationKey);
    }

    private function bumpAllLocationsForMenu(int $menuId): void
    {
        /** @var CmsCacheVersions $versions */
        $versions = app(CmsCacheVersions::class);

        $keys = MenuAssignment::query()
            ->where('menu_id', $menuId)
            ->pluck('location_key')
            ->unique()
            ->filter(fn($k) => is_string($k) && trim($k) !== '');

        foreach ($keys as $k) {
            $versions->bump('menu_location', (string) $k);
        }
    }

    // -------------------------
    // Tabs
    // -------------------------
    public function setTab(string $tab): void
    {
        $allowed = ['edit', 'locations', 'create'];
        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'edit';
    }

    // -------------------------
    // Menu header actions
    // -------------------------
    public function selectMenu(int $menuId): void
    {
        $this->activeMenuId = $menuId;
        $this->isRenaming = false;

        $this->activeLocationKey = MenuAssignment::query()
            ->where('menu_id', $this->activeMenuId)
            ->value('location_key');

        $this->draftLocationKey = $this->activeLocationKey;

        $this->reload();
        $this->toast('info', 'Menu selected', 'You are now editing a different menu.');
    }

    public function createMenu(): void
    {
        $this->saveCreateMenu();
    }

    public function saveCreateMenu(): void
    {
        $name = trim($this->newMenuName);
        if ($name === '') {
            $this->addError('newMenuName', 'Menu name is required.');
            $this->toast('error', 'Validation error', 'Menu name is required.', 4000);
            return;
        }

        $menu = Menu::query()->create(['name' => $name]);

        $this->newMenuName = '';
        $this->activeMenuId = (int) $menu->id;

        $this->activeLocationKey = null;
        $this->draftLocationKey = null;

        $this->reload();
        $this->setTab('edit');

        $this->toast('success', 'Menu created', 'New menu created successfully.');
    }

    public function startRename(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $menu = Menu::query()->find($this->activeMenuId);
        $this->renameValue = $menu?->name ?? '';
        $this->isRenaming = true;
    }

    public function cancelRename(): void
    {
        $this->isRenaming = false;
        $this->renameValue = '';
    }

    public function saveRename(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $name = trim($this->renameValue);
        if ($name === '') {
            $this->addError('renameValue', 'Menu name is required.');
            $this->toast('error', 'Validation error', 'Menu name is required.', 4000);
            return;
        }

        Menu::query()->whereKey($this->activeMenuId)->update(['name' => $name]);
        $this->isRenaming = false;
        $this->reload();

        $this->toast('success', 'Menu renamed', 'Menu name updated successfully.');
    }

    public function duplicateActiveMenu(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        DB::transaction(function () {
            $menu = Menu::query()->lockForUpdate()->find($this->activeMenuId);
            if (!$menu) {
                return;
            }

            $new = Menu::query()->create([
                'name' => $menu->name . ' (Copy)',
            ]);

            $items = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->orderBy('id')
                ->get();

            $idMap = [];

            foreach ($items as $item) {
                $clone = $item->replicate(['menu_id', 'parent_id', 'sort_order']);
                $clone->menu_id = $new->id;
                $clone->parent_id = null;
                $clone->save();

                $idMap[$item->id] = $clone->id;
            }

            foreach ($items as $item) {
                $newId = $idMap[$item->id] ?? null;
                if (!$newId) {
                    continue;
                }

                $newParent = $item->parent_id ? ($idMap[$item->parent_id] ?? null) : null;

                MenuItem::query()->whereKey($newId)->update([
                    'parent_id' => $newParent,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $this->activeMenuId = (int) $new->id;

            $this->activeLocationKey = null;
            $this->draftLocationKey = null;
        });

        $this->reload();
        $this->toast('success', 'Menu duplicated', 'A copy of the menu has been created.');
    }

    public function deleteActiveMenu(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $menuId = (int) $this->activeMenuId;
        $oldLocationKeys = MenuAssignment::query()
            ->where('menu_id', $menuId)
            ->pluck('location_key')
            ->unique()
            ->all();

        DB::transaction(function () use ($menuId) {
            MenuAssignment::query()->where('menu_id', $menuId)->delete();
            MenuItem::query()->where('menu_id', $menuId)->delete();
            Menu::query()->whereKey($menuId)->delete();
        });

        // Bust cache for any locations that were pointing to this menu
        foreach ($oldLocationKeys as $k) {
            $this->bumpMenuLocationCache(is_string($k) ? $k : null);
        }

        $this->activeMenuId = Menu::query()->orderBy('name')->value('id');

        $this->activeLocationKey = $this->activeMenuId
            ? MenuAssignment::query()->where('menu_id', $this->activeMenuId)->value('location_key')
            : null;

        $this->draftLocationKey = $this->activeLocationKey;

        $this->reload();
        $this->toast('success', 'Menu deleted', 'Menu has been deleted.');
    }

    // -------------------------
    // ✅ Location assignment (DRAFT + SAVE)
    // -------------------------
    public function setDraftLocation(?string $locationKey): void
    {
        $locationKey = $locationKey !== null ? trim($locationKey) : null;
        $locationKey = ($locationKey === '') ? null : $locationKey;

        $this->draftLocationKey = $locationKey;

        $this->locationDirty = ($this->draftLocationKey !== $this->activeLocationKey);
        $this->syncUnsavedFlag();
    }

    public function saveLocationAssignment(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        if (!$this->locationDirty) {
            $this->toast('info', 'No changes', 'Nothing to save for menu location.');
            return;
        }

        $oldLocationKey = $this->activeLocationKey;
        $locationKey = $this->draftLocationKey;

        if ($locationKey === null) {
            MenuAssignment::query()
                ->where('menu_id', $this->activeMenuId)
                ->delete();

            $this->activeLocationKey = null;
            $this->draftLocationKey = null;

            $this->locationDirty = false;
            $this->lastSavedAtLocation = time();
            $this->syncUnsavedFlag();

            // Bust cache for old location (it was unassigned)
            $this->bumpMenuLocationCache($oldLocationKey);

            $this->toast('success', 'Location saved', 'Menu location has been unassigned.');
            return;
        }

        DB::transaction(function () use ($locationKey) {
            MenuAssignment::query()
                ->where('menu_id', $this->activeMenuId)
                ->where('location_key', '!=', $locationKey)
                ->delete();

            MenuAssignment::query()->updateOrCreate(
                ['location_key' => $locationKey],
                ['menu_id' => $this->activeMenuId],
            );
        });

        $this->activeLocationKey = $locationKey;
        $this->draftLocationKey = $locationKey;

        $this->locationDirty = false;
        $this->lastSavedAtLocation = time();
        $this->syncUnsavedFlag();

        // Bust cache for both old + new location keys (covers re-assign)
        $this->bumpMenuLocationCache($oldLocationKey);
        $this->bumpMenuLocationCache($locationKey);

        $this->toast('success', 'Location saved', 'Menu location assignment saved.');
    }

    // -------------------------
    // Left panel add actions
    // -------------------------
    public function addSelectedPosts(): void
    {
        $this->addPostsByIds($this->selectedPostIds);
        $this->selectedPostIds = [];
    }

    public function addSelectedPages(): void
    {
        $this->addPostsByIds($this->selectedPageIds);
        $this->selectedPageIds = [];
    }

    public function addSelectedTerms(?string $taxonomyKey = null): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $this->selectedTermIds), fn($v) => $v > 0));
        if ($ids === []) {
            $this->toast('warning', 'No selection', 'Please select at least one term to add.');
            return;
        }

        $factory = app(MenuItemFactory::class);
        $terms = Term::query()->whereIn('id', $ids)->get();

        DB::transaction(function () use ($terms, $taxonomyKey, $factory) {
            $max = (int) MenuItem::query()
                ->where('menu_id', $this->activeMenuId)
                ->whereNull('parent_id')
                ->max('sort_order');

            $sort = $max + 1;

            foreach ($terms as $term) {
                $data = $factory->fromTerm($term, $taxonomyKey);
                $data['menu_id'] = $this->activeMenuId;
                $data['sort_order'] = $sort++;
                MenuItem::query()->create($data);
            }
        });

        // Bust cache for locations that use this menu
        $this->bumpAllLocationsForMenu((int) $this->activeMenuId);

        $this->selectedTermIds = [];
        $this->reload();

        $this->toast('success', 'Items added', 'Selected terms were added to the menu.');
    }

    public function addCustomLink(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $label = trim($this->customLabel);
        $url = trim($this->customUrl);

        if ($label === '' || $url === '') {
            $this->addError('customLabel', 'Label and URL are required.');
            $this->toast('error', 'Validation error', 'Label and URL are required.', 4000);
            return;
        }

        $factory = app(MenuItemFactory::class);

        DB::transaction(function () use ($factory, $label, $url) {
            $max = (int) MenuItem::query()
                ->where('menu_id', $this->activeMenuId)
                ->whereNull('parent_id')
                ->max('sort_order');

            $data = $factory->fromCustomLink($label, $url);
            $data['menu_id'] = $this->activeMenuId;
            $data['sort_order'] = $max + 1;

            MenuItem::query()->create($data);
        });

        // Bust cache for locations that use this menu
        $this->bumpAllLocationsForMenu((int) $this->activeMenuId);

        $this->customLabel = '';
        $this->customUrl = '';
        $this->reload();

        $this->toast('success', 'Item added', 'Custom link has been added to the menu.');
    }

    // -------------------------
    // Right side actions
    // -------------------------
    public function toggleCollapse(int $id): void
    {
        // default should be collapsed = true
        $current = (bool) ($this->collapsed[$id] ?? true);
        $this->collapsed[$id] = !$current;
    }

    public function removeItem(int $id): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        DB::transaction(function () use ($id) {
            $this->deleteItemRecursive($id);
        });

        // Bust cache for locations that use this menu
        $this->bumpAllLocationsForMenu((int) $this->activeMenuId);

        // Keep UI consistent
        $this->structureDirty = true;
        $this->itemsDirty = true;
        $this->syncUnsavedFlag();

        $this->reload();

        $this->toast('success', 'Item removed', 'Menu item has been removed.');
    }

    public function markItemsDirty(): void
    {
        $this->itemsDirty = true;
        $this->syncUnsavedFlag();
    }

    public function reorder(array $tree): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $this->tree = $tree;
        $this->structureDirty = true;
        $this->syncUnsavedFlag();

        $this->toast('info', 'Structure updated', 'New order applied. Click “Save Menu” to save.');
    }

    public function saveMenu(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        if (!$this->hasUnsavedChanges) {
            $this->toast('info', 'No changes', 'There is nothing new to save.');
            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->items as $id => $row) {
                    $id = (int) $id;
                    if ($id <= 0 || !is_array($row)) {
                        continue;
                    }

                    MenuItem::query()
                        ->where('menu_id', $this->activeMenuId)
                        ->whereKey($id)
                        ->update([
                            'label' => $row['label'] ?? null,
                            'url' => $row['url'] ?? null,
                            'is_enabled' => (bool) ($row['is_enabled'] ?? true),

                            'target' => $row['target'] ?? null,
                            'rel' => $row['rel'] ?? null,
                            'css_class' => $row['css_class'] ?? null,
                            'css_id' => $row['css_id'] ?? null,
                            'icon' => $row['icon'] ?? null,
                            'description' => $row['description'] ?? null,

                            'visibility' => $row['visibility'] ?? null,
                            'data' => $row['data'] ?? null,
                        ]);
                }

                if ($this->structureDirty) {
                    $this->persistTree($this->tree, null);
                }
            });
        } catch (\Throwable $e) {
            $this->toast('error', 'Save failed', 'Could not save menu. Please try again.', 4500);
            throw $e;
        }

        // ✅ Bust cache for all locations that use this menu
        $this->bumpAllLocationsForMenu((int) $this->activeMenuId);

        $this->lastSavedAt = time();
        $this->structureDirty = false;
        $this->itemsDirty = false;
        $this->syncUnsavedFlag();

        $this->reload();

        $this->toast('success', 'Menu saved', 'Your menu changes have been saved.');
    }

    // -------------------------
    // Internals
    // -------------------------
    private function reload(): void
    {
        $this->tree = $this->buildTree();
        $this->items = $this->buildItemsState();

        // ✅ Default should be COLLAPSED (true)
        foreach (array_keys($this->items) as $id) {
            $this->collapsed[$id] = $this->collapsed[$id] ?? true;
        }

        // reset dirty flags after reload
        $this->hasUnsavedChanges = false;
        $this->structureDirty = false;
        $this->itemsDirty = false;
        $this->locationDirty = ($this->draftLocationKey !== $this->activeLocationKey);

        $this->dispatch('menu-builder-init');
    }

    private function syncUnsavedFlag(): void
    {
        $this->hasUnsavedChanges = ($this->structureDirty || $this->itemsDirty || $this->locationDirty);
    }

    private function buildItemsState(): array
    {
        if (!$this->activeMenuId) {
            return [];
        }

        $rows = MenuItem::query()
            ->where('menu_id', $this->activeMenuId)
            ->orderByRaw('COALESCE(parent_id, 0), sort_order asc')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r->id] = [
                'label' => (string) ($r->label ?? ''),
                'url' => (string) ($r->url ?? ''),
                'is_enabled' => (bool) $r->is_enabled,

                'target' => $r->target,
                'rel' => $r->rel,
                'css_class' => $r->css_class,
                'css_id' => $r->css_id,
                'icon' => $r->icon,
                'description' => $r->description,

                'visibility' => is_array($r->visibility) ? $r->visibility : [],
                'data' => is_array($r->data) ? $r->data : [],
            ];
        }

        return $out;
    }

    private function buildTree(): array
    {
        if (!$this->activeMenuId) {
            return [];
        }

        $items = MenuItem::query()
            ->where('menu_id', $this->activeMenuId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn(MenuItem $i) => $i->parent_id ?: 0);

        $build = function (int $parentId) use (&$build, $items): array {
            $children = $items->get($parentId, collect());
            $out = [];

            foreach ($children as $child) {
                $out[] = [
                    'id' => (int) $child->id,
                    'children' => $build((int) $child->id),
                ];
            }

            return $out;
        };

        return $build(0);
    }

    private function persistTree(array $nodes, ?int $parentId): void
    {
        $sort = 1;

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $id = (int) ($node['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $exists = MenuItem::query()
                ->where('menu_id', $this->activeMenuId)
                ->whereKey($id)
                ->exists();

            if (!$exists) {
                continue;
            }

            MenuItem::query()
                ->where('menu_id', $this->activeMenuId)
                ->whereKey($id)
                ->update([
                    'parent_id' => $parentId,
                    'sort_order' => $sort++,
                ]);

            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->persistTree($children, $id);
            }
        }
    }

    private function deleteItemRecursive(int $id): void
    {
        $children = MenuItem::query()
            ->where('menu_id', $this->activeMenuId)
            ->where('parent_id', $id)
            ->pluck('id')
            ->all();

        foreach ($children as $childId) {
            $this->deleteItemRecursive((int) $childId);
        }

        MenuItem::query()
            ->where('menu_id', $this->activeMenuId)
            ->whereKey($id)
            ->delete();
    }

    private function addPostsByIds(array $ids): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if ($ids === []) {
            $this->toast('warning', 'No selection', 'Please select at least one item to add.');
            return;
        }

        $factory = app(MenuItemFactory::class);
        $posts = Post::query()->whereIn('id', $ids)->get();

        DB::transaction(function () use ($posts, $factory) {
            $max = (int) MenuItem::query()
                ->where('menu_id', $this->activeMenuId)
                ->whereNull('parent_id')
                ->max('sort_order');

            $sort = $max + 1;

            foreach ($posts as $post) {
                $data = $factory->fromPost($post);
                $data['menu_id'] = $this->activeMenuId;
                $data['sort_order'] = $sort++;
                MenuItem::query()->create($data);
            }
        });

        // Bust cache for locations that use this menu
        $this->bumpAllLocationsForMenu((int) $this->activeMenuId);

        $this->reload();
        $this->toast('success', 'Items added', 'Selected items were added to the menu.');
    }

    private function queryPosts(string $want)
    {
        $factory = app(MenuItemFactory::class);
        $col = $factory->detectPostTypeColumn();

        $q = Post::query()->orderByDesc('id');

        if ($col) {
            $q->where($col, $want);
        }

        $search = trim($want === 'page' ? $this->searchPages : $this->searchPosts);
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('title', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $q->limit(50)->get();
    }

    private function queryTerms()
    {
        $q = Term::query()->orderBy('name');

        $search = trim($this->searchTerms);
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $q->limit(100)->get();
    }
}