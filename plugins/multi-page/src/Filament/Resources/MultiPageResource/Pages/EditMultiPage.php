<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;
use Plugins\MultiPage\Support\MultiPageGenerator;
use Plugins\MultiPage\Support\MultiPageStorage;

class EditMultiPage extends EditRecord
{
    protected static string $resource = MultiPageResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ keep as MultiPage CPT always
        $data['type'] = 'multipage';

        // ✅ normalize json columns
        $data['meta_json'] = is_array($data['meta_json'] ?? null) ? $data['meta_json'] : [];
        $data['content_json'] = is_array($data['content_json'] ?? null) ? $data['content_json'] : [];

        // ✅ ensure multipage settings exist
        $data['meta_json']['multipage'] = is_array($data['meta_json']['multipage'] ?? null)
            ? $data['meta_json']['multipage']
            : [];

        // ✅ normalize enabled
        $data['meta_json']['multipage']['enabled'] = (bool) ($data['meta_json']['multipage']['enabled'] ?? true);

        return $data;
    }

    /**
     * ✅ Remove header actions from edit page (Generate/View List not needed in header)
     * Keep only the default Filament actions (Save/Delete/etc.)
     */
    protected function getHeaderActions(): array
    {
        return parent::getHeaderActions();
    }

    /**
     * ✅ Read generated links from tracker json
     * Used by the modal (and can also be used by your right panel blade).
     */
    public function getGeneratedLinksForModal(): array
    {
        $record = $this->getRecord();
        if (!$record) {
            return [];
        }

        // make sure folders exist
        if (class_exists(MultiPageStorage::class)) {
            MultiPageStorage::ensureDirs();
        }

        $disk = Storage::disk('local');

        // same key logic you use elsewhere (slug preferred)
        $trackerKey = trim((string) ($record->slug ?? '')) ?: 'multipage-' . (int) $record->id;

        // preferred: use MultiPageStorage constant if available
        $trackerPath = class_exists(MultiPageStorage::class)
            ? (MultiPageStorage::TRACKERS . '/' . $trackerKey . '.json')
            : ('app/private/multipage/trackers/' . $trackerKey . '.json');

        if (!$disk->exists($trackerPath)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($trackerPath), true);
        if (!is_array($decoded)) {
            return [];
        }

        $generated = $decoded['generated'] ?? [];

        // Ensure array of strings
        if (!is_array($generated)) {
            return [];
        }

        // sometimes may be associative; normalize
        return array_values(array_filter($generated, fn($v) => is_string($v) && trim($v) !== ''));
    }

    /**
     * ✅ Provide the View List action (modal) for your right panel button
     * This renders ONLY the list — no iframe / no full CMS layout.
     */
    protected function getFormActions(): array
    {
        // Keep Filament default form actions (Save/Cancel) + our custom ones if needed.
        // If you don’t want any custom form actions either, just return parent::getFormActions().
        return parent::getFormActions();
    }

    /**
     * If your blade uses Filament action mounting, this will work:
     * wire:click="$dispatch('open-modal', { id: 'mountedActionModal' })"
     * or simply: wire:click="mountAction('viewList')"
     */
    protected function getActions(): array
    {
        // Some Filament versions use getHeaderActions + form actions.
        // In Filament v3, modal actions can still be mounted even if not in header,
        // but if your setup needs it, you can expose this action here.
        return [
            Action::make('viewList')
                ->label('View List')
                ->color('gray')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Generated Links')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth('7xl')
                ->modalContent(function (): HtmlString {
                    $links = $this->getGeneratedLinksForModal();

                    if (count($links) === 0) {
                        return new HtmlString('
                            <div class="p-4">
                                <div class="text-sm text-gray-600">
                                    No generated links found. Click Generate first.
                                </div>
                            </div>
                        ');
                    }

                    // Build list HTML safely
                    $items = '';
                    foreach ($links as $path) {
                        $path = trim($path);

                        // Convert to full URL if it’s a relative path like "/t-shirts..."
                        $href = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                            ? $path
                            : url($path);

                        $items .= '<li class="py-1">
                            <a class="text-primary-600 hover:underline" target="_blank" rel="noopener noreferrer"
                               href="' . e($href) . '">' . e($path) . '</a>
                        </li>';
                    }

                    return new HtmlString('
                        <div class="p-4" style="max-height: 80vh; overflow:auto;">
                            <div class="text-sm text-gray-600 mb-3">
                                Total links: <strong>' . count($links) . '</strong>
                            </div>
                            <ul class="list-disc pl-5 space-y-1">
                                ' . $items . '
                            </ul>
                        </div>
                    ');
                })
                ->action(fn() => null),

            Action::make('generateMultipageLinks')
                ->label('Generate')
                ->color('success')
                ->icon('heroicon-o-bolt')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->save();

                    try {
                        $gen = new MultiPageGenerator();
                        $res = $gen->generateForPage($this->getRecord());

                        Notification::make()
                            ->success()
                            ->title('Generated')
                            ->body('Total links: ' . ($res['count'] ?? 0))
                            ->send();

                        $this->dispatch('$refresh');
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Generate failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Backward compatibility: if your blade still calls wire:click="generateMultipageLinks"
     * it will still work (but without Filament action loading UI).
     */
    public function generateMultipageLinks(): void
    {
        $this->save();

        try {
            $gen = new MultiPageGenerator();
            $res = $gen->generateForPage($this->getRecord());

            Notification::make()
                ->success()
                ->title('Generated Links')
                ->body('Total links: ' . ($res['count'] ?? 0))
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Generate failed')
                ->body($e->getMessage())
                ->send();
        }
    }
}