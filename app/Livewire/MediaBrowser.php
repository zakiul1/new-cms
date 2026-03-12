<?php

namespace App\Livewire;

use App\Cms\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\On;
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

    public ?string $targetKey = null;
    public ?string $source = null;
    public string $type = 'all'; // all|image

    public bool $isOpen = false;
    public bool $autoConfirmSingle = true;

    public function mount(
        bool $multiple = false,
        ?int $maxItems = null,
        array $selectedIds = [],
        string $eventName = 'media-picker-selected',
        ?string $targetKey = null,
        ?string $source = null,
        string $type = 'all'
    ): void {
        $this->multiple = $multiple;
        $this->maxItems = $maxItems;
        $this->selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));
        $this->eventName = $eventName;
        $this->targetKey = $targetKey;
        $this->source = $source;
        $this->type = in_array($type, ['all', 'image'], true) ? $type : 'all';
    }

    #[On('cms-media-browser-open')]
    public function openFromCustomizer(
        ?string $targetKey = null,
        ?string $type = 'image',
        ?string $source = null,
        ?bool $multiple = false,
        ?int $maxItems = null,
        array $selected = []
    ): void {
        $this->isOpen = true;
        $this->targetKey = $targetKey;
        $this->source = $source ?: 'theme-customizer';
        $this->type = in_array($type, ['all', 'image'], true) ? $type : 'all';
        $this->multiple = (bool) $multiple;
        $this->maxItems = $maxItems;
        $this->selectedIds = array_values(array_unique(array_filter(array_map('intval', $selected))));
        $this->resetPage();

        $this->dispatch('cms-media-browser-visibility', open: true);
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->dispatch('cms-media-browser-visibility', open: false);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        if (!$this->multiple) {
            $this->selectedIds = [$id];

            if ($this->autoConfirmSingle) {
                $this->confirm();
            }

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
        if ($this->multiple) {
            $this->dispatch(
                $this->eventName,
                ids: $this->selectedIds,
                targetKey: $this->targetKey,
                source: $this->source
            );

            $this->close();
            return;
        }

        $selectedId = $this->selectedIds[0] ?? null;
        if (!$selectedId) {
            return;
        }

        $this->dispatch(
            $this->eventName,
            mediaId: (int) $selectedId,
            ids: [(int) $selectedId],
            targetKey: $this->targetKey,
            source: $this->source
        );

        $this->dispatch(
            'cms-media-selected',
            mediaId: (int) $selectedId,
            ids: [(int) $selectedId],
            targetKey: $this->targetKey,
            source: $this->source
        );

        $this->close();
    }

    public function upload(): void
    {
        $rules = [
            'uploads.*' => ['file', 'max:' . ((int) config('cms-media.max_upload_mb', 50) * 1024)],
        ];

        if ($this->type === 'image') {
            $rules['uploads.*'][] = 'mimes:jpg,jpeg,png,gif,webp,svg,avif,ico';
        }

        $this->validate($rules);

        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);

        $lastUploadedId = null;

        foreach ($this->uploads as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $media = $uploader->upload($file);

            if ($this->type === 'image' && method_exists($media, 'isImage') && !$media->isImage()) {
                continue;
            }

            $lastUploadedId = (int) $media->id;

            if ($this->multiple) {
                $this->toggle((int) $media->id);
            }
        }

        $this->uploads = [];
        $this->resetPage();

        if (!$this->multiple && $lastUploadedId) {
            $this->selectedIds = [$lastUploadedId];

            if ($this->autoConfirmSingle) {
                $this->confirm();
            }
        }
    }

    public function render(): View
    {
        $q = Media::query()
            ->with('variantRecords')
            ->orderByDesc('id');

        if ($this->type === 'image') {
            $q->where(function ($qq) {
                $qq->where('mime_type', 'like', 'image/%');
            });
        }

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