<?php

namespace App\Livewire;

use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class MediaLibraryBrowser extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public ?int $activeId = null;

    public string $statePath = '';
    public bool $multiple = false;
    public ?int $maxItems = null;

    /** @var array<int, int> */
    public array $selected = [];

    // Filters (picker mode)
    public string $search = '';
    public string $type = 'all'; // all|image|video|pdf|other
    public ?int $folder = null;  // term id
    public string $sort = 'newest'; // newest|oldest|name_asc|name_desc

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
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedType(): void
    {
        $this->resetPage();
    }
    public function updatedFolder(): void
    {
        $this->resetPage();
    }
    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Selected media models keyed by id (used for selected strip).
     */
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

    /**
     * Optional: folder options (uses taxonomy key: media_folder)
     */
    public function folderOptions(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_folder')->value('id');
        if (!$taxonomyId) {
            return [];
        }

        return Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
            $this->selected = array_values(array_filter(
                $this->selected,
                fn($x) => (int) $x !== $id
            ));

            if ($this->activeId === $id) {
                $this->activeId = $this->selected[0] ?? null;
            }

            return;
        }

        if ($this->maxItems && count($this->selected) >= $this->maxItems) {
            return;
        }

        $this->selected[] = $id;
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

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->activeId = null;
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

    public function getMediaProperty(): LengthAwarePaginator
    {
        return Media::query()
            ->when($this->search !== '', function ($q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('title', 'like', $s)
                        ->orWhere('original_filename', 'like', $s);
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
            ->when($this->folder, function ($q) {
                $termId = (int) $this->folder;
                if ($termId > 0) {
                    $q->whereHas('terms', fn($qq) => $qq->where('terms.id', $termId));
                }
            })
            ->when(true, function ($q) {
                return match ($this->sort) {
                    'oldest' => $q->orderBy('id', 'asc'),
                    'name_asc' => $q->orderBy('title')->orderBy('id', 'desc'),
                    'name_desc' => $q->orderByDesc('title')->orderBy('id', 'desc'),
                    default => $q->orderByDesc('id'),
                };
            })
            ->paginate(36);
    }

    public function render(): View
    {
        return view('livewire.media-library-browser', [
            'media' => $this->media,
            'selectedMedia' => $this->selectedMedia,
            'folders' => $this->folderOptions(),
        ]);
    }
}