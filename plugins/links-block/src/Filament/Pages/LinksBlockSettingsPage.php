<?php

namespace Plugins\LinksBlock\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Plugins\LinksBlock\Support\LinksBlockSettings;
use Plugins\LinksBlock\Support\LinksBlockShortcode;
use UnitEnum;

class LinksBlockSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Links Block';
    protected static string|UnitEnum|null $navigationGroup = 'Pages';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?int $navigationSort = 53;

    protected string $view = 'links-block::filament.pages.links-block-settings';

    public array $data = [];
    public string $shortcode = '';

    public function mount(): void
    {
        $this->data = LinksBlockSettings::load();
        $this->shortcode = $this->buildShortcode($this->data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action(function () {
                    LinksBlockSettings::save($this->data);

                    Notification::make()
                        ->success()
                        ->title('Saved')
                        ->body('Links Block settings saved.')
                        ->send();
                }),
        ];
    }

    /**
     * Called from Blade whenever any field changes
     */
    public function updatedData(): void
    {
        $this->shortcode = $this->buildShortcode($this->data);
    }

    public function previewHtml(): string
    {
        $atts = [
            'col' => $this->data['col'] ?? 3,
            'hide' => $this->data['hide'] ?? 'no',
            'new-window' => $this->data['new_window'] ?? 'yes',
            'row' => $this->data['row'] ?? 2,
            'rand' => $this->data['rand'] ?? 'yes',
            'n' => $this->data['n'] ?? 60,
            'mcol' => $this->data['mcol'] ?? 1,
            'tcol' => $this->data['tcol'] ?? 2,
            'single-line' => $this->data['single_line'] ?? 'yes',
        ];

        return LinksBlockShortcode::render($atts);
    }

    private function buildShortcode(array $d): string
    {
        // match WP screenshot order
        return sprintf(
            '[linksblock col="%s" hide="%s" new-window="%s" row="%s" rand="%s" n="%s" mcol="%s" tcol="%s" single-line="%s"]',
            (int)($d['col'] ?? 3),
            (string)($d['hide'] ?? 'no'),
            (string)($d['new_window'] ?? 'yes'),
            (int)($d['row'] ?? 2),
            (string)($d['rand'] ?? 'yes'),
            (int)($d['n'] ?? 60),
            (int)($d['mcol'] ?? 1),
            (int)($d['tcol'] ?? 2),
            (string)($d['single_line'] ?? 'yes'),
        );
    }
}