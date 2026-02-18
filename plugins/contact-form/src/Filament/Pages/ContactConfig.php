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

    /** @var array<string, mixed> */
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

        $cronUrl = $this->buildCronUrl($cronToken);
        $curlCmd = $this->buildCurlCronCommand($cronUrl);
        $wgetCmd = $this->buildWgetCronCommand($cronUrl);

        $this->data = [
            // eDesk
            'edesk_enabled' => (bool) $settings->get('edesk_enabled', true, 'plugin:contact-form'),
            'edesk_api_url' => (string) $settings->get('edesk_api_url', '', 'plugin:contact-form'),
            'edesk_api_key' => (string) $settings->get('edesk_api_key', '', 'plugin:contact-form'),
            'retry_minutes' => (int) $settings->get('retry_minutes', 30, 'plugin:contact-form'),

            // ✅ Add to Cart / Get Price system
            'cart_enabled' => (bool) $settings->get('cart_enabled', true, 'plugin:contact-form'),
            'cart_floating_enabled' => (bool) $settings->get('cart_floating_enabled', true, 'plugin:contact-form'),

            // ✅ UX: redirect after save
            'redirect_to_submissions_after_save' => (bool) $settings->get(
                'redirect_to_submissions_after_save',
                true,
                'plugin:contact-form'
            ),

            // Cron + Retry control
            'cron_token' => $cronToken,
            'cron_batch' => (int) $settings->get('cron_batch', 10, 'plugin:contact-form'),
            'max_attempts' => (int) $settings->get('max_attempts', 10, 'plugin:contact-form'),

            // ✅ Helpful: copy/paste for cPanel
            'cron_url' => $cronUrl,
            'cron_curl_command' => $curlCmd,
            'cron_wget_command' => $wgetCmd,
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

                Section::make('Get Price / Add to Cart')
                    ->description('Controls the "Get Price" add-to-cart popup and cart UI on frontend.')
                    ->schema([
                        Toggle::make('cart_enabled')
                            ->label('Enable Add to Cart system')
                            ->default(true)
                            ->helperText('If OFF, Get Price button will not open cart UI.'),

                        Toggle::make('cart_floating_enabled')
                            ->label('Enable floating cart button')
                            ->default(true)
                            ->helperText('If ON, a floating cart button appears when user has 1+ item in cart.'),
                    ]),

                Section::make('Admin UX')
                    ->description('Optional admin convenience behavior.')
                    ->schema([
                        Toggle::make('redirect_to_submissions_after_save')
                            ->label('After saving settings, open Contact Submissions page')
                            ->default(true)
                            ->helperText('If ON, clicking Save will redirect to Contact Submissions list.'),
                    ]),

                Section::make('Cron & Retry (Shared Hosting)')
                    ->description('For cPanel: add a Cron Job that runs every 5–10 minutes. This will retry pending submissions and auto-delete old records.')
                    ->schema([
                        TextInput::make('cron_token')
                            ->label('Cron Token')
                            ->helperText('Cron URL requires it: /_contact/cron?token=YOUR_TOKEN')
                            ->minLength(12)
                            ->disabled()
                            ->dehydrated(true),

                        TextInput::make('cron_url')
                            ->label('Cron URL (copy)')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('cron_curl_command')
                            ->label('cPanel Cron Command (curl) — copy this')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('cron_wget_command')
                            ->label('cPanel Cron Command (wget) — if curl is not available')
                            ->disabled()
                            ->dehydrated(false),

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

        $this->data['cron_token'] = $newToken;

        // refresh helper fields
        $cronUrl = $this->buildCronUrl($newToken);
        $this->data['cron_url'] = $cronUrl;
        $this->data['cron_curl_command'] = $this->buildCurlCronCommand($cronUrl);
        $this->data['cron_wget_command'] = $this->buildWgetCronCommand($cronUrl);

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
        if ($retry <= 0) {
            $retry = 30;
        }

        $cronBatch = (int) ($this->data['cron_batch'] ?? 10);
        if ($cronBatch <= 0) {
            $cronBatch = 10;
        }

        $maxAttempts = (int) ($this->data['max_attempts'] ?? 10);
        if ($maxAttempts <= 0) {
            $maxAttempts = 10;
        }

        // Cron token: always ensure exists
        $cronToken = trim((string) ($this->data['cron_token'] ?? ''));
        if ($cronToken === '') {
            $cronToken = Str::random(40);
        }

        // ✅ Cart settings
        $cartEnabled = (bool) ($this->data['cart_enabled'] ?? true);
        $cartFloatingEnabled = (bool) ($this->data['cart_floating_enabled'] ?? true);

        // ✅ redirect after save
        $redirectAfterSave = (bool) ($this->data['redirect_to_submissions_after_save'] ?? true);

        // Save eDesk settings
        $settings->set('edesk_enabled', (bool) ($this->data['edesk_enabled'] ?? true), 'plugin:contact-form');
        $settings->set('edesk_api_url', trim((string) ($this->data['edesk_api_url'] ?? '')), 'plugin:contact-form');
        $settings->set('edesk_api_key', trim((string) ($this->data['edesk_api_key'] ?? '')), 'plugin:contact-form');
        $settings->set('retry_minutes', $retry, 'plugin:contact-form');

        // ✅ Save cart settings
        $settings->set('cart_enabled', $cartEnabled, 'plugin:contact-form');
        $settings->set('cart_floating_enabled', $cartFloatingEnabled, 'plugin:contact-form');

        // ✅ Save UX setting
        $settings->set('redirect_to_submissions_after_save', $redirectAfterSave, 'plugin:contact-form');

        // Save cron settings
        $settings->set('cron_token', $cronToken, 'plugin:contact-form');
        $settings->set('cron_batch', $cronBatch, 'plugin:contact-form');
        $settings->set('max_attempts', $maxAttempts, 'plugin:contact-form');

        // refresh helper fields (not saved)
        $cronUrl = $this->buildCronUrl($cronToken);
        $this->data['cron_url'] = $cronUrl;
        $this->data['cron_curl_command'] = $this->buildCurlCronCommand($cronUrl);
        $this->data['cron_wget_command'] = $this->buildWgetCronCommand($cronUrl);
        $this->form->fill($this->data);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();

        // ✅ Redirect to Contact Submissions page (if enabled)
        if ($redirectAfterSave) {
            $url = null;

            try {
                if (class_exists(\Plugins\ContactForm\Filament\Pages\ContactSubmissions::class)) {
                    $url = \Plugins\ContactForm\Filament\Pages\ContactSubmissions::getUrl();
                }
            } catch (\Throwable $e) {
                $url = null;
            }

            if (is_string($url) && trim($url) !== '') {
                $this->redirect($url);
            }
        }
    }

    private function buildCronUrl(string $token): string
    {
        $token = trim($token);
        $base = rtrim((string) config('app.url'), '/');

        // If config is empty or wrong, fallback to current host if possible
        if ($base === '') {
            try {
                $base = rtrim((string) url('/'), '/');
            } catch (\Throwable $e) {
                $base = '';
            }
        }

        if ($base === '') {
            // last fallback: show relative
            return '/_contact/cron?token=' . urlencode($token);
        }

        return $base . '/_contact/cron?token=' . urlencode($token);
    }

    private function buildCurlCronCommand(string $cronUrl): string
    {
        return '*/10 * * * * curl -fsS "' . $cronUrl . '" >/dev/null 2>&1';
    }

    private function buildWgetCronCommand(string $cronUrl): string
    {
        return '*/10 * * * * wget -qO- "' . $cronUrl . '" >/dev/null 2>&1';
    }
}