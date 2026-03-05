<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\View as LayoutView;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    public string $viewMode = 'list'; // grid | list
    public bool $selectMode = false;  // bulk actions only when true (grid only)

    public function mount(): void
    {
        parent::mount();

        $this->viewMode = (string) session()->get('media.viewMode', 'list');
        $this->selectMode = (bool) session()->get('media.selectMode', false);

        if (!in_array($this->viewMode, ['grid', 'list'], true)) {
            $this->viewMode = 'list';
        }

        if ($this->viewMode !== 'grid') {
            $this->selectMode = false;
            session()->put('media.selectMode', false);
        }
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'list';
        session()->put('media.viewMode', $this->viewMode);

        if ($this->viewMode !== 'grid') {
            $this->selectMode = false;
            session()->put('media.selectMode', false);
        }

        $this->resetTable();
        $this->resetTablePage();
    }

    protected function toggleSelectMode(): void
    {
        if ($this->viewMode !== 'grid') {
            $this->viewMode = 'grid';
            session()->put('media.viewMode', 'grid');
        }

        $this->selectMode = !$this->selectMode;
        session()->put('media.selectMode', $this->selectMode);

        $this->resetTable();
        $this->resetTablePage();
    }

    protected function mediaCategoryTaxonomyId(): ?int
    {
        return Taxonomy::query()->where('key', 'media_category')->value('id');
    }

    /**
     * Build hierarchical category options like:
     * Parent (12)
     * __Child (5)
     * ____Grandchild (2)
     *
     * Counts include children counts (parent shows total under it).
     */
    protected function categoryOptions(): array
    {
        $taxonomyId = $this->mediaCategoryTaxonomyId();
        if (!$taxonomyId) {
            return [];
        }

        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->withCount('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($terms->isEmpty()) {
            return [];
        }

        // Build adjacency list keyed by parent_id
        $byParent = [];
        foreach ($terms as $t) {
            $pid = (int) ($t->parent_id ?? 0);
            $byParent[$pid] ??= [];
            $byParent[$pid][] = $t;
        }

        // Recursive: compute total counts (self + descendants) and build option labels
        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$options, $byParent): int {
            $children = $byParent[$parentId] ?? [];
            $totalForThisLevel = 0;

            foreach ($children as $term) {
                /** @var \App\Models\Term $term */
                $selfCount = (int) ($term->media_count ?? 0);

                // descendants total
                $descCount = $walk((int) $term->id, $depth + 1);

                $total = $selfCount + $descCount;

                $prefix = $depth > 0 ? str_repeat('__', $depth) . ' ' : '';
                $label = $prefix . (string) $term->name . ' (' . $total . ')';

                $options[(int) $term->id] = $label;

                $totalForThisLevel += $total;
            }

            return $totalForThisLevel;
        };

        // Root parent_id can be null or 0 depending on your data
        $walk(0, 0);

        // Some datasets use NULL parent_id but not 0
        if (isset($byParent[0]) === false && isset($byParent[(int) null])) {
            $walk((int) null, 0);
        }

        return $options;
    }

    /**
     * ✅ NEW: Download selected media as single file OR zip.
     */
    protected function downloadSelectedMedia(Collection $records)
    {
        $records = $records->values();

        if ($records->isEmpty()) {
            return null;
        }

        $disk = config('cms-media.disk', 'public');

        // ✅ 1 item => direct download
        if ($records->count() === 1) {
            /** @var \App\Models\Media $m */
            $m = $records->first();

            $path = method_exists($m, 'path') ? $m->path() : (string) ($m->path ?? '');
            $path = trim((string) $path);

            if ($path === '') {
                Notification::make()->title('File path missing')->danger()->send();
                return null;
            }

            $name = (string) ($m->original_filename ?? $m->filename ?? basename($path));
            $name = trim($name) !== '' ? $name : ('media-' . $m->id);

            try {
                $abs = Storage::disk($disk)->path($path);
                if (is_file($abs)) {
                    return response()->download($abs, $name);
                }
            } catch (\Throwable $e) {
                // ignore and fall back to disk download
            }

            return Storage::disk($disk)->download($path, $name);
        }

        // ✅ multiple => zip
        if (!class_exists(\ZipArchive::class)) {
            Notification::make()->title('ZipArchive not available on server')->danger()->send();
            return null;
        }

        $zipName = 'media-' . now()->format('Ymd-His') . '.zip';

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Notification::make()->title('Could not create zip file')->danger()->send();
            return null;
        }

        foreach ($records as $m) {
            /** @var \App\Models\Media $m */
            $path = method_exists($m, 'path') ? $m->path() : (string) ($m->path ?? '');
            $path = trim((string) $path);

            if ($path === '') {
                continue;
            }

            $name = (string) ($m->original_filename ?? $m->filename ?? basename($path));
            $name = trim($name) !== '' ? $name : ('media-' . $m->id);

            // ensure unique inside zip
            $zipFileName = $name;

            // If duplicate name already exists in zip, put it under duplicates/<id>/ but keep same filename
            if ($zip->locateName($zipFileName) !== false) {
                $zipFileName = 'duplicates/' . $m->id . '/' . $name;
            }

            try {
                $abs = Storage::disk($disk)->path($path);
                if (is_file($abs)) {
                    $zip->addFile($abs, $zipFileName);
                    continue;
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // fallback: read content (still OK for public local disk most times)
            try {
                $content = Storage::disk($disk)->get($path);
                $zip->addFromString($zipFileName, $content);
            } catch (\Throwable $e) {
                // skip broken file
            }
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    protected function buildBulkActions(): array
    {
        return [
            // ✅ NEW: Download bulk action
            \Filament\Actions\BulkAction::make('download_selected')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (Collection $records) {
                    return $this->downloadSelectedMedia($records);
                }),

            \Filament\Actions\BulkAction::make('copy_to_media_category')
                ->label('Copy to Category')
                ->icon('heroicon-o-document-duplicate')
                ->form([
                    Select::make('category_id')
                        ->label('Target Category')
                        ->options(fn() => $this->categoryOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Collection $records, array $data): void {
                    $taxonomyId = Taxonomy::idByKey('media_category');
                    $categoryId = (int) ($data['category_id'] ?? 0);

                    if (!$taxonomyId || $categoryId <= 0) {
                        return;
                    }

                    $valid = Term::query()
                        ->where('taxonomy_id', $taxonomyId)
                        ->where('id', $categoryId)
                        ->exists();

                    if (!$valid) {
                        Notification::make()->title('Invalid category selected')->danger()->send();
                        return;
                    }

                    foreach ($records as $media) {
                        /** @var \App\Models\Media $media */
                        $media->terms()->syncWithoutDetaching([$categoryId]);
                    }
                })
                ->deselectRecordsAfterCompletion(),

            \Filament\Actions\BulkAction::make('move_to_media_category')
                ->label('Move to Category')
                ->icon('heroicon-o-arrow-right')
                ->color('warning')
                ->requiresConfirmation()
                ->form([
                    Select::make('category_id')
                        ->label('Target Category')
                        ->options(fn() => $this->categoryOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Collection $records, array $data): void {
                    $taxonomyId = Taxonomy::idByKey('media_category');
                    $categoryId = (int) ($data['category_id'] ?? 0);

                    if (!$taxonomyId || $categoryId <= 0) {
                        return;
                    }

                    $valid = Term::query()
                        ->where('taxonomy_id', $taxonomyId)
                        ->where('id', $categoryId)
                        ->exists();

                    if (!$valid) {
                        Notification::make()->title('Invalid category selected')->danger()->send();
                        return;
                    }

                    // Detach ONLY media_category terms (don’t touch other taxonomies)
                    $allMediaCategoryTermIds = Term::query()
                        ->where('taxonomy_id', $taxonomyId)
                        ->pluck('id')
                        ->all();

                    foreach ($records as $media) {
                        /** @var \App\Models\Media $media */
                        if (!empty($allMediaCategoryTermIds)) {
                            $media->terms()->detach($allMediaCategoryTermIds);
                        }

                        $media->terms()->syncWithoutDetaching([$categoryId]);
                    }
                })
                ->deselectRecordsAfterCompletion(),

            DeleteBulkAction::make(),
        ];
    }

    protected function frontendUrl(Media $record): string
    {
        return filled($record->slug)
            ? (function_exists('cms_slug_url')
                ? cms_slug_url((string) $record->slug)
                : url('/' . trim((string) $record->slug, '/') . '/'))
            : $record->url();
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

            Action::make('upload')
                ->label('Upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(MediaResource::getUrl('upload')),
        ];
    }

    public function table(Table $table): Table
    {
        $bulkActions = $this->buildBulkActions();
        $catTaxId = $this->mediaCategoryTaxonomyId();

        $table = $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['variantRecords', 'terms']))
            ->defaultSort('id', 'desc')
            ->searchDebounce(400)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->paginationPageOptions([24, 36, 48, 72])
            ->defaultPaginationPageOption(36)
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->filtersTriggerAction(function ($action) {
                return $action
                    ->label('Category')
                    ->icon('heroicon-o-tag');
            })
            ->selectable()
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->searchable()
                    ->preload()
                    ->options(fn(): array => ['__uncat__' => 'Uncategorized'] + $this->categoryOptions())
                    ->query(function (Builder $query, array $data) use ($catTaxId) {
                        $value = $data['value'] ?? null;

                        if (!filled($value)) {
                            return $query;
                        }

                        if ($value === '__uncat__') {
                            if (!$catTaxId) {
                                return $query;
                            }

                            return $query->whereDoesntHave(
                                'terms',
                                fn(Builder $q) => $q->where('terms.taxonomy_id', $catTaxId)
                            );
                        }

                        return $query->whereHas('terms', fn(Builder $q) => $q->where('terms.id', (int) $value));
                    }),
            ]);

        // GRID MODE
        if ($this->viewMode === 'grid') {
            return $table
                ->recordClasses(fn() => 'min-w-0')
                ->columns([
                    Stack::make([
                        LayoutView::make('card')->view('filament.media.grid-card'),
                    ]),
                ])
                ->contentGrid([
                    'default' => 2,
                    'sm' => 3,
                    'md' => 4,
                    'lg' => 5,
                    'xl' => 6,
                ])
                ->recordUrl(fn(Media $record) => MediaResource::getUrl('edit', ['record' => $record]))
                ->recordAction(null)
                ->actions([])
                ->bulkActions($bulkActions);
        }

        // LIST MODE
        return $table
            ->recordClasses(fn() => 'group')
            ->columns([
                ViewColumn::make('thumb')
                    ->label('')
                    ->view('filament.media.list-thumb'),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(false)
                    ->limit(40)
                    ->extraAttributes(['class' => 'min-w-0 max-w-[320px]'])
                    ->description(function (Media $record): HtmlString {
                        $editUrl = MediaResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = $this->frontendUrl($record);

                        $actions =
                            '<div class="mt-1 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">' .
                            '<a class="hover:underline text-primary-600" href="' . e($editUrl) . '">Edit</a>' .
                            ' <span class="text-slate-300">|</span> ' .
                            '<a class="hover:underline text-slate-600" href="' . e($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a>' .
                            '</div>';

                        return new HtmlString($actions);
                    }),

                TagsColumn::make('categories')
                    ->label('Categories')
                    ->state(function (Media $record) use ($catTaxId): array {
                        if (!$catTaxId) {
                            return [];
                        }

                        return $record->terms
                            ->filter(fn($t) => (int) $t->taxonomy_id === (int) $catTaxId)
                            ->pluck('name')
                            ->filter(fn($name) => is_string($name) && trim($name) !== '')
                            ->map(fn($name) => trim($name))
                            ->values()
                            ->all();
                    })
                    ->separator(',')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable()
                    ->extraAttributes(['class' => 'whitespace-nowrap']),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Trash')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->successNotificationTitle('Moved to trash'),
            ])
            ->bulkActions($bulkActions);
    }
}