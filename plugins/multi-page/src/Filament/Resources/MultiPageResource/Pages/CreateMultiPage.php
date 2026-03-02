<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;
use Plugins\MultiPage\Support\MultiPageGenerator;

class CreateMultiPage extends CreateRecord
{
    protected static string $resource = MultiPageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ MultiPage is its own custom post type now
        $data['type'] = 'multipage';

        // ✅ FIX: posts.author_id is required in DB
        // Use the currently logged-in user (Filament admin).
        // Fallback to 1 if no user (adjust if your admin user id differs).
        $data['author_id'] = $data['author_id']
            ?? Auth::id()
            ?? 1;

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
            $this->redirect(MultiPageResource::getUrl('edit', ['record' => $record]));
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Generate failed')
                ->body($e->getMessage())
                ->send();
        }
    }
}