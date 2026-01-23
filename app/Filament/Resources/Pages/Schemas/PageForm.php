<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
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
                Section::make('Content')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->schema([
                        TextInput::make('title')
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
                            ->helperText('Leave blank to auto-generate. If duplicate, it will auto-rename (e.g. about-2).')
                            ->maxLength(255),

                        Textarea::make('excerpt')
                            ->rows(6),
                    ]),

                // RIGHT (1/3)
                Section::make('Publish')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
                        CheckboxList::make('categories')
                            ->label('Categories')
                            ->relationship('categories', 'name')
                            ->columns(1),

                        Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'scheduled' => 'Scheduled',
                            ])
                            ->default('draft')
                            ->required(),

                        DateTimePicker::make('published_at')
                            ->label('Publish At')
                            ->seconds(false),
                    ]),
            ]);
    }
}