<?php

namespace App\Filament\Pages;

use App\Cms\Core\SettingsRepository;
use App\Cms\Plugins\PluginManager;
use App\Cms\Themes\ThemeManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class ManageCmsSettings extends Page
{
    protected static ?string $navigationLabel = 'CMS Settings';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.manage-cms-settings';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(
        SettingsRepository $settings,
    ): void {
        $this->form->fill([
            'site_name' => $settings->get('core', 'site_name', 'My CMS'),
            'site_url' => $settings->get('core', 'site_url', url('/')),
            'timezone' => $settings->get('core', 'timezone', config('app.timezone')),
            'active_theme' => $settings->get('core', 'active_theme', 'starter'),
            'enabled_plugins' => $settings->get('core', 'enabled_plugins', []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('site_name')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('site_url')
                        ->required()
                        ->url()
                        ->maxLength(255),

                    TextInput::make('timezone')
                        ->required()
                        ->maxLength(64),

                    Select::make('active_theme')
                        ->label('Active Theme')
                        ->options(
                            fn(ThemeManager $themes) => collect($themes->all())
                                ->mapWithKeys(fn($m, $slug) => [$slug => ($m['name'] ?? $slug)])
                                ->all()
                        )
                        ->searchable()
                        ->required(),

                    CheckboxList::make('enabled_plugins')
                        ->label('Enabled Plugins')
                        ->options(
                            fn(PluginManager $plugins) => collect($plugins->all())
                                ->mapWithKeys(fn($m, $slug) => [$slug => ($m['name'] ?? $slug)])
                                ->all()
                        ),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(SettingsRepository $settings): void
    {
        $data = $this->form->getState();

        $settings->set('core', 'site_name', $data['site_name']);
        $settings->set('core', 'site_url', $data['site_url']);
        $settings->set('core', 'timezone', $data['timezone']);
        $settings->set('core', 'active_theme', $data['active_theme']);
        $settings->set('core', 'enabled_plugins', array_values($data['enabled_plugins'] ?? []));

        Notification::make()
            ->success()
            ->title('Saved')
            ->send();
    }
}