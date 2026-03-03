<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;
use Plugins\MultiPage\Support\MultiPageGenerator;

class CreateMultiPage extends CreateRecord
{
    protected static string $resource = MultiPageResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    /** @var int[] */
    protected array $productMediaIds = [];

    /**
     * ✅ Header buttons: Create + Cancel/Back
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_record')
                ->label('Create')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action(function (): void {
                    $this->create();

                    $record = $this->getRecord();
                    if ($record) {
                        $this->redirect(MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin'));
                    }
                }),

            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->icon('heroicon-o-x-mark')
                ->url(fn() => MultiPageResource::getUrl('index', panel: 'admin')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ MultiPage is its own custom post type now
        $data['type'] = 'multipage';

        // ✅ FIX: posts.author_id is required in DB
        $data['author_id'] = $data['author_id']
            ?? Auth::id()
            ?? 1;

        // ✅ Capture Featured Images (MediaPicker field)
        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $data['featured_media_ids'])))
            : [];
        unset($data['featured_media_ids']);

        // ✅ Capture Product Images (MediaPicker field)
        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $data['product_media_ids'])))
            : [];
        unset($data['product_media_ids']);

        // ✅ Keep legacy single featured_media_id synced (used in some theme helpers)
        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        // ✅ normalize json columns
        $data['meta_json'] = is_array($data['meta_json'] ?? null) ? $data['meta_json'] : [];
        $data['content_json'] = is_array($data['content_json'] ?? null) ? $data['content_json'] : [];

        // ✅ ensure multipage settings exist
        $data['meta_json']['multipage'] = is_array($data['meta_json']['multipage'] ?? null)
            ? $data['meta_json']['multipage']
            : [];

        // ✅ default enable multipage for this resource
        $data['meta_json']['multipage']['enabled'] = true;

        return $data;
    }

    /**
     * ✅ After record is created, sync media pivots (featured + product)
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if (!$record) {
            return;
        }

        if (method_exists($record, 'syncMediaRole')) {
            $record->syncMediaRole('featured', $this->featuredMediaIds);
            $record->syncMediaRole('product', $this->productMediaIds);
        }

        $record->refresh();
    }

    /**
     * ✅ Called by: wire:click="generateMultipageLinks"
     * Create record first, then generate.
     */
    public function generateMultipageLinks(): void
    {
        try {
            // ✅ Ensure record exists (create it)
            $this->create();

            $record = $this->getRecord();

            $gen = new MultiPageGenerator();
            $res = $gen->generateForPage($record);

            Notification::make()
                ->success()
                ->title('Generated Links')
                ->body('Total links: ' . ($res['count'] ?? 0))
                ->send();

            // ✅ Redirect to edit so View List works
            $this->redirect(MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin'));
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Generate failed')
                ->body($e->getMessage())
                ->send();
        }
    }
}