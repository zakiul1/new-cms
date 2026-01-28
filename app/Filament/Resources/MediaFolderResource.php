<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaFolderResource\Pages;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class MediaFolderResource extends Resource
{
    protected static ?string $model = Term::class;

    // ✅ Filament v5 expects: BackedEnum|string|null
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Media Folders';

    // ✅ Filament v5 expects: UnitEnum|string|null
    protected static \UnitEnum|string|null $navigationGroup = 'Media';

    protected static ?int $navigationSort = 51;

    public static function getEloquentQuery(): Builder
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_folder')->value('id');

        if (!$taxonomyId) {
            return parent::getEloquentQuery()->whereRaw('1=0');
        }

        return parent::getEloquentQuery()
            ->where('taxonomy_id', $taxonomyId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaFolders::route('/'),
            'create' => Pages\CreateMediaFolder::route('/create'),
            'edit' => Pages\EditMediaFolder::route('/{record}/edit'),
        ];
    }
}