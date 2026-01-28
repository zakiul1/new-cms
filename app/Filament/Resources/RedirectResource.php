<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static string|\UnitEnum|null $navigationGroup = 'SEO';
    protected static ?string $navigationLabel = 'Redirects';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Redirect')
                ->description('Create 301/302 redirects (premium SEO feature).')
                ->schema([
                    TextInput::make('from_path')
                        ->label('From Path')
                        ->helperText('Example: /old-page')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->dehydrateStateUsing(function ($state) {
                            $p = trim((string) $state);
                            if ($p === '')
                                return $p;
                            if ($p === '/')
                                return '/';
                            $p = '/' . ltrim($p, '/');
                            return rtrim($p, '/'); // normalize /about/
                        }),

                    TextInput::make('to_path')
                        ->label('To Path')
                        ->helperText('Example: /new-page or https://example.com/new-page')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->dehydrateStateUsing(function ($state) {
                            $p = trim((string) $state);
                            if ($p === '')
                                return $p;

                            if (Str::startsWith($p, ['http://', 'https://'])) {
                                return $p;
                            }

                            if ($p === '/')
                                return '/';
                            $p = '/' . ltrim($p, '/');
                            return rtrim($p, '/');
                        }),

                    Select::make('status_code')
                        ->label('Status Code')
                        ->options([
                            301 => '301 (Permanent)',
                            302 => '302 (Temporary)',
                            307 => '307 (Temporary, strict)',
                            308 => '308 (Permanent, strict)',
                        ])
                        ->default(301)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')
                    ->label('From')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('to_path')
                    ->label('To')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('status_code')
                    ->label('Code')
                    ->sortable(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\CreateAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}