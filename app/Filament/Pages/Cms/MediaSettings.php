<?php

namespace App\Filament\Pages\Cms;

use App\Cms\Core\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use UnitEnum;

class MediaSettings extends Page
{
    protected static ?string $navigationLabel = 'Media Settings';
    protected static ?string $title = 'Media Settings';
    protected static string|UnitEnum|null $navigationGroup = 'CMS';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.cms.media-settings';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(SettingsRepository $settings): void
    {
        $defaultSizes = (array) config('cms-media.image_variants', []);

        $thumbDefault = (int) ($defaultSizes['thumb'] ?? 300);
        $mediumDefault = (int) ($defaultSizes['medium'] ?? 768);
        $mediumLargeDefault = (int) ($defaultSizes['medium_large'] ?? 1024);
        $largeDefault = (int) ($defaultSizes['large'] ?? 1600);

        $this->form->fill([
            'thumbnail_width' => (int) $settings->get('core', 'media_thumbnail_width', $thumbDefault),
            'medium_width' => (int) $settings->get('core', 'media_medium_width', $mediumDefault),
            'medium_large_width' => (int) $settings->get('core', 'media_medium_large_width', $mediumLargeDefault),
            'large_width' => (int) $settings->get('core', 'media_large_width', $largeDefault),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [


            Action::make('regenerateAll')
                ->label('Regenerate All Variants')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate all image variants?')
                ->modalDescription('This will regenerate variants for all image media using the currently saved size settings.')
                ->action(function () {
                    try {
                        Artisan::call('media:regenerate', [
                            '--force' => true,
                        ]);

                        Notification::make()
                            ->title('Variant regeneration completed')
                            ->body('All image variants were regenerated using the saved media settings.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Variant regeneration failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),


            Action::make('save')
                ->label('Save Changes')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(function () {
                    $this->save(app(SettingsRepository::class));
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Image sizes')
                        ->description('The sizes listed below determine the maximum dimensions in pixels to use when generating image variants. Width values drive actual variant generation.')
                        ->schema([
                            TextInput::make('thumbnail_width')
                                ->label('Thumbnail Width')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('medium_width')
                                ->label('Medium Max Width')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('medium_large_width')
                                ->label('Medium Large Max Width')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('large_width')
                                ->label('Large Max Width')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                        ])
                        ->columns(2),
                ]),
            ])
            ->statePath('data');
    }

    public function save(SettingsRepository $settings): void
    {
        $data = $this->form->getState();

        $settings->set('core', 'media_thumbnail_width', max(1, (int) ($data['thumbnail_width'] ?? 300)));
        $settings->set('core', 'media_medium_width', max(1, (int) ($data['medium_width'] ?? 768)));
        $settings->set('core', 'media_medium_large_width', max(1, (int) ($data['medium_large_width'] ?? 1024)));
        $settings->set('core', 'media_large_width', max(1, (int) ($data['large_width'] ?? 1600)));

        Notification::make()
            ->title('Media settings saved')
            ->body('If you changed image sizes, regenerate variants so existing images match the new settings.')
            ->success()
            ->send();
    }
}