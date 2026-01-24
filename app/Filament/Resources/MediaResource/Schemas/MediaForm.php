<?php

namespace App\Filament\Resources\MediaResource\Schemas;

use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\FileUpload;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Section;

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
                Section::make('Upload')
                    ->columnSpanFull()
                    ->schema([
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
                                \Filament\Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('slug', Str::slug((string) $state));
                                    }),

                                \Filament\Forms\Components\TextInput::make('slug')
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

                        FileUpload::make('files')
                            ->label('Upload files')
                            ->required()
                            ->multiple()
                            ->storeFiles(false) // ✅ IMPORTANT: keep TemporaryUploadedFile objects
                            ->reorderable()
                            ->appendFiles()
                            ->imagePreviewHeight('120')
                            ->panelLayout('grid') // ✅ grid preview (much better than tall list)
                            ->maxSize($maxMb * 1024)
                            ->helperText("Drag & drop. Max upload size: {$maxMb} MB each.")
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}