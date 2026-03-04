<?php

namespace Plugins\MultiPage\Filament\Pages;

use App\Filament\Forms\Components\WpClassicEditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
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

    public ?string $lastSitemapUrl = null;

    public function mount(): void
    {
        MultiPageStorage::ensureDirs();

        // Load settings (now defaults allow sitemaps_dir = '')
        $this->data = MultiPageSettings::load();

        // Ensure numeric defaults
        $this->data['max_links_per_file'] = (int) ($this->data['max_links_per_file'] ?? 20000);
        if ($this->data['max_links_per_file'] <= 0) {
            $this->data['max_links_per_file'] = 20000;
        }

        // Ensure file base defaults
        $this->data['file_base_name'] = trim((string) ($this->data['file_base_name'] ?? 'static')) ?: 'static';

        // Ensure changefreq
        $this->data['changefreq'] = trim((string) ($this->data['changefreq'] ?? 'weekly')) ?: 'weekly';

        // Ensure priority [0..1]
        $priority = (string) ($this->data['priority'] ?? '0.9');
        if ($priority === '' || !is_numeric($priority)) {
            $priority = '0.9';
        }
        $p = (float) $priority;
        if ($p < 0) {
            $p = 0;
        }
        if ($p > 1) {
            $p = 1;
        }
        $this->data['priority'] = number_format($p, 1, '.', '');

        // Modified date default
        $this->data['modified_date'] = $this->data['modified_date'] ?? now()->toDateString();

        // IMPORTANT: allow blank sitemaps_dir (root)
        $this->data['sitemaps_dir'] = trim((string) ($this->data['sitemaps_dir'] ?? ''));
        $this->data['sitemaps_dir'] = trim($this->data['sitemaps_dir'], '/'); // keep '' allowed

        $this->refreshCsvFiles();
        $this->form->fill($this->data);

        // Preview URL based on root/folder mode
        $this->lastSitemapUrl = $this->computeSitemapUrl(
            (string) ($this->data['file_base_name'] ?? 'static'),
            (string) ($this->data['sitemaps_dir'] ?? '')
        );
    }

    /**
     * ✅ Used by "Save" button inside Company Info tab (blade button)
     */
    public function saveCompanyInfo(): void
    {
        MultiPageSettings::save($this->data ?? []);

        Notification::make()
            ->success()
            ->title('Saved')
            ->body('Company info saved.')
            ->send();
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
                                ViewField::make('multipage_sitemap_tab_actions')
                                    ->view('multi-page::filament.components.multipage-sitemap-tab-actions')
                                    ->viewData(fn() => [
                                        'url' => $this->lastSitemapUrl ?: $this->computeSitemapUrl(
                                            (string) ($this->data['file_base_name'] ?? 'static'),
                                            (string) ($this->data['sitemaps_dir'] ?? '')
                                        ),
                                    ])
                                    ->dehydrated(false),

                                Section::make('Sitemap Settings')
                                    ->schema([
                                        TextInput::make('sitemaps_dir')
                                            ->label('Sitemaps Dir (public)')
                                            ->helperText('Leave blank to store at ROOT (e.g. /static.xml).')
                                            ->default('')
                                            ->disabled()      // ✅ blocks typing + editing
                                            ->dehydrated(true), // ✅ still saved with form (keep it)

                                        TextInput::make('max_links_per_file')
                                            ->label('Max Links (per file)')
                                            ->numeric()
                                            ->default(20000)
                                            ->required(),

                                        TextInput::make('file_base_name')
                                            ->label('File Name')
                                            ->default('static')
                                            ->helperText('Example: multipage-sitemap (will create multipage-sitemap.xml)')
                                            ->required()
                                            ->live(debounce: 400)
                                            ->afterStateUpdated(function ($state): void {
                                                $name = trim((string) $state) ?: 'static';
                                                $dir = trim((string) ($this->data['sitemaps_dir'] ?? ''));
                                                $this->lastSitemapUrl = $this->computeSitemapUrl($name, $dir);
                                            }),

                                        DatePicker::make('modified_date')
                                            ->label('Modified Date')
                                            ->default(now()->toDateString())
                                            ->required(),

                                        Select::make('changefreq')
                                            ->label('Change Frequency')
                                            ->default('weekly')
                                            ->options([
                                                'always' => 'Always',
                                                'hourly' => 'Hourly',
                                                'daily' => 'Daily',
                                                'weekly' => 'Weekly',
                                                'monthly' => 'Monthly',
                                                'yearly' => 'Yearly',
                                                'never' => 'Never',
                                            ])
                                            ->native(false)
                                            ->required(),

                                        TextInput::make('priority')
                                            ->label('Priority')
                                            ->default('0.9')
                                            ->numeric()
                                            ->step('0.1')
                                            ->minValue(0)
                                            ->maxValue(1)
                                            ->helperText('Allowed range: 0.0 to 1.0 (example: 0.5)')
                                            ->required(),

                                        ViewField::make('sitemap_generate_button')
                                            ->view('multi-page::filament.components.multipage-sitemap-generate-inline')
                                            ->viewData(fn() => [])
                                            ->dehydrated(false),
                                    ])
                                    ->columns(2),

                                Section::make('Generate')
                                    ->schema([
                                        Placeholder::make('sitemap_info')
                                            ->label('Sitemap URL')
                                            ->content(fn() => $this->lastSitemapUrl ?: 'Not generated yet')
                                            ->dehydrated(false),
                                    ]),
                            ]),

                        Tab::make('Data')
                            ->schema([
                                Section::make('')
                                    ->columns(2)
                                    ->schema([
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

                        Tab::make('Company Info')
                            ->schema([
                                Section::make('Company Info')
                                    ->schema([
                                        ViewField::make('company_info_save_button_top')
                                            ->view('multi-page::filament.components.company-info-save')
                                            ->dehydrated(false),

                                        WpClassicEditor::make('company_info')
                                            ->label('Company Info')
                                            ->height(260)
                                            ->columnSpanFull()
                                            ->helperText('Shown in the right sidebar (4-column) on the MultiPage template. Shortcodes supported.')
                                            ->formatStateUsing(
                                                fn($state): string => is_string($state)
                                                ? $state
                                                : (is_array($state) ? (string) ($state['html'] ?? '') : '')
                                            )
                                            ->dehydrateStateUsing(fn($state) => is_string($state) ? $state : ''),
                                    ]),
                            ]),
                    ]),
            ]);
    }

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

                    // refresh preview
                    $name = trim((string) ($this->data['file_base_name'] ?? 'static')) ?: 'static';
                    $dir = trim((string) ($this->data['sitemaps_dir'] ?? ''));
                    $this->lastSitemapUrl = $this->computeSitemapUrl($name, $dir);
                }),

            Action::make('generate_sitemap')
                ->label('Generate Sitemap')
                ->action(fn() => $this->generateSitemap()),
        ];
    }

    /**
     * ✅ Reusable generator (used by header button and inline button)
     */
    public function generateSitemap(): void
    {
        MultiPageSettings::save($this->data ?? []);

        $gen = new MultiPageSitemapGenerator();
        $res = $gen->generate();

        // Update preview URL based on current settings
        $name = trim((string) ($this->data['file_base_name'] ?? 'static')) ?: 'static';
        $dir = trim((string) ($this->data['sitemaps_dir'] ?? ''));
        $this->lastSitemapUrl = $this->computeSitemapUrl($name, $dir);

        Notification::make()
            ->success()
            ->title('Sitemap Generated')
            ->body('Total links: ' . ($res['count'] ?? 0))
            ->send();

        $this->dispatch('$refresh');
    }

    public function refreshCsvFiles(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(MultiPageStorage::CSVS);

        $files = $disk->files(MultiPageStorage::CSVS);

        $names = array_values(array_map(fn($f) => basename($f), $files));
        sort($names);

        $this->csvFiles = $names;

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
        if ($file === '') {
            return;
        }

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
        if ($file === '') {
            return null;
        }

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

        if ($file === '') {
            return;
        }

        $disk = Storage::disk('local');
        $path = MultiPageStorage::CSVS . '/' . $file;

        if (!$disk->exists($path)) {
            return;
        }

        $full = $disk->path($path);
        $fh = fopen($full, 'rb');
        if (!$fh) {
            return;
        }

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

    /**
     * Build public URL based on settings:
     * - root: /static.xml
     * - folder: /storage/<dir>/static.xml
     */
    private function computeSitemapUrl(string $baseName, string $dir): string
    {
        $baseName = trim($baseName) ?: 'static';
        $dir = trim($dir);
        $dir = trim($dir, '/');

        if ($dir === '') {
            return '/' . $baseName . '.xml';
        }

        return '/' . trim('storage/' . $dir . '/' . $baseName . '.xml', '/');
    }
}