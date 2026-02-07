<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|UnitEnum|null $navigationGroup = 'CMS';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel = 'Menus';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Menu')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(), // ✅ KEY FIX: span both outer grid columns
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('items')
                    ->label('Items')
                    ->icon('heroicon-o-rectangle-stack')
                    ->url(fn(Menu $record): string => MenuItemResource::getUrl('index', ['menu' => $record->id])),

                EditAction::make(),
            ]);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}