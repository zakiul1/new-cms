<?php

namespace App\Livewire;

use App\Cms\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaLibraryBrowser extends Component
{
    use WithPagination;
    use WithFileUploads;

    protected string $paginationTheme = 'tailwind';

    public string $statePath = '';

    public bool $multiple = false;

    public ?int $maxItems = null;

    /** @var array<int, int> */
    public array $selected = [];

    public string $tab = 'library'; // library | upload
    public string $search = '';

    /** Focused item (right panel) */
    public ?int $activeId = null;

    /** Edit fields for right panel */
    public array $edit = [
        'title' => '',
        'alt' => '',
        'caption' => '',
        'description' => '',
    ];

    /** @var array<int, UploadedFile> */
    public array $uploads = [];

    public function mount(string $statePath = '', bool $multiple = false, ?int $maxItems = null, array $selected = []): void
    {
        $this->statePath = $statePath;
        $this->multiple = $multiple;
        $this->maxItems = $maxItems;

        $this->selected = array_values(array_filter(array_map('intval', $selected)));

        $this->activeId = $this->selected[0] ?? null;
        $this->loadActiveToEditor();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetErrorBag();
    }

    public function setActive(int $id): void
    {
        $this->activeId = (int) $id;
        $this->loadActiveToEditor();
    }

    public function toggle(int $id): void
    {
        $id = (int) $id;

        $this->activeId = $id;
        $this->loadActiveToEditor();

        if (!$this->multiple) {
            $this->selected = [$id];
            return;
        }

        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_filter($this->selected, fn($x) => (int) $x !== $id));
            if ($this->activeId === $id) {
                $this->activeId = $this->selected[0] ?? null;
                $this->loadActiveToEditor();
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
        $this->selected = array_values(array_filter($this->selected, fn($x) => (int) $x !== $id));

        if ($this->activeId === $id) {
            $this->activeId = $this->selected[0] ?? null;
            $this->loadActiveToEditor();
        }
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->activeId = null;
        $this->edit = ['title' => '', 'alt' => '', 'caption' => '', 'description' => ''];
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

    public function upload(): void
    {
        $this->validate([
            'uploads' => ['required', 'array', 'min:1'],
            'uploads.*' => ['file', 'max:' . ((int) config('cms-media.max_upload_mb', 50) * 1024)],
        ]);

        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);

        $createdIds = [];

        foreach ($this->uploads as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $media = $uploader->upload($file);
            $createdIds[] = (int) $media->id;
        }

        $this->uploads = [];
        $this->resetErrorBag();

        foreach ($createdIds as $id) {
            if (!$this->multiple) {
                $this->selected = [$id];
                $this->activeId = $id;
                break;
            }

            if ($this->maxItems && count($this->selected) >= $this->maxItems) {
                break;
            }

            if (!in_array($id, $this->selected, true)) {
                $this->selected[] = $id;
                $this->activeId = $id;
            }
        }

        $this->loadActiveToEditor();
        $this->tab = 'library';
        $this->resetPage();
    }

    public function saveDetails(): void
    {
        if (!$this->activeId) {
            return;
        }

        $data = $this->validate([
            'edit.title' => ['nullable', 'string', 'max:255'],
            'edit.alt' => ['nullable', 'string', 'max:255'],
            'edit.caption' => ['nullable', 'string'],
            'edit.description' => ['nullable', 'string'],
        ]);

        $m = Media::query()->find($this->activeId);
        if (!$m) {
            return;
        }

        $m->update([
            'title' => $data['edit']['title'] ?? null,
            'alt' => $data['edit']['alt'] ?? null,
            'caption' => $data['edit']['caption'] ?? null,
            'description' => $data['edit']['description'] ?? null,
        ]);
    }

    private function loadActiveToEditor(): void
    {
        if (!$this->activeId) {
            $this->edit = ['title' => '', 'alt' => '', 'caption' => '', 'description' => ''];
            return;
        }

        $m = Media::query()->find($this->activeId);
        if (!$m) {
            $this->edit = ['title' => '', 'alt' => '', 'caption' => '', 'description' => ''];
            return;
        }

        $this->edit = [
            'title' => (string) ($m->title ?? ''),
            'alt' => (string) ($m->alt ?? ''),
            'caption' => (string) ($m->caption ?? ''),
            'description' => (string) ($m->description ?? ''),
        ];
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
            ->orderByDesc('id')
            ->paginate(36);
    }

    public function render(): View
    {
        return view('livewire.media-library-browser', [
            'media' => $this->media,
        ]);
    }
}