<?php

namespace Plugins\SiatexTags\Filament\Resources\SiatexTagResource\Pages;

use App\Models\CmsSetting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Plugins\SiatexTags\Filament\Resources\SiatexTagResource;
use Plugins\SiatexTags\Models\SiatexTag;

class ListSiatexTags extends ListRecords
{
    protected static string $resource = SiatexTagResource::class;

    private function getSlugTemplate(): string
    {
        return (string) CmsSetting::query()
            ->where('key', 'siatex_tags.slug_template')
            ->value('value');
    }

    private function setSlugTemplate(string $value): void
    {
        CmsSetting::query()->updateOrCreate(
            ['key' => 'siatex_tags.slug_template'],
            ['value' => $value],
        );
    }

    private function makeSlugFromTemplate(string $title, string $template): string
    {
        $template = trim($template);

        $base = $template !== ''
            ? str_ireplace(['[tag]', '[tags]'], $title, $template)
            : $title;

        return Str::slug($base);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('customSlug')
                ->label('Custom Slug')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('gray')
                ->modalHeading('Custom Slug Template')
                ->modalDescription('Use [tag] for tag name. Example: wholesale-[tag]-manufacturers-suppliers')
                ->modalSubmitActionLabel('Save')
                ->form([
                    TextInput::make('template')
                        ->label('Slug Template')
                        ->placeholder('wholesale-[tag]-manufacturers-suppliers')
                        ->default(fn() => $this->getSlugTemplate())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $template = trim((string) ($data['template'] ?? ''));
                    $this->setSlugTemplate($template);

                    Notification::make()
                        ->title('Slug template saved')
                        ->success()
                        ->send();
                }),

            Action::make('regenerateAllSlugs')
                ->label('Regenerate All Slugs')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Regenerate all slugs?')
                ->modalDescription('This will update existing tag slugs using the current template. Conflicting slugs will be skipped.')
                ->action(function (): void {
                    $template = trim($this->getSlugTemplate());

                    if ($template === '') {
                        Notification::make()
                            ->title('Slug template is empty')
                            ->warning()
                            ->send();
                        return;
                    }

                    $updated = 0;
                    $skipped = 0;

                    $tags = SiatexTag::query()
                        ->orderBy('id')
                        ->get(['id', 'title', 'slug']);

                    foreach ($tags as $tag) {
                        $newSlug = $this->makeSlugFromTemplate((string) $tag->title, $template);

                        if ($newSlug === '' || $newSlug === (string) $tag->slug) {
                            $skipped++;
                            continue;
                        }

                        $exists = SiatexTag::query()
                            ->where('slug', $newSlug)
                            ->where('id', '!=', $tag->id)
                            ->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        $tag->slug = $newSlug;
                        $tag->save();

                        $updated++;
                    }

                    Notification::make()
                        ->title("Updated: {$updated}, Skipped: {$skipped}")
                        ->success()
                        ->send();
                }),

            Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Import Siatex Tags')
                ->modalDescription('Paste tags (one per line). Existing slugs will be skipped.')
                ->modalSubmitActionLabel('Import')
                ->form([
                    Textarea::make('lines')
                        ->label('Paste tags (one per line)')
                        ->rows(12)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $raw = (string) ($data['lines'] ?? '');

                    $rows = preg_split("/\r\n|\n|\r/", $raw) ?: [];
                    $rows = array_map(fn($value) => trim((string) $value), $rows);
                    $rows = array_values(array_filter($rows, fn($value) => $value !== ''));

                    if (empty($rows)) {
                        Notification::make()
                            ->title('Nothing to import')
                            ->warning()
                            ->send();
                        return;
                    }

                    $template = trim($this->getSlugTemplate());

                    $created = 0;
                    $skipped = 0;

                    foreach (array_unique($rows) as $title) {
                        $slug = $this->makeSlugFromTemplate((string) $title, $template);

                        if ($slug === '') {
                            $skipped++;
                            continue;
                        }

                        if (SiatexTag::query()->where('slug', $slug)->exists()) {
                            $skipped++;
                            continue;
                        }

                        SiatexTag::create([
                            'title' => $title,
                            'slug' => $slug,
                            'content_json' => ['html' => ''],
                            'meta_json' => [],
                            'media_category_term_id' => null,
                        ]);

                        $created++;
                    }

                    Notification::make()
                        ->title("Imported: {$created}, Skipped: {$skipped}")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Add Siatex Tag')
                ->icon('heroicon-o-plus')
                ->color('info'),
        ];
    }
}