<?php

namespace App\Livewire;

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

    // Menu actions UI
    public string $newMenuName = '';
    public bool $isRenaming = false;
    public string $renameValue = '';

    // ✅ Location assignment
    public ?string $activeLocationKey = null;

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

    // Right side state
    public array $tree = [];       // nested structure
    public array $items = [];      // editable state by id
    public array $collapsed = [];  // collapse/expand per item id

    // Autosave status
    public array $savedAt = [];    // timestamps by id

   public function mount(): void
{
    $this->activeMenuId = Menu::query()->orderBy('name')->value('id');

    $this->activeLocationKey = $this->activeMenuId
        ? MenuAssignment::query()->where('menu_id', $this->activeMenuId)->value('location_key')
        : null;

    $this->reload();
}


    public function render()
    {
        return view('livewire.menu-builder', [
            'menus' => Menu::query()->orderBy('name')->get(),

            // ✅ REQUIRED for location dropdown
            'locations' => MenuLocation::query()->orderBy('label')->get(),

            'posts' => $this->queryPosts('post'),
            'pages' => $this->queryPosts('page'),
            'taxonomies' => Taxonomy::query()->orderBy('label')->get(),
            'terms' => $this->queryTerms(),
        ]);
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

        $this->reload();
    }

    public function createMenu(): void
    {
        $name = trim($this->newMenuName);
        if ($name === '') {
            $this->addError('newMenuName', 'Menu name is required.');
            return;
        }

        $menu = Menu::query()->create(['name' => $name]);

        $this->newMenuName = '';
        $this->activeMenuId = (int) $menu->id;

        // ✅ reset location selection for new menu
        $this->activeLocationKey = null;

        $this->reload();
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
            return;
        }

        Menu::query()->whereKey($this->activeMenuId)->update(['name' => $name]);
        $this->isRenaming = false;
        $this->reload();
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

            // ✅ copied menu starts unassigned (optional)
            $this->activeLocationKey = null;
        });

        $this->reload();
    }

    public function deleteActiveMenu(): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        DB::transaction(function () {
            // remove assignments too (cleanup)
            MenuAssignment::query()->where('menu_id', $this->activeMenuId)->delete();

            MenuItem::query()->where('menu_id', $this->activeMenuId)->delete();
            Menu::query()->whereKey($this->activeMenuId)->delete();
        });

        $this->activeMenuId = Menu::query()->orderBy('name')->value('id');

        $this->activeLocationKey = $this->activeMenuId
            ? MenuAssignment::query()->where('menu_id', $this->activeMenuId)->value('location_key')
            : null;

        $this->reload();
    }

    // -------------------------
    // ✅ Location assignment
    // -------------------------

  public function assignLocation(string $locationKey): void
{
    if (!$this->activeMenuId) {
        return;
    }

    $locationKey = trim($locationKey);

    // ✅ Unassign: remove any assignment for this menu
    if ($locationKey === '') {
        MenuAssignment::query()
            ->where('menu_id', $this->activeMenuId)
            ->delete();

        $this->activeLocationKey = null;
        return;
    }

    // ✅ If this menu was assigned to another location, remove it first (1 menu = 1 location)
    MenuAssignment::query()
        ->where('menu_id', $this->activeMenuId)
        ->where('location_key', '!=', $locationKey)
        ->delete();

    // ✅ Assign location (1 location = 1 menu)
    MenuAssignment::query()->updateOrCreate(
        ['location_key' => $locationKey],
        ['menu_id' => $this->activeMenuId],
    );

    $this->activeLocationKey = $locationKey;
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

        $ids = array_values(array_filter(array_map('intval', $this->selectedTermIds), fn ($v) => $v > 0));
        if ($ids === []) {
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

        $this->selectedTermIds = [];
        $this->reload();
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

        $this->customLabel = '';
        $this->customUrl = '';
        $this->reload();
    }

    // -------------------------
    // Right side actions
    // -------------------------

    public function toggleCollapse(int $id): void
    {
        $this->collapsed[$id] = !($this->collapsed[$id] ?? false);
    }

    public function removeItem(int $id): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        DB::transaction(function () use ($id) {
            $this->deleteItemRecursive($id);
        });

        $this->reload();
    }

    /**
     * Livewire auto-save
     * key example: "123.label" or "123.visibility.roles_csv"
     */
    public function updatedItems($value, string $key): void
    {
        $parts = explode('.', $key);
        $id = (int) ($parts[0] ?? 0);
        if ($id <= 0) {
            return;
        }

        $this->saveItemSilent($id);
    }

    public function saveItemSilent(int $id): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        $row = $this->items[$id] ?? null;
        if (!is_array($row)) {
            return;
        }

        // Persist
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

                // keep your existing structure:
                'visibility' => $row['visibility'] ?? null,
                'data' => $row['data'] ?? null,
            ]);

        $this->savedAt[$id] = time();
    }

    /** Called by JS after drag/drop */
    public function reorder(array $tree): void
    {
        if (!$this->activeMenuId) {
            return;
        }

        DB::transaction(function () use ($tree) {
            $this->persistTree($tree, null);
        });

        $this->reload();
    }

    // -------------------------
    // Internals
    // -------------------------

    private function reload(): void
    {
        $this->tree = $this->buildTree();
        $this->items = $this->buildItemsState();

        foreach (array_keys($this->items) as $id) {
            $this->collapsed[$id] = $this->collapsed[$id] ?? false;
        }

        $this->dispatch('menu-builder-init');
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
            ->groupBy(fn (MenuItem $i) => $i->parent_id ?: 0);

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

        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        if ($ids === []) {
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

        $this->reload();
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
