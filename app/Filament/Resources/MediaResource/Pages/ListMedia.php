<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Cms\Media\MediaUploader;
use App\Filament\Resources\MediaResource;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\View as LayoutView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    public string $viewMode = 'grid'; // grid | list
    public bool $selectMode = false;  // show checkboxes + bulk actions only when true

    public function mount(): void
    {
        parent::mount();

        $this->viewMode = (string) session()->get('media.viewMode', 'grid');
        $this->selectMode = (bool) session()->get('media.selectMode', false);

        if (!in_array($this->viewMode, ['grid', 'list'], true)) {
            $this->viewMode = 'grid';
        }
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
        session()->put('media.viewMode', $this->viewMode);

        // leaving grid => stop select mode (WP-like)
        if ($this->viewMode !== 'grid') {
            $this->selectMode = false;
            session()->put('media.selectMode', false);
        }

        $this->resetTable();
    }

    protected function toggleSelectMode(): void
    {
        // only meaningful in grid mode
        if ($this->viewMode !== 'grid') {
            $this->viewMode = 'grid';
            session()->put('media.viewMode', 'grid');
        }

        $this->selectMode = !$this->selectMode;
        session()->put('media.selectMode', $this->selectMode);

        $this->resetTable();
    }

    protected function folderOptions(): array
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

    protected function categoryOptions(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId) {
            return [];
        }

        return Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function ensureTaxonomy(string $key, string $label, bool $hierarchical = true): Taxonomy
    {
        // Some projects have Taxonomy::ensure(), but to keep this file safe:
        return Taxonomy::firstOrCreate(
            ['key' => $key],
            ['label' => $label, 'hierarchical' => $hierarchical],
        );
    }

    protected function createUniqueTerm(int $taxonomyId, string $name, ?int $parentId = null): Term
    {
        $name = trim($name);
        $name = $name !== '' ? $name : 'Untitled';

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grid')
                ->label('Grid')
                ->icon('heroicon-o-squares-2x2')
                ->color($this->viewMode === 'grid' ? 'primary' : 'gray')
                ->action(fn() => $this->setViewMode('grid')),

            Action::make('list')
                ->label('List')
                ->icon('heroicon-o-list-bullet')
                ->color($this->viewMode === 'list' ? 'primary' : 'gray')
                ->action(fn() => $this->setViewMode('list')),

            Action::make('select')
                ->label($this->selectMode ? 'Done' : 'Select')
                ->icon($this->selectMode ? 'heroicon-o-check' : 'heroicon-o-check-circle')
                ->color($this->selectMode ? 'primary' : 'gray')
                ->visible(fn() => $this->viewMode === 'grid')
                ->action(fn() => $this->toggleSelectMode()),

            // ✅ Upload modal (now includes Categories + runtime create)
            Action::make('upload')
                ->label('Upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->slideOver()
                ->modalWidth('7xl')
                ->form([
                    Select::make('term_id')
                        ->label('Upload into Folder (optional)')
                        ->options(fn() => $this->folderOptions())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Folder name')
                                ->required()
                                ->maxLength(255),

                            Select::make('parent_id')
                                ->label('Parent (optional)')
                                ->searchable()
                                ->preload()
                                ->options(fn() => $this->folderOptions())
                                ->nullable(),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $taxonomy = $this->ensureTaxonomy('media_folder', 'Media Folders', true);

                            $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;

                            $term = $this->createUniqueTerm(
                                (int) $taxonomy->id,
                                (string) ($data['name'] ?? ''),
                                $parentId
                            );

                            return (int) $term->id;
                        })
                        ->helperText('You can create a folder here, or manage folders from “Media Folders”.'),

                    // ✅ NEW: Categories
                    Select::make('category_term_ids')
                        ->label('Categories (optional)')
                        ->options(fn() => $this->categoryOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Category name')
                                ->required()
                                ->maxLength(255),

                            Select::make('parent_id')
                                ->label('Parent (optional)')
                                ->searchable()
                                ->preload()
                                ->options(fn() => $this->categoryOptions())
                                ->nullable(),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $taxonomy = $this->ensureTaxonomy('media_category', 'Media Categories', true);

                            $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;

                            $term = $this->createUniqueTerm(
                                (int) $taxonomy->id,
                                (string) ($data['name'] ?? ''),
                                $parentId
                            );

                            return (int) $term->id;
                        })
                        ->helperText('Optional. If none selected, media will be saved as Uncategorized.'),

                    Action::make('folders')
                        ->label('Folders')
                        ->icon('heroicon-o-folder')
                        ->color('gray')
                        ->url(\App\Filament\Resources\MediaFolderResource::getUrl('index'))
                        ->openUrlInNewTab(),

                    FileUpload::make('files')
                        ->label('Drop files here')
                        ->required()
                        ->multiple()
                        ->storeFiles(false) // keep TemporaryUploadedFile objects; we store manually
                        ->reorderable()
                        ->appendFiles()
                        ->panelLayout('grid') // ✅ grid preview
                        ->imagePreviewHeight('140')
                        ->maxFiles(50)
                        ->helperText('Drag & drop. Select multiple files. Variants generate automatically for images.')
                        ->columnSpanFull(),

                    Placeholder::make('hint')
                        ->label('')
                        ->content('Tip: Use folders + categories to organize media like WordPress.')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $files = $data['files'] ?? [];
                    $termId = filled($data['term_id'] ?? null) ? (int) $data['term_id'] : null;

                    $categoryIds = isset($data['category_term_ids']) && is_array($data['category_term_ids'])
                        ? collect($data['category_term_ids'])
                            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
                            ->map(fn($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all()
                        : [];

                    // normalize single file
                    if ($files instanceof TemporaryUploadedFile || $files instanceof UploadedFile) {
                        $files = [$files];
                    }

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

                        // ✅ if you updated MediaUploader with options (recommended)
                        $media = $uploader->upload($file, [
                            'folder_term_id' => $termId,
                            'category_term_ids' => $categoryIds,
                            'default_category_name' => 'Uncategorized',
                        ]);

                        // If you did NOT update MediaUploader, use this instead:
                        // $media = $uploader->upload($file);
                        // if ($termId) $media->syncFolderTerm($termId);
                        // if (!empty($categoryIds)) $media->syncCategoryTerms($categoryIds);
        
                        $count++;
                    }

                    Notification::make()->title("Uploaded {$count} file(s).")->success()->send();
                    $this->resetTable();
                }),
        ];
    }

    protected function buildBulkActions(): array
    {
        return [
            DeleteBulkAction::make(),

            BulkAction::make('moveToFolder')
                ->label('Move to folder')
                ->icon('heroicon-o-folder')
                ->form([
                    Select::make('term_id')
                        ->label('Folder')
                        ->searchable()
                        ->preload()
                        ->options(fn(): array => $this->folderOptions())
                        ->required(),
                ])
                ->action(function (Collection $records, array $data): void {
                    $termId = (int) ($data['term_id'] ?? 0);
                    if ($termId <= 0) {
                        return;
                    }

                    foreach ($records as $media) {
                        if ($media instanceof Media) {
                            $media->syncFolderTerm($termId);
                        }
                    }

                    Notification::make()->title('Moved to folder.')->success()->send();
                }),

            BulkAction::make('clearFolder')
                ->label('Clear folder')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (Collection $records): void {
                    foreach ($records as $media) {
                        if ($media instanceof Media) {
                            $media->clearFolderTerms();
                        }
                    }

                    Notification::make()->title('Folder cleared.')->success()->send();
                }),

            // (Optional) Bulk set categories could be added here too if you want.
        ];
    }

    protected function previewAction(): TableAction
    {
        return TableAction::make('preview')
            ->label('Preview')
            ->hidden() // no visible row buttons; tile click triggers it
            ->slideOver()
            ->modalSubmitActionLabel('Save')
            ->modalCancelActionLabel('Close')
            ->form([
                Placeholder::make('preview')
                    ->label('')
                    ->content(function (Media $record): string {
                        return view('filament.media.preview-pane', [
                            'record' => $record->loadMissing(['variantRecords', 'terms']),
                        ])->render();
                    })
                    ->columnSpanFull(),

                TextInput::make('title')->label('Title')->maxLength(255),
                TextInput::make('alt')->label('Alt text')->maxLength(255),
                Forms\Components\Textarea::make('caption')->label('Caption')->rows(2),
                Forms\Components\Textarea::make('description')->label('Description')->rows(3),

                FileUpload::make('replace_file')
                    ->label('Replace file (optional)')
                    ->storeFiles(false)
                    ->helperText('Replaces the original file and regenerates variants for images.'),
            ])
            ->fillForm(function (Media $record): array {
                return [
                    'title' => $record->title,
                    'alt' => $record->alt,
                    'caption' => $record->caption,
                    'description' => $record->description,
                ];
            })
            ->action(function (Media $record, array $data): void {
                $record->update([
                    'title' => (string) ($data['title'] ?? $record->title),
                    'alt' => (string) ($data['alt'] ?? $record->alt),
                    'caption' => (string) ($data['caption'] ?? $record->caption),
                    'description' => (string) ($data['description'] ?? $record->description),
                ]);

                $replace = $data['replace_file'] ?? null;

                if ($replace instanceof TemporaryUploadedFile || $replace instanceof UploadedFile) {
                    app(MediaUploader::class)->replace($record, $replace);
                }

                Notification::make()->title('Saved.')->success()->send();
                $this->resetTable();
            })
            ->extraModalFooterActions([
                Action::make('regenerate')
                    ->label('Regenerate variants')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn(Media $record) => $record->isImage())
                    ->action(function (Media $record): void {
                        $job = GenerateMediaVariants::dispatch($record->id, true);

                        $queueEnabled = (bool) config('cms-media.queue.enabled', true);
                        if ($queueEnabled) {
                            $connection = (string) config('cms-media.queue.connection', config('queue.default'));
                            $queue = (string) config('cms-media.queue.queue', 'media');
                            $job->onConnection($connection)->onQueue($queue);
                        }

                        Notification::make()->title('Variant regeneration queued.')->success()->send();
                    }),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn(Media $record) => MediaResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Media $record): void {
                        $record->delete();

                        Notification::make()->title('Deleted.')->success()->send();
                        $this->resetTable();
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        $bulkActions = $this->buildBulkActions();

        $table = $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['variantRecords', 'terms']))
            ->defaultSort('id', 'desc')
            ->searchDebounce(400)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->paginationPageOptions([24, 36, 48, 72])
            ->defaultPaginationPageOption(36)
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'image' => 'Images',
                        'video' => 'Video',
                        'pdf' => 'PDF',
                        'other' => 'Other',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $v = $data['value'] ?? null;

                        return match ($v) {
                            'image' => $query->where('mime_type', 'like', 'image/%'),
                            'video' => $query->where('mime_type', 'like', 'video/%'),
                            'pdf' => $query->where('mime_type', 'application/pdf'),
                            'other' => $query->where(function (Builder $q) {
                                    $q->where('mime_type', 'not like', 'image/%')
                                    ->where('mime_type', 'not like', 'video/%')
                                    ->where('mime_type', '!=', 'application/pdf');
                                }),
                            default => $query,
                        };
                    }),

                SelectFilter::make('folder')
                    ->label('Folder')
                    ->searchable()
                    ->preload()
                    ->options(fn(): array => $this->folderOptions())
                    ->query(function (Builder $query, array $data) {
                        $termId = filled($data['value'] ?? null) ? (int) $data['value'] : null;
                        if (!$termId) {
                            return $query;
                        }

                        return $query->whereHas('terms', fn(Builder $q) => $q->where('terms.id', $termId));
                    }),

                // ✅ NEW: Category filter
                SelectFilter::make('category')
                    ->label('Category')
                    ->searchable()
                    ->preload()
                    ->options(fn(): array => $this->categoryOptions())
                    ->query(function (Builder $query, array $data) {
                        $termId = filled($data['value'] ?? null) ? (int) $data['value'] : null;
                        if (!$termId) {
                            return $query;
                        }

                        return $query->whereHas('terms', fn(Builder $q) => $q->where('terms.id', $termId));
                    }),

                Filter::make('created_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(!empty($data['from']), fn(Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when(!empty($data['to']), fn(Builder $q) => $q->whereDate('created_at', '<=', $data['to']));
                    }),
            ]);

        // ✅ GRID MODE
        if ($this->viewMode === 'grid') {
            return $table
                ->columns([
                    Stack::make([
                        LayoutView::make('card')
                            ->view('filament.media.grid-card'),
                    ]),
                ])
                ->contentGrid([
                    'default' => 1, // ✅ let CSS handle layout
                ])
                ->recordUrl(null)
                ->recordAction(null)
                ->actions([
                    $this->previewAction(),
                ])
                ->bulkActions($this->selectMode ? $bulkActions : []);
        }

        // ✅ LIST MODE
        return $table
            ->columns([
                ViewColumn::make('thumb')
                    ->label('')
                    ->view('filament.media.list-thumb'),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('mime_type')->label('Type'),

                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn($state) => number_format(((int) $state) / 1024, 1) . ' KB')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions($bulkActions);
    }
}