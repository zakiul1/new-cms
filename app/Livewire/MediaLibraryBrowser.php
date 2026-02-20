<?php

namespace App\Livewire;

use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MediaLibraryBrowser extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $tab = 'library'; // library|upload
    public ?int $activeId = null;

    public string $statePath = '';
    public bool $multiple = false;
    public ?int $maxItems = null;

    /** @var array<int, int> */
    public array $selected = [];

    // WP-like filters
    public string $search = '';
    public string $type = 'all';      // all|image|video|pdf|other
    public string $date = '';         // '' or 'YYYY-MM'
    public string $category = '';     // '' or term id

    // forces refresh after uploads
    public int $refreshTick = 0;

    public function mount(
        string $statePath = '',
        bool $multiple = false,
        ?int $maxItems = null,
        array $selected = [],
    ): void {
        $this->statePath = $statePath;
        $this->multiple = $multiple;
        $this->maxItems = $maxItems;

        $this->selected = array_values(array_filter(array_map('intval', $selected)));
        $this->activeId = $this->selected[0] ?? null;

        $this->tab = 'library';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'upload' ? 'upload' : 'library';
    }

    // Reset paging when filters change (Livewire best practice)
    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingType(): void
    {
        $this->resetPage();
    }
    public function updatingDate(): void
    {
        $this->resetPage();
    }
    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    /**
     * Fired by uploader after upload completes.
     */
    #[On('wp-media-uploaded')]
    public function onUploaded(array $ids = []): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $ids = array_values(array_filter($ids, fn($x) => $x > 0));

        $this->tab = 'library';
        $this->refreshTick++;

        if (!empty($ids)) {
            if ($this->multiple) {
                foreach ($ids as $id) {
                    if ($this->maxItems && count($this->selected) >= $this->maxItems) {
                        break;
                    }
                    if (!in_array($id, $this->selected, true)) {
                        $this->selected[] = $id;
                    }
                }
                $this->activeId = $this->activeId ?: ($this->selected[0] ?? $ids[0]);
            } else {
                $this->selected = [(int) $ids[0]];
                $this->activeId = (int) $ids[0];
            }
        }

        $this->resetPage();
    }

    public function getSelectedMediaProperty(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->selected)));
        if (empty($ids)) {
            return collect();
        }

        return Media::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    public function getActiveMediaProperty(): ?Media
    {
        $id = is_numeric($this->activeId) ? (int) $this->activeId : null;
        if (!$id) {
            return null;
        }

        return Media::query()->find($id);
    }

    public function toggle(int $id): void
    {
        $id = (int) $id;
        $this->activeId = $id;

        if (!$this->multiple) {
            $this->selected = [$id];
            return;
        }

        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_filter($this->selected, fn($x) => (int) $x !== $id));
            $this->activeId = $this->selected[0] ?? null;
            return;
        }

        if ($this->maxItems && count($this->selected) >= $this->maxItems) {
            return;
        }

        $this->selected[] = $id;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->activeId = null;
    }

    public function removeSelected(int $id): void
    {
        $id = (int) $id;

        $this->selected = array_values(array_filter(
            $this->selected,
            fn($x) => (int) $x !== $id
        ));

        if ($this->activeId === $id) {
            $this->activeId = $this->selected[0] ?? null;
        }
    }

    public function apply(): void
    {
        $ids = array_values(array_filter(array_map('intval', $this->selected)));

        if (!$this->multiple) {
            $ids = $ids ? [(int) $ids[0]] : [];
        } else {
            if ($this->maxItems) {
                $ids = array_slice($ids, 0, $this->maxItems);
            }
        }

        $this->dispatch('media-library-apply', ids: $ids, statePath: $this->statePath);
    }

    public function dateOptions(): array
    {
        $rows = Media::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->all();

        $out = ['' => 'All dates'];

        foreach ($rows as $ym) {
            try {
                $c = Carbon::createFromFormat('Y-m', (string) $ym)->startOfMonth();
                $out[(string) $ym] = $c->format('F Y');
            } catch (\Throwable $e) {
                $out[(string) $ym] = (string) $ym;
            }
        }

        return $out;
    }

    /**
     * ✅ UPDATED:
     * Category options now show hierarchy with "—" indentation:
     * Parent
     * — Child
     * —— Grandchild
     */
    public function categoryOptions(): array
    {
        $taxId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxId) {
            return ['' => 'All categories'];
        }

        // Need parent_id to build hierarchy
        $terms = Term::query()
            ->where('taxonomy_id', $taxId)
            ->get(['id', 'name', 'parent_id']);

        $out = ['' => 'All categories'];

        foreach ($this->buildIndentedTerms($terms) as $row) {
            $out[(string) $row['id']] = (string) $row['label'];
        }

        return $out;
    }

    /**
     * Build a flattened, indented list from hierarchical terms.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Term>  $terms
     * @return \Illuminate\Support\Collection<int, array{id:int,label:string}>
     */
    protected function buildIndentedTerms(Collection $terms): Collection
    {
        // Group by parent_id (null => 0)
        $byParent = $terms->groupBy(fn(Term $t) => (int) ($t->parent_id ?? 0));

        $out = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, &$out) {
            /** @var \Illuminate\Support\Collection<int, Term> $children */
            $children = $byParent->get($parentId, collect());

            // Sort naturally by name (case-insensitive)
            $children = $children->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

            foreach ($children as $term) {
                $prefix = $depth > 0 ? str_repeat('—', $depth) . ' ' : '';
                $out->push([
                    'id' => (int) $term->id,
                    'label' => $prefix . $term->name,
                ]);

                $walk((int) $term->id, $depth + 1);
            }
        };

        // Root nodes
        $walk(0, 0);

        /**
         * Safety: if there are terms whose parent_id points to a missing parent,
         * they won't appear in the root walk. Append them at the end (depth 0).
         */
        $seen = $out->pluck('id')->map(fn($v) => (int) $v)->all();
        $missing = $terms->filter(fn(Term $t) => !in_array((int) $t->id, $seen, true))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        foreach ($missing as $term) {
            $out->push([
                'id' => (int) $term->id,
                'label' => (string) $term->name,
            ]);
        }

        return $out;
    }

    public function getMediaProperty(): LengthAwarePaginator
    {
        // touch refreshTick so Livewire considers it in dependency tracking
        $refreshTick = $this->refreshTick;

        return Media::query()
            ->when($this->search !== '', function ($q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('title', 'like', $s)
                        ->orWhere('original_filename', 'like', $s)
                        ->orWhere('filename', 'like', $s);
                });
            })
            ->when($this->type !== 'all', function ($q) {
                return match ($this->type) {
                    'image' => $q->where('mime_type', 'like', 'image/%'),
                    'video' => $q->where('mime_type', 'like', 'video/%'),
                    'pdf' => $q->where('mime_type', 'application/pdf'),
                    'other' => $q->where(function ($qq) {
                            $qq->where('mime_type', 'not like', 'image/%')
                            ->where('mime_type', 'not like', 'video/%')
                            ->where('mime_type', '!=', 'application/pdf');
                        }),
                    default => $q,
                };
            })
            ->when($this->date !== '', function ($q) {
                $ym = trim($this->date);
                if (preg_match('/^\d{4}\-\d{2}$/', $ym)) {
                    $q->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$ym]);
                }
            })
            ->when($this->category !== '', function ($q) {
                $termId = (int) $this->category;
                if ($termId > 0) {
                    $q->whereHas('categories', fn($qq) => $qq->where('terms.id', $termId));
                }
            })
            ->orderByDesc('id')
            ->paginate(40);
    }

    public function render(): View
    {
        return view('livewire.media-library-browser', [
            'media' => $this->media,
            'selectedMedia' => $this->selectedMedia,
            'activeMedia' => $this->activeMedia,
            'dateOptions' => $this->dateOptions(),
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }
}