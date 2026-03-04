<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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

    /** @var int[] */
    protected array $featuredMediaIds = [];

    /** @var int[] */
    protected array $productMediaIds = [];

    // ✅ Hide big heading
    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record && method_exists($record, 'mediaPivot')) {
            $data['featured_media_ids'] = $record->mediaPivot()
                ->wherePivot('role', 'featured')
                ->orderBy('post_media.sort_order')
                ->pluck('media.id')
                ->map(fn($id) => (int) $id)
                ->all();

            $data['product_media_ids'] = $record->mediaPivot()
                ->wherePivot('role', 'product')
                ->orderBy('post_media.sort_order')
                ->pluck('media.id')
                ->map(fn($id) => (int) $id)
                ->all();
        } else {
            $data['featured_media_ids'] = [];
            $data['product_media_ids'] = [];
        }

        // fallback legacy single featured
        if (empty($data['featured_media_ids']) && !empty($data['featured_media_id'])) {
            $data['featured_media_ids'] = [(int) $data['featured_media_id']];
        }

        return $data;
    }

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

        // ✅ Capture Featured Images
        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $data['featured_media_ids'])))
            : [];
        unset($data['featured_media_ids']);

        // ✅ Capture Product Images
        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $data['product_media_ids'])))
            : [];
        unset($data['product_media_ids']);

        // ✅ Keep legacy single featured_media_id synced
        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        return $data;
    }

    protected function afterSave(): void
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
     * ✅ Header buttons
     * IMPORTANT: use Filament DeleteAction (v5) — DO NOT call $this->delete()
     */
    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        $viewUrl = $record
            ? url('/' . ltrim((string) ($record->slug ?? ''), '/'))
            : url('/');

        return [
            Action::make('add_new')
                ->label('Add Page')
                ->color('warning')
                ->icon('heroicon-o-plus')
                ->url(fn() => MultiPageResource::getUrl('create', panel: 'admin')),

            // ✅ FIX: proper delete action for EditRecord in Filament v5
            DeleteAction::make()
                ->label('Delete')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation(),

            Action::make('view')
                ->label('View')
                ->color('gray')
                ->icon('heroicon-o-eye')
                ->url(fn() => $viewUrl, shouldOpenInNewTab: true),

            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => MultiPageResource::getUrl('index', panel: 'admin')),

            Action::make('save')
                ->label('Save changes')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action(fn() => $this->save()),
        ];
    }

    public function getGeneratedLinksForModal(): array
    {
        $record = $this->getRecord();
        if (!$record) {
            return [];
        }

        if (class_exists(MultiPageStorage::class)) {
            MultiPageStorage::ensureDirs();
        }

        $disk = Storage::disk('local');

        $trackerKey = trim((string) ($record->slug ?? '')) ?: 'multipage-' . (int) $record->id;

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
        if (!is_array($generated)) {
            return [];
        }

        return array_values(array_filter($generated, fn($v) => is_string($v) && trim($v) !== ''));
    }

    protected function getFormActions(): array
    {
        return parent::getFormActions();
    }

    protected function getActions(): array
    {
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

                    $items = '';
                    foreach ($links as $path) {
                        $path = trim($path);

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

    // Backward compatibility: if your blade still calls wire:click="generateMultipageLinks"
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