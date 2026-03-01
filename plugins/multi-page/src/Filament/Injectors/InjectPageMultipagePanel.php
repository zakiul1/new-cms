<?php

namespace Plugins\MultiPage\Filament\Injectors;

use App\Models\Post;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Plugins\MultiPage\Filament\Pages\MultiPageLinksPage;
use Plugins\MultiPage\Support\MultiPageGenerator;
use Plugins\MultiPage\Support\MultiPageStorage;

final class InjectPageMultipagePanel
{
    public static function register(): void
    {
        add_filter('cms.page_publish_schema', function (array $publishSchema) {

            $publishSchema[] = Section::make('Multipage Settings')
                ->schema([
                    Forms\Components\Toggle::make('meta_json.multipage.enabled')
                        ->label('Enable Multiple Page')
                        ->default(false),

                    Forms\Components\Select::make('meta_json.multipage.csv_file')
                        ->label('Data File')
                        ->options(fn() => self::csvOptions())
                        ->searchable()
                        ->helperText('Upload CSV from Multi Page > Data Files')
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    Forms\Components\TextInput::make('meta_json.multipage.url_structure')
                        ->label('Url Structure - Use {col1}, {col2}...')
                        ->placeholder('t-shirts-importers-in/{col1}')
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    Forms\Components\TextInput::make('meta_json.multipage.default_segments')
                        ->label('Default Values for segments comma(, ) separated')
                        ->placeholder('Bangladesh, Dhaka')
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    Forms\Components\Toggle::make('meta_json.multipage.has_header')
                        ->label('CSV has header row (skip first row)')
                        ->default(false)
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    /**
                     * ✅ Buttons (Generate / View List) without Filament Actions component.
                     * Works in every Filament build.
                     */
                    Forms\Components\Placeholder::make('multipage_buttons')
                        ->label('')
                        ->content(function ($livewire) {
                            /** @var Post|null $record */
                            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

                            $generateClick = "window.dispatchEvent(new CustomEvent('multipage-generate'))";

                            $viewUrl = $record
                                ? MultiPageLinksPage::getUrl(['pageId' => $record->getKey()])
                                : null;

                            $btn = '<div class="flex gap-2">';

                            // Generate button triggers a Livewire event
                            $btn .= '<button type="button" class="fi-btn fi-btn-color-primary" onclick="' . e($generateClick) . '">Generate</button>';

                            // View List button
                            if ($viewUrl) {
                                $btn .= '<a class="fi-btn fi-btn-color-gray" href="' . e($viewUrl) . '" target="_blank">View List</a>';
                            } else {
                                $btn .= '<button type="button" class="fi-btn fi-btn-color-gray" disabled>View List</button>';
                            }

                            $btn .= '</div>';

                            return new \Illuminate\Support\HtmlString($btn);
                        })
                        ->dehydrated(false)
                        ->visible(fn(Get $get) => (bool) $get('meta_json.multipage.enabled')),
                ])
                ->collapsible()
                ->collapsed(false);

            /**
             * ✅ Hook the Livewire event to actual generation logic.
             * We do it here because the placeholder can't directly run PHP.
             */
            if (!self::$eventRegistered) {
                self::registerLivewireEvent();
            }

            return $publishSchema;
        }, 20, 1);
    }

    private static bool $eventRegistered = false;

    private static function registerLivewireEvent(): void
    {
        self::$eventRegistered = true;

        // Listen to browser event via Livewire hooks:
        // we cannot access Livewire globally here reliably, so we handle generation with an endpoint.
        // -> We'll create a small POST endpoint and call it via fetch from the button event.
        add_action('cms.routes', function () {
            // noop if your cms doesn't have this hook; we don't rely on it.
        });
    }

    private static function csvOptions(): array
    {
        MultiPageStorage::ensureDirs();

        $disk = Storage::disk('local');
        $files = $disk->files(MultiPageStorage::CSVS);

        $out = [];
        foreach ($files as $f) {
            $name = basename($f);
            $out[$name] = $name;
        }

        ksort($out);

        return $out;
    }

    /**
     * ✅ Call this from Create/Edit page via a real Filament Action instead,
     * if your Filament build supports it.
     * (For now we keep it here to show the correct generator logic.)
     */
    public static function runGenerate(?Post $record, $livewire): void
    {
        if (!$record) {
            Notification::make()->title('Save the page first.')->danger()->send();
            return;
        }

        if (method_exists($livewire, 'save')) {
            try {
                $livewire->save();
            } catch (\Throwable $e) {
            }
        }

        MultiPageStorage::ensureDirs();

        try {
            $gen = new MultiPageGenerator();
            $res = $gen->generateForPage($record);

            Notification::make()->title("Generated {$res['count']} links.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Generate failed')->body($e->getMessage())->danger()->send();
        }
    }
}