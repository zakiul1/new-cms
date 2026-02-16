<?php

namespace Plugins\ContactForm\Filament\Pages;

use App\Cms\Core\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ContactConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'plugins.contact-form::filament.contact-config';

    protected static ?string $title = 'Contact Config';
    protected static ?string $navigationLabel = 'Contact Config';
    protected static string|\UnitEnum|null $navigationGroup = 'CMS';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 60;

    public array $data = [];

    protected function getForms(): array
    {
        return ['form'];
    }

    public function mount(): void
    {
        $settings = app(Settings::class);

        // Auto-generate cron token if missing
        $cronToken = (string) $settings->get('cron_token', '', 'plugin:contact-form');
        if (trim($cronToken) === '') {
            $cronToken = Str::random(40);
            $settings->set('cron_token', $cronToken, 'plugin:contact-form');
        }

        $this->data = [
            // eDesk
            'edesk_enabled' => (bool) $settings->get('edesk_enabled', true, 'plugin:contact-form'),
            'edesk_api_url' => (string) $settings->get('edesk_api_url', '', 'plugin:contact-form'),
            'edesk_api_key' => (string) $settings->get('edesk_api_key', '', 'plugin:contact-form'),
            'retry_minutes' => (int) $settings->get('retry_minutes', 30, 'plugin:contact-form'),

            // Cron + Retry control
            'cron_token' => $cronToken,
            'cron_batch' => (int) $settings->get('cron_batch', 10, 'plugin:contact-form'),
            'max_attempts' => (int) $settings->get('max_attempts', 10, 'plugin:contact-form'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('eDesk API')
                    ->schema([
                        Toggle::make('edesk_enabled')
                            ->label('Enable eDesk API')
                            ->default(true),

                        TextInput::make('edesk_api_url')
                            ->label('API URL')
                            ->placeholder('https://api.example.com/endpoint')
                            ->helperText('Set the eDesk API endpoint URL.'),

                        TextInput::make('edesk_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Set the eDesk API key.'),

                        TextInput::make('retry_minutes')
                            ->label('Retry Delay (minutes)')
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->helperText('If API send fails, it will retry after this many minutes (ex: 30 or 60).'),
                    ]),

                Section::make('Cron & Retry (Shared Hosting)')
                    ->schema([
                        TextInput::make('cron_token')
                            ->label('Cron Token')
                            ->helperText('Cron URL will require it: /_contact/cron?token=YOUR_TOKEN')
                            ->minLength(12)
                            ->disabled() // prevent accidental changes; use Regenerate button
                            ->dehydrated(true),

                        TextInput::make('cron_batch')
                            ->label('Cron Batch Size')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->helperText('How many pending messages to process per cron call (recommended 10).'),

                        TextInput::make('max_attempts')
                            ->label('Max Attempts')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->helperText('Stop retrying after this many failed attempts.'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate_token')
                ->label('Regenerate Token')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn() => $this->regenerateCronToken()),

            Action::make('save')
                ->label('Save')
                ->action(fn() => $this->saveSettings()),
        ];
    }

    public function regenerateCronToken(): void
    {
        $settings = app(Settings::class);

        $newToken = Str::random(40);
        $settings->set('cron_token', $newToken, 'plugin:contact-form');

        // Update local state + refill form
        $this->data['cron_token'] = $newToken;
        $this->form->fill($this->data);

        Notification::make()
            ->title('Cron token regenerated')
            ->success()
            ->send();
    }

    public function saveSettings(): void
    {
        $settings = app(Settings::class);

        $state = $this->form->getState();
        $this->data = is_array($state) ? $state : [];

        $retry = (int) ($this->data['retry_minutes'] ?? 30);
        if ($retry <= 0)
            $retry = 30;

        $cronBatch = (int) ($this->data['cron_batch'] ?? 10);
        if ($cronBatch <= 0)
            $cronBatch = 10;

        $maxAttempts = (int) ($this->data['max_attempts'] ?? 10);
        if ($maxAttempts <= 0)
            $maxAttempts = 10;

        // Cron token: always ensure exists
        $cronToken = trim((string) ($this->data['cron_token'] ?? ''));
        if ($cronToken === '') {
            $cronToken = Str::random(40);
        }

        // Save eDesk settings
        $settings->set('edesk_enabled', (bool) ($this->data['edesk_enabled'] ?? true), 'plugin:contact-form');
        $settings->set('edesk_api_url', trim((string) ($this->data['edesk_api_url'] ?? '')), 'plugin:contact-form');
        $settings->set('edesk_api_key', trim((string) ($this->data['edesk_api_key'] ?? '')), 'plugin:contact-form');
        $settings->set('retry_minutes', $retry, 'plugin:contact-form');

        // Save cron settings
        $settings->set('cron_token', $cronToken, 'plugin:contact-form');
        $settings->set('cron_batch', $cronBatch, 'plugin:contact-form');
        $settings->set('max_attempts', $maxAttempts, 'plugin:contact-form');

        Notification::make()->title('Saved')->success()->send();
    }
}