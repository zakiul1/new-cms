<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\View as LayoutView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    public string $viewMode = 'list'; // grid | list
    public bool $selectMode = false;  // checkboxes + bulk actions only when true (grid only)

    public function mount(): void
    {
        parent::mount();

        $this->viewMode = (string) session()->get('media.viewMode', 'list');
        $this->selectMode = (bool) session()->get('media.selectMode', false);

        if (!in_array($this->viewMode, ['grid', 'list'], true)) {
            $this->viewMode = 'list';
        }

        // leaving grid => stop select mode (WP-like)
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

    protected function mediaCategoryTaxonomyId(): ?int
    {
        return Taxonomy::query()->where('key', 'media_category')->value('id');
    }

    protected function mediaFolderTaxonomyId(): ?int
    {
        return Taxonomy::query()->where('key', 'media_folder')->value('id');
    }

    // --- Header Actions (WP-like) ---
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

            // ✅ NEW: WP-like Upload page (NOT modal)
            Action::make('upload')
                ->label('Upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(MediaResource::getUrl('upload')),
        ];
    }

    protected function buildBulkActions(): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        $bulkActions = $this->buildBulkActions();
        $catTaxId = $this->mediaCategoryTaxonomyId();
        $folderTaxId = $this->mediaFolderTaxonomyId();

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

                // ✅ Category filter + "Uncategorized"
                SelectFilter::make('category')
                    ->label('Category')
                    ->searchable()
                    ->preload()
                    ->options(function (): array {
                        return ['__uncat__' => 'Uncategorized'] + $this->categoryOptions();
                    })
                    ->query(function (Builder $query, array $data) use ($catTaxId) {
                        $value = $data['value'] ?? null;

                        if (!filled($value)) {
                            return $query;
                        }

                        // Uncategorized: no media_category term attached
                        if ($value === '__uncat__') {
                            if (!$catTaxId) {
                                return $query;
                            }

                            return $query->whereDoesntHave('terms', fn(Builder $q) => $q->where('terms.taxonomy_id', $catTaxId));
                        }

                        $termId = (int) $value;

                        return $query->whereHas('terms', fn(Builder $q) => $q->where('terms.id', $termId));
                    }),

                // ✅ Folder filter + "No folder"
                SelectFilter::make('folder')
                    ->label('Folder')
                    ->searchable()
                    ->preload()
                    ->options(function (): array {
                        return ['__none__' => 'No folder'] + $this->folderOptions();
                    })
                    ->query(function (Builder $query, array $data) use ($folderTaxId) {
                        $value = $data['value'] ?? null;

                        if (!filled($value)) {
                            return $query;
                        }

                        if ($value === '__none__') {
                            if (!$folderTaxId) {
                                return $query;
                            }

                            return $query->whereDoesntHave('terms', fn(Builder $q) => $q->where('terms.taxonomy_id', $folderTaxId));
                        }

                        $termId = (int) $value;

                        return $query->whereHas('terms', fn(Builder $q) => $q->where('terms.id', $termId));
                    }),
            ]);

        // ✅ GRID MODE (WP-like: click opens Edit page, no drawer/preview action)
        if ($this->viewMode === 'grid') {
            return $table
                ->columns([
                    Stack::make([
                        LayoutView::make('card')->view('filament.media.grid-card'),
                    ]),
                ])
                ->contentGrid(['default' => 1])
                ->recordUrl(fn(Media $record) => MediaResource::getUrl('edit', ['record' => $record]))
                ->recordAction(null)
                ->actions([])
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

                // ✅ Categories as badges (multi)
                TextColumn::make('categories')
                    ->label('Categories')
                    ->state(function (Media $record) use ($catTaxId) {
                        if (!$catTaxId) {
                            return [];
                        }

                        return $record->terms
                            ->filter(fn($t) => (int) $t->taxonomy_id === (int) $catTaxId)
                            ->pluck('name')
                            ->values()
                            ->all();
                    })
                    ->formatStateUsing(fn($state) => is_array($state) ? $state : (blank($state) ? [] : [$state]))
                    ->separator(' ')
                    ->badge()
                    ->toggleable()
                    ->placeholder('—'),

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