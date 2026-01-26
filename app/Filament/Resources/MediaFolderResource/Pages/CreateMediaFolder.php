<?php

namespace App\Filament\Resources\MediaFolderResource\Pages;

use App\Filament\Resources\MediaFolderResource;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CreateMediaFolder extends CreateRecord
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
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->nullable(),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure taxonomy exists
        $taxonomy = Taxonomy::ensure('media_folder', 'Media Folders', true);

        $data['taxonomy_id'] = $taxonomy->id;

        return $data;
    }
}