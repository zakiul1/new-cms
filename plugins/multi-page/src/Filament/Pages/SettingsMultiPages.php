<?php

namespace Plugins\MultiPage\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Plugins\MultiPage\Support\MultiPageSettings;
use Plugins\MultiPage\Support\MultiPageSitemapGenerator;
use Plugins\MultiPage\Support\MultiPageStorage;
use UnitEnum;

class SettingsMultiPages extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Settings Multipages';
    protected static string|UnitEnum|null $navigationGroup = 'Pages';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 52;

    protected string $view = 'multi-page::filament.pages.settings-multipages';

    public ?array $data = [];

    public array $csvFiles = [];
    public array $csvPreview = [];
    public ?string $selectedCsv = null;

    public string $randKeys = '';
    public string $externalLinks = '';

    public ?string $lastSitemapUrl = null;

    public function mount(): void
    {
        MultiPageStorage::ensureDirs();

        $this->data = MultiPageSettings::load();
        $this->randKeys = MultiPageSettings::loadRandKeys();
        $this->externalLinks = MultiPageSettings::loadExternalLinks();

        $this->refreshCsvFiles(); // also auto-select first file if available

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Generate & Settings')
                            ->schema([
                                Section::make('Sitemap Settings')
                                    ->schema([
                                        TextInput::make('sitemaps_dir')
                                            ->label('Sitemaps Dir (public)')
                                            ->helperText('Stored in: storage/app/public/<dir>/')
                                            ->required(),

                                        TextInput::make('max_links_per_file')
                                            ->label('Max Links (per file)')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('file_base_name')
                                            ->label('File Name')
                                            ->helperText('Example: multipage-sitemap (will create multipage-sitemap.xml)')
                                            ->required(),

                                        DatePicker::make('modified_date')
                                            ->label('Modified Date')
                                            ->required(),
                                    ])
                                    ->columns(2),

                                Section::make('Generate')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('sitemap_info')
                                            ->label('Sitemap URL')
                                            ->content(fn() => $this->lastSitemapUrl ?: 'Not generated yet')
                                            ->dehydrated(false),
                                    ]),
                            ]),

                        /**
                         * ✅ DATA TAB (Left: upload + file list, Right: preview)
                         */
                        Tab::make('Data')
                            ->schema([
                                Section::make('')
                                    ->columns(2)
                                    ->schema([
                                        // LEFT PANEL
                                        Section::make('Data Files')
                                            ->columnSpan(1)
                                            ->schema([
                                                FileUpload::make('csv_upload')
                                                    ->label('Upload CSV')
                                                    ->disk('local')
                                                    ->directory(MultiPageStorage::CSVS)
                                                    ->preserveFilenames()
                                                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                                                    ->maxSize(50 * 1024)
                                                    ->helperText('Uploads into: storage/app/private/' . MultiPageStorage::CSVS)
                                                    ->multiple(false)
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, callable $set): void {
                                                        MultiPageStorage::ensureDirs();

                                                        if ($state instanceof TemporaryUploadedFile) {
                                                            $name = $state->getClientOriginalName();
                                                            $state->storeAs(MultiPageStorage::CSVS, $name, 'local');
                                                        }

                                                        $set('csv_upload', null);

                                                        $this->refreshCsvFiles();
                                                        $this->dispatch('$refresh');
                                                    }),

                                                ViewField::make('csv_files_list')
                                                    ->view('multi-page::filament.components.csv-files-list')
                                                    ->viewData(fn() => [
                                                        'files' => $this->csvFiles,
                                                        'selected' => $this->selectedCsv,
                                                    ])
                                                    ->dehydrated(false),
                                            ]),

                                        // RIGHT PANEL
                                        Section::make('Preview')
                                            ->columnSpan(1)
                                            ->schema([
                                                ViewField::make('csv_preview_table')
                                                    ->view('multi-page::filament.components.csv-preview')
                                                    ->viewData(fn() => [
                                                        'selected' => $this->selectedCsv,
                                                        'rows' => $this->csvPreview,
                                                    ])
                                                    ->dehydrated(false),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Rand Key Data')
                            ->schema([
                                Section::make('Keywords')
                                    ->headerActions([
                                        Action::make('save_rand_keys')
                                            ->label('Save Keywords')
                                            ->color('primary')
                                            ->action(function () {
                                                $state = $this->form->getState();

                                                $text = (string) ($state['rand_keys_text'] ?? $this->randKeys ?? '');
                                                $this->randKeys = $text;

                                                MultiPageSettings::saveRandKeys($text);

                                                Notification::make()
                                                    ->success()
                                                    ->title('Saved')
                                                    ->body('Rand Key Data saved.')
                                                    ->send();
                                            }),
                                    ])
                                    ->schema([
                                        Textarea::make('rand_keys_text')
                                            ->label('Rand Key Data')
                                            ->rows(18)
                                            ->default(fn() => $this->randKeys)
                                            ->dehydrated(false)
                                            ->live(),
                                    ]),
                            ]),

                        Tab::make('External Links')
                            ->schema([
                                Section::make('External Links')
                                    ->headerActions([
                                        Action::make('save_external_links')
                                            ->label('Save External Links')
                                            ->color('primary')
                                            ->action(function () {
                                                $state = $this->form->getState();

                                                $text = (string) ($state['external_links_text'] ?? $this->externalLinks ?? '');
                                                $this->externalLinks = $text;

                                                MultiPageSettings::saveExternalLinks($text);

                                                Notification::make()
                                                    ->success()
                                                    ->title('Saved')
                                                    ->body('External Links saved.')
                                                    ->send();
                                            }),
                                    ])
                                    ->schema([
                                        Textarea::make('external_links_text')
                                            ->label('External Links')
                                            ->rows(18)
                                            ->default(fn() => $this->externalLinks)
                                            ->dehydrated(false)
                                            ->live(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * ✅ Only keep global actions in header.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_settings')
                ->label('Save Settings')
                ->action(function () {
                    MultiPageSettings::save($this->data ?? []);

                    Notification::make()
                        ->success()
                        ->title('Saved')
                        ->body('Multipage settings saved.')
                        ->send();
                }),

            Action::make('generate_sitemap')
                ->label('Generate Sitemap')
                ->action(function () {
                    MultiPageSettings::save($this->data ?? []);

                    $gen = new MultiPageSitemapGenerator();
                    $res = $gen->generate();

                    $this->lastSitemapUrl = $res['index'] ?? null;

                    Notification::make()
                        ->success()
                        ->title('Sitemap Generated')
                        ->body('Total links: ' . ($res['count'] ?? 0))
                        ->send();
                }),

            Action::make('view_sitemap')
                ->label('View Sitemap')
                ->url(fn() => $this->lastSitemapUrl ?: '/multipage-sitemap.xml', shouldOpenInNewTab: true),
        ];
    }

    /**
     * ✅ Refresh file list + auto select first file
     */
    public function refreshCsvFiles(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(MultiPageStorage::CSVS);

        $files = $disk->files(MultiPageStorage::CSVS);

        $names = array_values(array_map(fn($f) => basename($f), $files));
        sort($names);

        $this->csvFiles = $names;

        // Auto select first file if none selected
        if (!$this->selectedCsv && count($names) > 0) {
            $this->selectCsv($names[0]);
        }
    }

    public function selectCsv(string $file): void
    {
        $file = basename(trim($file));
        $this->selectedCsv = $file;
        $this->loadCsvPreview($file);
    }

    public function deleteCsv(string $file): void
    {
        $file = basename(trim($file));
        if ($file === '')
            return;

        $disk = Storage::disk('local');
        $path = MultiPageStorage::CSVS . '/' . $file;

        if ($disk->exists($path)) {
            $disk->delete($path);

            Notification::make()
                ->success()
                ->title('Deleted')
                ->body("Deleted: {$file}")
                ->send();
        }

        if ($this->selectedCsv === $file) {
            $this->selectedCsv = null;
            $this->csvPreview = [];
        }

        $this->refreshCsvFiles();
        $this->dispatch('$refresh');
    }

    public function downloadCsv(string $file)
    {
        $file = basename(trim($file));
        if ($file === '')
            return null;

        $disk = Storage::disk('local');
        $path = MultiPageStorage::CSVS . '/' . $file;

        if (!$disk->exists($path)) {
            Notification::make()
                ->danger()
                ->title('File not found')
                ->body($file)
                ->send();

            return null;
        }

        return response()->download($disk->path($path), $file);
    }

    private function loadCsvPreview(string $file): void
    {
        $file = basename(trim($file));
        $this->csvPreview = [];

        if ($file === '')
            return;

        $disk = Storage::disk('local');
        $path = MultiPageStorage::CSVS . '/' . $file;

        if (!$disk->exists($path))
            return;

        $full = $disk->path($path);
        $fh = fopen($full, 'rb');
        if (!$fh)
            return;

        $rows = [];
        $limit = 50;

        while (($row = fgetcsv($fh)) !== false) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }

        fclose($fh);

        $this->csvPreview = $rows;
    }
}