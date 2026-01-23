<?php

namespace App\Filament\Resources\MediaResource\Schemas;

use App\Models\Media;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                // LEFT (2/3)
                Section::make('Upload')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->schema([
                        // ✅ WP-like multi uploader (Create only)
                        FileUpload::make('files')
                            ->label('Upload files')
                            ->multiple()
                            ->required(fn (?Media $record) => $record === null)
                            ->visible(fn (?Media $record) => $record === null) // hide on edit
                            ->storeFiles(false) // IMPORTANT: keep TemporaryUploadedFile objects
                            ->maxSize((int) config('cms-media.max_upload_mb', 50) * 1024)
                            ->helperText('Drag & drop multiple files (WordPress style).'),

                        // simple note on create
                        Placeholder::make('hint')
                            ->visible(fn (?Media $record) => $record === null)
                            ->content('After upload, items will appear in the Media list. Click any item to edit details.'),
                    ]),

                // RIGHT (1/3) - Metadata (Edit)
                Section::make('Details')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->visible(fn (?Media $record) => $record !== null) // only on edit
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(255),

                        TextInput::make('alt')
                            ->label('Alt text')
                            ->maxLength(255),

                        Textarea::make('caption')
                            ->rows(3),

                        Textarea::make('description')
                            ->rows(5),
                    ]),
            ]);
    }
}
