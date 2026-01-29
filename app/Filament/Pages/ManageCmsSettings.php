<?php

namespace App\Filament\Pages;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\SettingsRepository;
use App\Cms\Plugins\PluginManager;
use App\Cms\Themes\ThemeManager;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ManageCmsSettings extends Page
{
    protected static ?string $navigationLabel = 'CMS Settings';
    protected static \UnitEnum|string|null $navigationGroup = 'CMS';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected string $view = 'filament.pages.manage-cms-settings';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(SettingsRepository $settings, ThemeManager $themes): void
    {
        // ✅ Source of truth is ThemeManager (it reads from settings + ensures valid)
        $activeTheme = $themes->activeSlug();

        $homepageId = $settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        $this->form->fill([
            // Core
            'site_name' => $settings->get('core', 'site_name', 'My CMS'),
            'site_url' => $settings->get('core', 'site_url', url('/')),
            'timezone' => $settings->get('core', 'timezone', config('app.timezone')),
            'active_theme' => $activeTheme, // ✅ always correct
            'enabled_plugins' => $settings->get('core', 'enabled_plugins', []),

            // Global Contact (Siatex header)
            'contact_phone' => $settings->get('core', 'contact_phone', ''),
            'contact_email' => $settings->get('core', 'contact_email', ''),

            // ✅ Homepage page selector (nullable)
            'homepage_page_id' => $homepageId,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearRenderCache')
                ->label('Clear Render Cache')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (CmsCacheVersions $versions) {
                    $versions->bumpRender();

                    Notification::make()
                        ->success()
                        ->title('Render cache cleared')
                        ->body('Menus/widgets/theme output will be regenerated.')
                        ->send();
                }),

            Action::make('clearThemeDiscovery')
                ->label('Clear Theme Discovery')
                ->color('gray')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->action(function (ThemeManager $themes) {
                    $themes->forgetDiscoveryCache();

                    Notification::make()
                        ->success()
                        ->title('Theme discovery cache cleared')
                        ->body('Theme list will be re-scanned on next load.')
                        ->send();
                }),

            Action::make('flushCache')
                ->label('Flush ALL Cache')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function () {
                    Cache::flush();

                    Notification::make()
                        ->success()
                        ->title('All cache flushed')
                        ->body('All cache entries removed from the cache store.')
                        ->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('site_name')->required()->maxLength(120),
                    TextInput::make('site_url')->required()->url()->maxLength(255),
                    TextInput::make('timezone')->required()->maxLength(64),

                    TextInput::make('contact_phone')
                        ->label('Contact Phone')
                        ->maxLength(50),

                    TextInput::make('contact_email')
                        ->label('Contact Email')
                        ->email()
                        ->maxLength(120),

                    // ✅ Nullable homepage page (no more "0" invalid)
                    Select::make('homepage_page_id')
                        ->label('Homepage Page')
                        ->options(
                            fn() => Post::query()
                                ->where('type', 'page')
                                ->where('status', 'published')
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->placeholder('— Use latest posts (default) —')
                        ->nullable(),

                    // ✅ Active theme always from ThemeManager
                    Select::make('active_theme')
                        ->label('Active Theme')
                        ->options(
                            fn(ThemeManager $themes) => collect($themes->all())
                                ->mapWithKeys(fn($m, $slug) => [$slug => ($m->name ?? $slug)])
                                ->all()
                        )
                        ->searchable()
                        ->required()
                        ->reactive(),

                    CheckboxList::make('enabled_plugins')
                        ->label('Enabled Plugins')
                        ->options(
                            fn(PluginManager $plugins) => collect($plugins->all())
                                ->mapWithKeys(fn($m, $slug) => [$slug => ($m->name ?? $slug)])
                                ->all()
                        ),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(
        SettingsRepository $settings,
        ThemeManager $themes,
        PluginManager $plugins,
        CmsCacheVersions $versions,
    ): void {
        $data = $this->form->getState();

        // Core
        $settings->set('core', 'site_name', (string) ($data['site_name'] ?? ''));
        $settings->set('core', 'site_url', (string) ($data['site_url'] ?? ''));
        $settings->set('core', 'timezone', (string) ($data['timezone'] ?? ''));

        // Contact
        $settings->set('core', 'contact_phone', (string) ($data['contact_phone'] ?? ''));
        $settings->set('core', 'contact_email', (string) ($data['contact_email'] ?? ''));

        // ✅ Homepage page: store null when empty
        $homepageId = $data['homepage_page_id'] ?? null;
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }
        $settings->set('core', 'homepage_page_id', $homepageId);

        $renderChanged = false;
        $errors = [];

        // Theme change
        $currentTheme = $themes->activeSlug(); // ✅ always correct
        $newTheme = (string) ($data['active_theme'] ?? $currentTheme);

        if ($newTheme !== '' && $newTheme !== $currentTheme) {
            try {
                $themes->activate($newTheme);
                $renderChanged = true;
            } catch (Throwable $e) {
                $errors[] = "Theme activation ({$newTheme}): " . $e->getMessage();
            }
        }

        // Plugins enable/disable
        $currentEnabled = $settings->get('core', 'enabled_plugins', []);
        $currentEnabled = is_array($currentEnabled)
            ? array_values(array_unique(array_map('strval', $currentEnabled)))
            : [];

        $newEnabled = $data['enabled_plugins'] ?? [];
        $newEnabled = is_array($newEnabled)
            ? array_values(array_unique(array_map('strval', $newEnabled)))
            : [];

        $toEnable = array_values(array_diff($newEnabled, $currentEnabled));
        $toDisable = array_values(array_diff($currentEnabled, $newEnabled));

        foreach ($toEnable as $slug) {
            try {
                $plugins->enable($slug);
                $renderChanged = true;
            } catch (Throwable $e) {
                $errors[] = "Enable {$slug}: " . $e->getMessage();
            }
        }

        foreach ($toDisable as $slug) {
            try {
                $plugins->disable($slug);
                $renderChanged = true;
            } catch (Throwable $e) {
                $errors[] = "Disable {$slug}: " . $e->getMessage();
            }
        }

        if ($renderChanged) {
            $versions->bumpRender();
            $themes->forgetDiscoveryCache();
        }

        if ($errors !== []) {
            Notification::make()
                ->danger()
                ->title('Saved with warnings')
                ->body(implode("\n", $errors))
                ->send();
            return;
        }

        Notification::make()
            ->success()
            ->title('Saved')
            ->send();

        // ✅ Refresh form state (so UI matches newly activated theme instantly)
        $this->form->fill([
            ...$data,
            'active_theme' => $themes->activeSlug(),
        ]);
    }
}