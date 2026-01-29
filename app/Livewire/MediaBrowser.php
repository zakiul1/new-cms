<?php

namespace App\Livewire;

use App\Cms\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaBrowser extends Component
{
    use WithPagination;
    use WithFileUploads;

    public bool $multiple = false;

    public ?int $maxItems = null;

    /** @var array<int,int> */
    public array $selectedIds = [];

    public string $eventName = 'media-picker-selected';

    #[Url]
    public string $search = '';

    /** @var array<int, UploadedFile> */
    public array $uploads = [];

    public function mount(bool $multiple = false, ?int $maxItems = null, array $selectedIds = [], string $eventName = 'media-picker-selected'): void
    {
        $this->multiple = $multiple;
        $this->maxItems = $maxItems;
        $this->selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));
        $this->eventName = $eventName;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        if (!$this->multiple) {
            $this->selectedIds = [$id];
            return;
        }

        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_filter($this->selectedIds, fn($x) => $x !== $id));
            return;
        }

        $this->selectedIds[] = $id;
        $this->selectedIds = array_values(array_unique($this->selectedIds));

        if (!is_null($this->maxItems)) {
            $this->selectedIds = array_slice($this->selectedIds, 0, $this->maxItems);
        }
    }

    public function confirm(): void
    {
        $this->dispatch($this->eventName, ids: $this->selectedIds);
    }

    public function upload(): void
    {
        $this->validate([
            'uploads.*' => ['file', 'max:' . ((int) config('cms-media.max_upload_mb', 50) * 1024)],
        ]);

        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);

        foreach ($this->uploads as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $media = $uploader->upload($file);

            // auto-select newly uploaded
            $this->toggle((int) $media->id);
        }

        $this->uploads = [];
        $this->resetPage();
    }

    public function render(): View
    {
        $q = Media::query()
            ->with('variantRecords')   // ✅ IMPORTANT: prevents N+1 in thumbnail grid
            ->orderByDesc('id');

        if (trim($this->search) !== '') {
            $s = trim($this->search);

            $q->where(function ($qq) use ($s) {
                $qq->where('title', 'like', "%{$s}%")
                    ->orWhere('original_filename', 'like', "%{$s}%");
            });
        }

        return view('livewire.media-browser', [
            'items' => $q->paginate(24),
        ]);
    }
}