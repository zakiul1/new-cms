<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages\CreateMedia;
use App\Filament\Resources\MediaResource\Pages\EditMedia;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Filament\Resources\MediaResource\Pages\UploadMedia;
use App\Filament\Resources\MediaResource\Schemas\MediaForm;
use App\Models\Media;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;
    protected static ?string $navigationLabel = 'Media';
    protected static ?int $navigationSort = 50;
    protected static string|\UnitEnum|null $navigationGroup = 'Media';

    public static function form(Schema $schema): Schema
    {
        return MediaForm::configure($schema);
    }

    /**
     * ✅ Helper for CMS frontend admin bar
     * Use record model (recommended) to avoid routing issues.
     */
    public static function cms_edit_media_url(Media $media): string
    {
        return static::getUrl('edit', ['record' => $media]);
        // If you ever want to force key only:
        // return static::getUrl('edit', ['record' => $media->getKey()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'upload' => UploadMedia::route('/upload'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}