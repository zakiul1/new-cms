<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\EditAction;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;

class ListMultiPages extends ListRecords
{
    protected static string $resource = MultiPageResource::class;

    /**
     * ✅ Header button (top right)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Mega Pages')
                ->url(fn() => MultiPageResource::getUrl('create', panel: 'admin')),
        ];
    }

    /**
     * ✅ Force row click URL
     */
    protected function getTableRecordUrlUsing(): ?\Closure
    {
        return fn($record) => MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin');
    }

    /**
     * ✅ Force Edit action URL (the inline "Edit" action on the right)
     */
    protected function configureTableAction(\Filament\Tables\Actions\Action $action): void
    {
        parent::configureTableAction($action);

        if ($action instanceof EditAction) {
            $action->url(fn($record) => MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin'));
        }
    }
}