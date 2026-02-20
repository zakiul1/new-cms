<?php

namespace App\Livewire\Cms\Media;

use App\Cms\Media\MediaUploader;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class WpMediaUploader extends Component
{
    use WithFileUploads;

    /**
     * Backward compatible multi-upload (current behavior)
     *
     * @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile>
     */
    public array $files = [];

    /**
     * New WP-style single-file upload property (used by Livewire JS @this.upload)
     *
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $singleFile = null;

    public ?int $categoryId = null;
    public string $newCategoryName = '';

    /** @var array<int, int> */
    public array $uploadedIds = [];

    protected function rules(): array
    {
        $maxKb = ((int) config('cms-media.max_upload_mb', 50)) * 1024;

        return [
            // Old multi upload
            'files.*' => ['file', 'max:' . $maxKb],

            // New single upload
            'singleFile' => ['nullable', 'file', 'max:' . $maxKb],

            'categoryId' => ['nullable', 'integer', 'min:1'],
            'newCategoryName' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Old behavior: Livewire calls this after temp-upload of selected files completes.
     * Keep it working (uploads everything at once after temp-upload).
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

    /**
     * Old behavior: persist all files in one request.
     */
    public function uploadNow(): void
    {
        if (empty($this->files)) {
            return;
        }

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

        if (!empty($newIds)) {
            $this->dispatch('wp-media-uploaded', ids: $newIds);
        }
    }

    /**
     * ✅ NEW (WP-style):
     * Called AFTER frontend uploads one file into `singleFile` using Livewire JS:
     *   this.$wire.upload('singleFile', file, ...)
     *
     * Then this method persists it into Media table.
     *
     * Returns the created media id.
     */
    public function persistSingleUpload(): ?int
    {
        if (!$this->singleFile) {
            return null;
        }

        $this->validateOnly('singleFile');

        $uploader = app(MediaUploader::class);

        $media = $uploader->upload($this->singleFile, [
            'category_term_ids' => $this->categoryId ? [$this->categoryId] : [],
            'default_category_name' => 'Uncategorized',
        ]);

        $id = (int) $media->id;

        array_unshift($this->uploadedIds, $id);

        // Reset so next file can be uploaded
        $this->reset('singleFile');

        // Tell browser/modal to refresh + switch to library tab if needed
        $this->dispatch('wp-media-uploaded', ids: [$id]);

        return $id;
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

    public function getUploadedMediaProperty(): Collection
    {
        $ids = array_values(array_unique(array_filter($this->uploadedIds)));

        if (empty($ids)) {
            return collect();
        }

        return Media::query()
            ->whereIn('id', $ids)
            ->orderByRaw('FIELD(id,' . implode(',', array_map('intval', $ids)) . ')')
            ->get();
    }

    /**
     * ✅ Category options with indentation:
     * Parent
     * — Child
     * —— Grandchild
     *
     * Returns Collection<int, array{id:int,label:string}>
     */
    public function getCategoryOptionsProperty(): Collection
    {
        $catTaxId = Taxonomy::query()->where('key', 'media_category')->value('id');

        if (!$catTaxId) {
            return collect();
        }

        $terms = Term::query()
            ->where('taxonomy_id', $catTaxId)
            ->orderBy('name')
            ->get();

        return $this->buildIndentedTerms($terms);
    }

    /**
     * Build a flattened, indented list from hierarchical terms.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Term>  $terms
     * @return \Illuminate\Support\Collection<int, array{id:int,label:string}>
     */
    protected function buildIndentedTerms(Collection $terms): Collection
    {
        // group by parent_id for quick tree traversal
        $byParent = $terms->groupBy(fn (Term $t) => (int) ($t->parent_id ?? 0));

        $out = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, &$out) {
            /** @var \Illuminate\Support\Collection<int, Term> $children */
            $children = $byParent->get($parentId, collect());

            // sort children by name (safe even if DB already ordered)
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

        // root nodes: parent_id null => mapped as 0
        $walk(0, 0);

        return $out;
    }

    public function render()
    {
        return view('livewire.cms.media.wp-media-uploader', [
            'categoryOptions' => $this->categoryOptions,
            'uploadedMedia' => $this->uploadedMedia,
        ]);
    }
}