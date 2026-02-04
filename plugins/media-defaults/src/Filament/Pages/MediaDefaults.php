<?php

namespace Plugins\MediaDefaults\Filament\Pages;

use App\Cms\Core\Settings;
use App\Filament\Forms\Components\WpClassicEditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class MediaDefaults extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Media';
    protected static ?string $navigationLabel = 'Media Defaults';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 60;

    protected string $view = 'media-defaults::filament.pages.media-defaults';

    public array $data = [
        'default_title' => '',
        'default_description' => '',
        'default_sub_title' => '',
        'default_sub_description' => '',
    ];

    protected function getForms(): array
    {
        return ['form'];
    }

    public function mount(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        $this->data = [
            'default_title' => (string) $settings->get('default_title', '', $group),
            'default_description' => (string) $settings->get('default_description', '', $group),
            'default_sub_title' => (string) $settings->get('default_sub_title', '', $group),
            'default_sub_description' => (string) $settings->get('default_sub_description', '', $group),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                TextInput::make('default_title')
                    ->label('Default Title')
                    ->maxLength(255)
                    ->helperText('Used only if Media title is empty.')
                    ->columnSpanFull(),

                // ✅ TinyMCE editor for Product Description
                WpClassicEditor::make('default_description')
                    ->label('Default Description (Product)')
                    ->height(260)
                    ->columnSpanFull()
                    ->helperText('Used only if Media description is empty.'),

                TextInput::make('default_sub_title')
                    ->label('Default Sub title')
                    ->maxLength(255)
                    ->helperText('Used only if Media Sub title is empty.')
                    ->columnSpanFull(),

                // ✅ TinyMCE editor for Sub Description
                WpClassicEditor::make('default_sub_description')
                    ->label('Default Sub description')
                    ->height(260)
                    ->columnSpanFull()
                    ->helperText('Used only if Media Sub description is empty.'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(fn() => $this->save()),
        ];
    }

    public function save(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        $state = $this->form->getState();
        $this->data = is_array($state) ? $state : $this->data;

        $settings->set('default_title', (string) ($this->data['default_title'] ?? ''), $group);
        $settings->set('default_description', (string) ($this->data['default_description'] ?? ''), $group);
        $settings->set('default_sub_title', (string) ($this->data['default_sub_title'] ?? ''), $group);
        $settings->set('default_sub_description', (string) ($this->data['default_sub_description'] ?? ''), $group);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}