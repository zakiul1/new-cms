<?php

namespace App\Livewire\Cms\Media;

use App\Cms\Media\MediaUploader;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class WpMediaUploader extends Component
{
    use WithFileUploads;

    /**
     * @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile>
     */
    public $files = [];

    public ?int $categoryId = null;
    public string $newCategoryName = '';

    /** @var array<int, int> */
    public array $uploadedIds = [];

    protected function rules(): array
    {
        $maxKb = ((int) config('cms-media.max_upload_mb', 50)) * 1024;

        return [
            'files.*' => ['file', 'max:' . $maxKb],
            'categoryId' => ['nullable', 'integer', 'min:1'],
            'newCategoryName' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * ✅ KEY FIX:
     * Livewire calls this after it finishes temp-uploading the selected files.
     * Now we immediately persist them into your Media table.
     */
    public function updatedFiles(): void
    {
        $this->uploadNow();
    }

    // (Optional) keep for backward compat
    public function filesUploaded(): void
    {
        $this->uploadNow();
    }

    public function uploadNow(): void
    {
        if (empty($this->files))
            return;

        $this->validateOnly('files.*');

        $uploader = app(MediaUploader::class);
        $newIds = [];

        foreach ($this->files as $file) {
            $media = $uploader->upload($file, [
                'category_term_ids' => $this->categoryId ? [$this->categoryId] : [],
                'default_category_name' => 'Uncategorized',
            ]);

            array_unshift($this->uploadedIds, (int) $media->id);
            $newIds[] = (int) $media->id;
        }

        $this->reset('files');

        // ✅ tell parent browser to switch to library and refresh
        if (!empty($newIds)) {
            $this->dispatch('wp-media-uploaded', ids: $newIds);
        }
    }

    public function createCategory(): void
    {
        $name = trim($this->newCategoryName);

        if ($name === '') {
            $this->addError('newCategoryName', 'Category name is required.');
            return;
        }

        $taxonomy = Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        );

        $slugBase = Str::slug($name);
        $slugBase = $slugBase !== '' ? $slugBase : 'category';

        $existing = Term::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->where(function ($q) use ($name, $slugBase) {
                $q->where('name', $name)->orWhere('slug', $slugBase);
            })
            ->first();

        if ($existing) {
            $this->categoryId = $existing->id;
            $this->newCategoryName = '';
            $this->resetErrorBag('newCategoryName');
            return;
        }

        $slug = $slugBase;
        $i = 2;
        while (Term::where('taxonomy_id', $taxonomy->id)->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $i;
            $i++;
        }

        $term = Term::create([
            'taxonomy_id' => $taxonomy->id,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => null,
        ]);

        $this->categoryId = $term->id;
        $this->newCategoryName = '';
        $this->resetErrorBag('newCategoryName');
    }

    public function getUploadedMediaProperty()
    {
        $ids = array_values(array_unique(array_filter($this->uploadedIds)));

        if (empty($ids))
            return collect();

        return Media::query()
            ->whereIn('id', $ids)
            ->orderByRaw('FIELD(id,' . implode(',', array_map('intval', $ids)) . ')')
            ->get();
    }

    public function render()
    {
        $catTaxId = Taxonomy::query()->where('key', 'media_category')->value('id');

        $categories = $catTaxId
            ? Term::query()->where('taxonomy_id', $catTaxId)->orderBy('name')->get()
            : collect();

        return view('livewire.cms.media.wp-media-uploader', [
            'categories' => $categories,
            'uploadedMedia' => $this->uploadedMedia,
        ]);
    }
}