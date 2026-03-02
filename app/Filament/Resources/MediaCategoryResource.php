<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaCategoryResource\Pages;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use UnitEnum;

class MediaCategoryResource extends Resource
{
    protected static ?string $model = Term::class;

    // ✅ Show under Media group, under "Media"
    protected static string|UnitEnum|null $navigationGroup = 'Media';
    protected static ?int $navigationSort = 51;
    protected static ?string $navigationLabel = 'Media Categories';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function getModelLabel(): string
    {
        return 'Media Category';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Media Categories';
    }

    protected static function mediaCategoryTaxonomyId(): int
    {
        return (int) Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        )->id;
    }

    public static function getEloquentQuery(): Builder
    {
        $taxonomyId = static::mediaCategoryTaxonomyId();

        return parent::getEloquentQuery()
            ->where('taxonomy_id', $taxonomyId)
            // ✅ preload media count for "Name (25)"
            ->withCount([
                'media as items_count',
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->components([
                Section::make('Category')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (!filled($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }

                                // ✅ auto-fill Product from Name (only if empty)
                                if (!filled($get('product'))) {
                                    $set('product', (string) $state);
                                }
                            }),

                        TextInput::make('product')
                            ->label('Product')
                            ->maxLength(255)
                            ->helperText('Defaults to Name. You can change it.'),

                        TextInput::make('slug')
                            ->label('Slug (optional)')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Set $set) => $set('slug', filled($state) ? Str::slug((string) $state) : null))
                            ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null),

                        Select::make('visibility')
                            ->label('Visibility')
                            ->options([
                                'public' => 'Public (can be shown on frontend)',
                                'private' => 'Private (admin/internal only)',
                            ])
                            ->default('public')
                            ->required(),

                        Select::make('parent_id')
                            ->label('Parent (optional)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (): array {
                                $taxonomyId = static::mediaCategoryTaxonomyId();

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * ✅ NEW: Download selected categories as ZIP with category folders.
     */
    protected static function downloadSelectedCategoriesZip(Collection $records)
    {
        $records = $records->values();

        if ($records->isEmpty()) {
            return null;
        }

        if (!class_exists(\ZipArchive::class)) {
            Notification::make()->title('ZipArchive not available on server')->danger()->send();
            return null;
        }

        $disk = config('cms-media.disk', 'public');

        $zipName = 'media-categories-' . now()->format('Ymd-His') . '.zip';
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

        $taxonomyId = static::mediaCategoryTaxonomyId();

        foreach ($records as $term) {
            /** @var \App\Models\Term $term */

            // folder name: prefer slug then name
            $folder = (string) ($term->slug ?: $term->name ?: ('category-' . $term->id));
            $folder = Str::slug($folder);
            if ($folder === '') {
                $folder = 'category-' . $term->id;
            }

            // Get media IDs for this category via pivot (fast)
            $mediaIds = DB::table('termables')
                ->where('term_id', (int) $term->id)
                ->where('termable_type', Media::class)
                ->pluck('termable_id')
                ->map(fn($v) => (int) $v)
                ->values()
                ->all();

            if (empty($mediaIds)) {
                continue;
            }

            // Load media models (so we can call path()/filename etc)
            $mediaItems = Media::query()
                ->whereIn('id', $mediaIds)
                ->get();

            foreach ($mediaItems as $m) {
                /** @var \App\Models\Media $m */

                $path = method_exists($m, 'path') ? $m->path() : (string) ($m->path ?? '');
                $path = trim((string) $path);

                if ($path === '') {
                    continue;
                }

                // name inside zip
                $name = (string) ($m->original_filename ?? $m->filename ?? basename($path));
                $name = trim($name) !== '' ? $name : ('media-' . $m->id);

                // ensure unique + keep grouped in folder
                $zipFileName = $folder . '/' . $name;

                // If duplicate name already exists in same category folder, put it under duplicates/<id>/ but keep filename unchanged
                if ($zip->locateName($zipFileName) !== false) {
                    $zipFileName = $folder . '/duplicates/' . $m->id . '/' . $name;
                }

                try {
                    $abs = Storage::disk($disk)->path($path);
                    if (is_file($abs)) {
                        $zip->addFile($abs, $zipFileName);
                        continue;
                    }
                } catch (\Throwable $e) {
                    // ignore and fall back
                }

                // fallback: read content
                try {
                    $content = Storage::disk($disk)->get($path);
                    $zip->addFromString($zipFileName, $content);
                } catch (\Throwable $e) {
                    // skip broken file
                }
            }
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state, Term $record): string {
                        $count = (int) ($record->items_count ?? 0);
                        return "{$state} ({$count})";
                    }),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->toggleable(),

                TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                // ✅ NEW: Download selected categories -> zip (category folders)
                BulkAction::make('download_categories')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records) {
                        return static::downloadSelectedCategoriesZip($records);
                    }),

                // Copy media items from selected categories → target category
                BulkAction::make('copyItemsToCategory')
                    ->label('Copy items to…')
                    ->icon('heroicon-o-document-duplicate')
                    ->form([
                        Select::make('target_term_id')
                            ->label('Target media category')
                            ->required()
                            ->searchable()
                            ->options(function (): array {
                                $taxonomyId = static::mediaCategoryTaxonomyId();

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            }),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $targetId = (int) $data['target_term_id'];

                        if ($records->isEmpty()) {
                            return;
                        }

                        $sourceIds = $records->pluck('id')->map(fn($v) => (int) $v)->values()->all();

                        // All media IDs in ANY selected categories
                        $mediaIds = DB::table('termables')
                            ->whereIn('term_id', $sourceIds)
                            ->where('termable_type', Media::class)
                            ->pluck('termable_id')
                            ->unique()
                            ->values()
                            ->all();

                        if (empty($mediaIds)) {
                            return;
                        }

                        // Attach to target without detaching from source categories
                        $rows = array_map(fn($mediaId) => [
                            'term_id' => $targetId,
                            'termable_type' => Media::class,
                            'termable_id' => (int) $mediaId,
                        ], $mediaIds);

                        DB::table('termables')->insertOrIgnore($rows);
                    }),

                // Move media items from selected categories → target category
                BulkAction::make('moveItemsToCategory')
                    ->label('Move items to…')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->form([
                        Select::make('target_term_id')
                            ->label('Target media category')
                            ->required()
                            ->searchable()
                            ->options(function (): array {
                                $taxonomyId = static::mediaCategoryTaxonomyId();

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            }),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $targetId = (int) $data['target_term_id'];

                        if ($records->isEmpty()) {
                            return;
                        }

                        $sourceIds = $records->pluck('id')->map(fn($v) => (int) $v)->values()->all();

                        // All media IDs in ANY selected categories
                        $mediaIds = DB::table('termables')
                            ->whereIn('term_id', $sourceIds)
                            ->where('termable_type', Media::class)
                            ->pluck('termable_id')
                            ->unique()
                            ->values()
                            ->all();

                        if (empty($mediaIds)) {
                            return;
                        }

                        DB::transaction(function () use ($sourceIds, $targetId, $mediaIds) {
                            // 1) Detach ONLY the selected categories from these media items
                            DB::table('termables')
                                ->whereIn('term_id', $sourceIds)
                                ->where('termable_type', Media::class)
                                ->whereIn('termable_id', $mediaIds)
                                ->delete();

                            // 2) Attach to target category
                            $rows = array_map(fn($mediaId) => [
                                'term_id' => $targetId,
                                'termable_type' => Media::class,
                                'termable_id' => (int) $mediaId,
                            ], $mediaIds);

                            DB::table('termables')->insertOrIgnore($rows);
                        });
                    }),

                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaCategories::route('/'),
            'create' => Pages\CreateMediaCategory::route('/create'),
            'edit' => Pages\EditMediaCategory::route('/{record}/edit'),
        ];
    }
}