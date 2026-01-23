<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (!filled($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->label('Slug (optional)')
                ->helperText('Leave blank to auto-generate. Duplicates will auto-rename.')
                ->maxLength(255),

            Textarea::make('description')
                ->rows(5),
        ]);
    }
}