<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Cms\Media\MediaUploader;
use App\Filament\Resources\MediaResource;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class UploadMedia extends Page
{
    use WithFileUploads;

    protected static string $resource = MediaResource::class;

    protected string $view = 'filament.media.upload-media';

    /** @var array<int, TemporaryUploadedFile|UploadedFile> */
    public array $wpUploadFiles = [];

    public ?int $wpFolderId = null;

    /** @var array<int> */
    public array $wpCategoryIds = [];

    // search boxes (UI)
    public string $folderSearch = '';
    public string $categorySearch = '';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getFolderOptionsProperty(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_folder')->value('id');
        if (!$taxonomyId) {
            return [];
        }

        $q = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name');

        if (trim($this->folderSearch) !== '') {
            $q->where('name', 'like', '%' . trim($this->folderSearch) . '%');
        }

        return $q->pluck('name', 'id')->all();
    }

    public function getCategoryOptionsProperty(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId) {
            return [];
        }

        $q = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name');

        if (trim($this->categorySearch) !== '') {
            $q->where('name', 'like', '%' . trim($this->categorySearch) . '%');
        }

        return $q->pluck('name', 'id')->all();
    }

    protected function ensureTaxonomy(string $key, string $label, bool $hierarchical = true): Taxonomy
    {
        return Taxonomy::firstOrCreate(
            ['key' => $key],
            ['label' => $label, 'hierarchical' => $hierarchical],
        );
    }

    protected function createUniqueTerm(int $taxonomyId, string $name, ?int $parentId = null): Term
    {
        $name = trim($name) !== '' ? trim($name) : 'Untitled';

        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'term';

        $slug = $base;
        $i = 2;

        while (Term::where('taxonomy_id', $taxonomyId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return Term::create([
            'taxonomy_id' => $taxonomyId,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
        ]);
    }

    protected function folderParentOptions(int $taxonomyId): array
    {
        return Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newFolder')
                ->label('New Folder')
                ->icon('heroicon-o-folder-plus')
                ->modalHeading('Create Folder')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Folder name')
                        ->required()
                        ->maxLength(255),

                    \Filament\Forms\Components\Select::make('parent_id')
                        ->label('Parent folder (optional)')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->options(function () {
                            $tax = $this->ensureTaxonomy('media_folder', 'Media Folders', true);
                            return $this->folderParentOptions($tax->id);
                        }),
                ])
                ->action(function (array $data) {
                    $tax = $this->ensureTaxonomy('media_folder', 'Media Folders', true);

                    $term = $this->createUniqueTerm(
                        taxonomyId: $tax->id,
                        name: (string) ($data['name'] ?? 'Folder'),
                        parentId: filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                    );

                    $this->wpFolderId = (int) $term->id;

                    Notification::make()->title('Folder created')->success()->send();
                }),

            Action::make('newCategory')
                ->label('New Category')
                ->icon('heroicon-o-tag')
                ->modalHeading('Create Category')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Category name')
                        ->required()
                        ->maxLength(255),

                    \Filament\Forms\Components\Select::make('parent_id')
                        ->label('Parent category (optional)')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->options(function () {
                            $tax = $this->ensureTaxonomy('media_category', 'Media Categories', true);
                            return $this->folderParentOptions($tax->id);
                        }),
                ])
                ->action(function (array $data) {
                    $tax = $this->ensureTaxonomy('media_category', 'Media Categories', true);

                    $term = $this->createUniqueTerm(
                        taxonomyId: $tax->id,
                        name: (string) ($data['name'] ?? 'Category'),
                        parentId: filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                    );

                    $this->wpCategoryIds = array_values(array_unique(array_merge($this->wpCategoryIds, [(int) $term->id])));

                    Notification::make()->title('Category created')->success()->send();
                }),

            Action::make('back')
                ->label('Back to Media')
                ->icon('heroicon-o-arrow-left')
                ->url(MediaResource::getUrl('index')),
        ];
    }

    public function clearUploads(): void
    {
        $this->wpUploadFiles = [];
    }

    public function removeUploadFile(int $index): void
    {
        if (!isset($this->wpUploadFiles[$index])) {
            return;
        }

        unset($this->wpUploadFiles[$index]);
        $this->wpUploadFiles = array_values($this->wpUploadFiles);
    }

    public function uploadWpMedia(): void
    {
        $files = $this->wpUploadFiles ?? [];

        $folderId = $this->wpFolderId ? (int) $this->wpFolderId : null;

        $categoryIds = collect($this->wpCategoryIds ?? [])
            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (!is_array($files) || count($files) === 0) {
            Notification::make()->title('No files selected.')->danger()->send();
            return;
        }

        $uploader = app(MediaUploader::class);

        $count = 0;

        foreach ($files as $file) {
            if (!($file instanceof TemporaryUploadedFile || $file instanceof UploadedFile)) {
                continue;
            }

            $media = $uploader->upload($file);

            if ($folderId) {
                $media->syncFolderTerm($folderId);
            }

            if (!empty($categoryIds)) {
                $media->syncCategoryTerms($categoryIds);
            }

            $count++;
        }

        $this->wpUploadFiles = [];

        Notification::make()->title("Uploaded {$count} file(s).")->success()->send();

        $this->redirect(MediaResource::getUrl('index'));
    }
}