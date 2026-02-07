<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\Menu;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|UnitEnum|null $navigationGroup = 'CMS';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Menu Items';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $menuId = request()->integer('menu');
        if ($menuId > 0) {
            $query->where('menu_id', $menuId);
        }

        return $query;
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Schema $schema): Schema
    {
        $menuId = request()->integer('menu');

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Menu Item')
                    ->schema([
                        Select::make('menu_id')
                            ->label('Menu')
                            ->options(fn(): array => Menu::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default($menuId > 0 ? $menuId : null)
                            ->required()
                            ->disabled($menuId > 0),

                        Select::make('parent_id')
                            ->label('Parent')
                            ->options(function (Get $get): array {
                                $menuId = (int) $get('menu_id');
                                if ($menuId <= 0) {
                                    return [];
                                }

                                return MenuItem::query()
                                    ->where('menu_id', $menuId)
                                    ->orderBy('label')
                                    ->pluck('label', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->nullable(),

                        Select::make('type')
                            ->required()
                            ->options([
                                'custom_url' => 'Custom URL',
                                'heading' => 'Heading',
                                'separator' => 'Separator',
                            ])
                            ->default('custom_url')
                            ->live(),

                        TextInput::make('label')
                            ->required(fn(Get $get): bool => $get('type') !== 'separator')
                            ->maxLength(255),

                        TextInput::make('url')
                            ->label('URL')
                            ->required(fn(Get $get): bool => $get('type') === 'custom_url')
                            ->visible(fn(Get $get): bool => $get('type') === 'custom_url')
                            ->maxLength(2048),

                        Toggle::make('is_enabled')->default(true),

                        Textarea::make('data')
                            ->label('Advanced JSON (optional)')
                            ->helperText('Premium-ready fields like target/nofollow/visibility/mega can be stored here as JSON.')
                            ->dehydrateStateUsing(function ($state) {
                                if (blank($state)) {
                                    return null;
                                }

                                $decoded = json_decode((string) $state, true);

                                return is_array($decoded) ? $decoded : null;
                            })
                            ->formatStateUsing(fn($state) => is_array($state)
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : '')
                            ->rows(8)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(), // ✅ KEY FIX: span both outer grid columns
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('url')->limit(40),
                ToggleColumn::make('is_enabled'),
                TextColumn::make('parent_id')
                    ->label('Parent')
                    ->formatStateUsing(fn($s) => $s ? "#{$s}" : '-'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),

                Action::make('delete')
                    ->requiresConfirmation()
                    ->action(fn(MenuItem $record) => $record->delete()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}