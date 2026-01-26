<?php

namespace App\Filament\Pages;

use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManager;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;

class Themes extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Themes';
    protected static \UnitEnum|string|null $navigationGroup = 'Appearance';

    // ✅ MUST be non-static in your Filament build
    protected string $view = 'filament.pages.themes';

    /** Livewire state */
    public array $themes = [];
    public string $active = '';
    public string $search = '';
    public string $filter = 'all'; // all | active | updates

    public function mount(ThemeManager $themes): void
    {
        $this->reload($themes);
    }

    private function reload(ThemeManager $themes): void
    {
        $this->themes = $themes->discoverForUi(); // arrays only (Livewire-safe)
        $this->active = $themes->activeSlug();
    }

    /** @return array<string, array> */
    public function getFilteredThemesProperty(): array
    {
        $items = $this->themes;

        // Search
        $q = trim(mb_strtolower($this->search));
        if ($q !== '') {
            $items = array_filter($items, function ($t) use ($q) {
                $m = $t['manifest'] ?? [];
                $name = mb_strtolower((string) ($m['name'] ?? ''));
                $slug = mb_strtolower((string) ($t['slug'] ?? ''));
                $author = mb_strtolower((string) ($m['author'] ?? ''));
                $desc = mb_strtolower((string) ($m['description'] ?? ''));

                return str_contains($name, $q)
                    || str_contains($slug, $q)
                    || str_contains($author, $q)
                    || str_contains($desc, $q);
            });
        }

        // Filter
        if ($this->filter === 'active') {
            $items = array_filter($items, fn($t) => ($t['slug'] ?? '') === $this->active);
        } elseif ($this->filter === 'updates') {
            $items = array_filter($items, function ($t) {
                $m = $t['manifest'] ?? [];
                $current = (string) ($m['version'] ?? '');
                $latest = (string) ($m['latest_version'] ?? '');
                return $latest !== '' && $current !== '' && version_compare($latest, $current, '>');
            });
        }

        return $items;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('installTheme')
                ->label('Install Theme (ZIP)')
                ->form([
                    FileUpload::make('zip')
                        ->label('Theme ZIP')
                        ->required()
                        ->disk('local')
                        ->directory('cms/tmp/uploads')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize((int) (config('cms.max_zip_size_bytes') / 1024)), // KB
                ])
                ->action(function (array $data, ThemeInstaller $installer, ThemeManager $themes) {
                    $path = Arr::get($data, 'zip');

                    if (!$path) {
                        Notification::make()->title('No file uploaded')->danger()->send();
                        return;
                    }

                    $abs = storage_path('app/' . $path);

                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $abs,
                        basename($abs),
                        'application/zip',
                        null,
                        true
                    );

                    $manifest = $installer->installFromZip($uploadedFile);

                    Notification::make()
                        ->title('Theme installed')
                        ->body("Installed: {$manifest['name']} ({$manifest['slug']})")
                        ->success()
                        ->send();

                    $this->reload($themes);
                }),

            Action::make('details')
                ->label('Details')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalHeading(function (array $arguments) {
                    $slug = (string) ($arguments['slug'] ?? '');
                    $m = $this->themes[$slug]['manifest'] ?? [];
                    return ($m['name'] ?? $slug) . ' — Details';
                })
                ->modalContent(function (array $arguments) {
                    $slug = (string) ($arguments['slug'] ?? '');
                    $theme = $this->themes[$slug] ?? null;

                    return view('filament.pages.partials.theme-details', [
                        'slug' => $slug,
                        'theme' => $theme,
                        'active' => $this->active,
                    ]);
                }),
        ];
    }

    public function activateTheme(string $slug, ThemeManager $themes): void
    {
        $themes->activate($slug);

        Notification::make()
            ->title('Theme activated')
            ->body("Active theme: {$slug}")
            ->success()
            ->send();

        $this->reload($themes);
    }
}