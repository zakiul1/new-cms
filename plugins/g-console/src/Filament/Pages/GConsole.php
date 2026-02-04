<?php

namespace Plugins\GConsole\Filament\Pages;

use App\Cms\Core\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class GConsole extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'CMS';
    protected static ?string $navigationLabel = 'G Console';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';
    protected static ?int $navigationSort = 60;

    protected string $view = 'g-console::filament.pages.g-console';

    public array $data = [
        'header_meta' => '',
        'footer_script' => '',
    ];

    protected function getForms(): array
    {
        return ['form'];
    }

    public function mount(): void
    {
        $settings = app(Settings::class);

        $this->data = [
            'header_meta' => (string) $settings->get('header_meta', '', 'plugins.g-console'),
            'footer_script' => (string) $settings->get('footer_script', '', 'plugins.g-console'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Textarea::make('header_meta')
                    ->label('Header Meta')
                    ->rows(4)
                    ->helperText('Paste meta tags. This will be injected into the theme <head>.')
                    ->placeholder("<meta property=\"og:title\" content=\"...\">"),

                Textarea::make('footer_script')
                    ->label('Footer Script')
                    ->rows(7)
                    ->helperText('Paste scripts including <script>..</script>. This will be injected before </body>.')
                    ->placeholder("<script>console.log('Hello');</script>"),
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

        $state = $this->form->getState();
        $this->data = is_array($state) ? $state : $this->data;

        $settings->set('header_meta', (string) ($this->data['header_meta'] ?? ''), 'plugins.g-console');
        $settings->set('footer_script', (string) ($this->data['footer_script'] ?? ''), 'plugins.g-console');

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}