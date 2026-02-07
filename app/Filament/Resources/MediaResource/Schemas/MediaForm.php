<?php

namespace App\Filament\Resources\MediaResource\Schemas;

use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        $maxMb = (int) config('cms-media.max_upload_mb', 50);

        return $schema
            ->columns([
                'default' => 1,
                'lg' => 1, // full width
            ])
            ->components([
                // ✅ Attachment page controls (only relevant when editing a single media record)
                Section::make('Attachment Page')
                    ->description('Public attachment page uses /{slug}. You can hide it per media like WordPress.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('attachment_public')
                            ->label('Public attachment page')
                            ->helperText('If OFF, visiting /{slug} will return 404 (even if global attachment pages are enabled).')
                            ->default(true),

                        Toggle::make('attachment_indexable')
                            ->label('Indexable (SEO)')
                            ->helperText('If OFF, robots meta becomes noindex, follow for this attachment.')
                            ->default(true),

                        TextInput::make('slug')
                            ->label('Attachment slug')
                            ->helperText('This controls the public attachment page URL: /{slug}.')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                $state = trim((string) $state);
                                if ($state === '') {
                                    return;
                                }
                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('attachment_url_preview')
                            ->label('Attachment URL (preview)')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function (Get $get) {
                                $slug = trim((string) $get('slug'));
                                if ($slug === '') {
                                    return '';
                                }

                                return url('/' . $slug);
                            })
                            ->helperText('This is what the public URL will be (if enabled + public).'),
                    ])
                    ->visible(fn(Get $get) => true),

                // ✅ Upload section
                Section::make('Upload')
                    ->columnSpanFull()
                    ->schema([
                        // ✅ Folder selector (existing)
                        Select::make('folder_term_id')
                            ->label('Folder (optional)')
                            ->helperText('Upload into a folder like WordPress. Leave empty for Uncategorized.')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (): array {
                                $taxonomyId = Taxonomy::firstOrCreate(
                                    ['key' => 'media_folder'],
                                    ['label' => 'Media Folders', 'hierarchical' => true],
                                )->id;

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('slug', Str::slug((string) $state));
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->maxLength(255),

                                Select::make('parent_id')
                                    ->label('Parent Folder (optional)')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->options(function (): array {
                                        $taxonomyId = Taxonomy::where('key', 'media_folder')->value('id');
                                        if (!$taxonomyId) {
                                            return [];
                                        }

                                        return Term::query()
                                            ->where('taxonomy_id', $taxonomyId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    }),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $taxonomyId = Taxonomy::firstOrCreate(
                                    ['key' => 'media_folder'],
                                    ['label' => 'Media Folders', 'hierarchical' => true],
                                )->id;

                                $base = filled($data['slug'] ?? null)
                                    ? Str::slug((string) $data['slug'])
                                    : Str::slug((string) ($data['name'] ?? ''));

                                $base = $base !== '' ? $base : 'folder';

                                $slug = $base;
                                $i = 2;

                                while (Term::where('taxonomy_id', $taxonomyId)->where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i;
                                    $i++;
                                }

                                $term = Term::create([
                                    'taxonomy_id' => $taxonomyId,
                                    'name' => (string) $data['name'],
                                    'slug' => $slug,
                                    'parent_id' => $data['parent_id'] ?? null,
                                ]);

                                return $term->getKey();
                            }),

                        // ✅ NEW: Category selector (multi) + runtime create option
                        Select::make('category_term_ids')
                            ->label('Categories (optional)')
                            ->helperText('Select media categories (max 10 shown later in Related Links). You can create categories from here.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (): array {
                                $taxonomyId = Taxonomy::firstOrCreate(
                                    ['key' => 'media_category'],
                                    ['label' => 'Media Categories', 'hierarchical' => true],
                                )->id;

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('slug', Str::slug((string) $state));
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->maxLength(255),

                                Select::make('parent_id')
                                    ->label('Parent Category (optional)')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->options(function (): array {
                                        $taxonomyId = Taxonomy::where('key', 'media_category')->value('id');
                                        if (!$taxonomyId) {
                                            return [];
                                        }

                                        return Term::query()
                                            ->where('taxonomy_id', $taxonomyId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    }),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $taxonomyId = Taxonomy::firstOrCreate(
                                    ['key' => 'media_category'],
                                    ['label' => 'Media Categories', 'hierarchical' => true],
                                )->id;

                                $base = filled($data['slug'] ?? null)
                                    ? Str::slug((string) $data['slug'])
                                    : Str::slug((string) ($data['name'] ?? ''));

                                $base = $base !== '' ? $base : 'category';

                                $slug = $base;
                                $i = 2;

                                while (Term::where('taxonomy_id', $taxonomyId)->where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i;
                                    $i++;
                                }

                                $term = Term::create([
                                    'taxonomy_id' => $taxonomyId,
                                    'name' => (string) $data['name'],
                                    'slug' => $slug,
                                    'parent_id' => $data['parent_id'] ?? null,
                                ]);

                                return $term->getKey();
                            }),

                        // ✅ Upload
                        FileUpload::make('files')
                            ->label('Upload files')
                            ->required()
                            ->multiple()
                            ->storeFiles(false) // ✅ IMPORTANT: keep TemporaryUploadedFile objects
                            ->reorderable()
                            ->appendFiles()
                            ->imagePreviewHeight('120')
                            ->panelLayout('grid') // ✅ grid preview
                            ->maxSize($maxMb * 1024)
                            ->helperText("Drag & drop. Max upload size: {$maxMb} MB each.")
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}