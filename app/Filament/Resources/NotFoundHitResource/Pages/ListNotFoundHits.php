<?php

namespace App\Filament\Resources\NotFoundHitResource\Pages;

use App\Filament\Resources\NotFoundHitResource;
use Filament\Resources\Pages\ListRecords;

class ListNotFoundHits extends ListRecords
{
    protected static string $resource = NotFoundHitResource::class;
}