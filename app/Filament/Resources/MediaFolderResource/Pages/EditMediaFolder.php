<?php

namespace App\Filament\Resources\MediaFolderResource\Pages;

use App\Filament\Resources\MediaFolderResource;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class EditMediaFolder extends EditRecord
{
    protected static string $resource = MediaFolderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Folder')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('parent_id')
                        ->label('Parent (optional)')
                        ->searchable()
                        ->preload()
                        ->options(
                            fn() => Term::query()
                                ->where('taxonomy_id', Taxonomy::idByKey('media_folder'))
                                ->where('id', '!=', $this->record->id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->nullable(),
                ]),
        ]);
    }
}