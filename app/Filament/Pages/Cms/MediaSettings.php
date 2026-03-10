<?php

namespace App\Filament\Pages\Cms;

use App\Cms\Core\SettingsRepository;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
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

    protected int $regenerateBatchSize = 25;

    public function mount(SettingsRepository $settings): void
    {
        $defaultSizes = (array) config('cms-media.image_variants', []);

        $thumbDefault = (int) ($defaultSizes['thumb'] ?? 275);
        $smallDefault = (int) ($defaultSizes['small'] ?? 370);
        $heroSmDefault = (int) ($defaultSizes['hero_sm'] ?? 575);
        $largeDefault = (int) ($defaultSizes['large'] ?? 1000);

        $this->form->fill([
            'thumbnail_width' => (int) $settings->get('core', 'media_thumbnail_width', $thumbDefault),
            'small_width' => (int) $settings->get('core', 'media_small_width', $smallDefault),
            'hero_sm_width' => (int) $settings->get('core', 'media_hero_sm_width', $heroSmDefault),
            'large_width' => (int) $settings->get('core', 'media_large_width', $largeDefault),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateBatch')
                ->label('Regenerate Next Batch')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate next image batch?')
                ->modalDescription('Processes a small batch of images safely for shared hosting. Click again until all images are processed.')
                ->action(function () {
                    try {
                        $result = $this->regenerateNextBatch();

                        Notification::make()
                            ->title('Batch regeneration completed')
                            ->body(
                                "Processed {$result['processed']} image(s). "
                                . ($result['remaining'] > 0
                                    ? "{$result['remaining']} image(s) still remaining. Click again to continue."
                                    : 'All pending images are now processed.')
                            )
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Batch regeneration failed')
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
                        ->description('The sizes below control generated image variants. Canonical variants are: thumb, small, hero_sm, and large.')
                        ->schema([
                            TextInput::make('thumbnail_width')
                                ->label('Thumbnail Width (thumb)')
                                ->helperText('Used for tiny thumbnails and grid previews.')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('small_width')
                                ->label('Small Width (small)')
                                ->helperText('Compact cards and small content images.')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('hero_sm_width')
                                ->label('Hero Small Width (hero_sm)')
                                ->helperText('Mobile hero and LCP-friendly image size.')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('large_width')
                                ->label('Large Width (large)')
                                ->helperText('Large desktop content and hero images.')
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

        $thumb = max(1, (int) ($data['thumbnail_width'] ?? 275));
        $small = max(1, (int) ($data['small_width'] ?? 370));
        $heroSm = max(1, (int) ($data['hero_sm_width'] ?? 575));
        $large = max(1, (int) ($data['large_width'] ?? 1000));

        $settings->set('core', 'media_thumbnail_width', $thumb);
        $settings->set('core', 'media_small_width', $small);
        $settings->set('core', 'media_hero_sm_width', $heroSm);
        $settings->set('core', 'media_large_width', $large);

        config()->set('cms-media.image_variants', [
            'thumb' => $thumb,
            'small' => $small,
            'hero_sm' => $heroSm,
            'large' => $large,
        ]);

        Notification::make()
            ->title('Media settings saved')
            ->body('Image variant sizes were updated to thumb, small, hero_sm, and large. Regenerate image batches until all images are processed.')
            ->success()
            ->send();
    }

    protected function regenerateNextBatch(): array
    {
        $batchSize = max(1, min(100, $this->regenerateBatchSize));

        $requiredVariants = array_keys((array) config('cms-media.image_variants', []));

        $baseQuery = Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->where(function (Builder $q) use ($requiredVariants) {
                $q->whereNull('processed_at')
                    ->orWhereDoesntHave('variantRecords')
                    ->orWhereHas('variantRecords', function (Builder $vq) {
                        $vq->whereIn('key', ['medium', 'medium_large']);
                    });

                foreach ($requiredVariants as $variant) {
                    $q->orWhereDoesntHave('variantRecords', function (Builder $vq) use ($variant) {
                        $vq->where('key', $variant);
                    });
                }
            })
            ->orderBy('id');

        $items = (clone $baseQuery)
            ->limit($batchSize)
            ->get();

        $processed = 0;

        foreach ($items as $media) {
            GenerateMediaVariants::dispatchSync((int) $media->id, false);
            $processed++;
        }

        $remaining = (clone $baseQuery)->count();

        return [
            'processed' => $processed,
            'remaining' => $remaining,
        ];
    }
}