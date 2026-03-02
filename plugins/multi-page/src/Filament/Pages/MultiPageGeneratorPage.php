<?php

namespace Plugins\MultiPage\Filament\Pages;

use BackedEnum;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Plugins\MultiPage\Support\MultiPageGenerator;
use Plugins\MultiPage\Support\MultiPageStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MultiPageGeneratorPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    // Filament v5: Page::$view is NON-static in your install
    public string $view = 'multi-page::filament.pages.multipage-generator';

    protected static ?string $title = 'Multi Page Generator';

    public ?int $page_id = null;

    public array $data = [
        'enabled' => false,
        'csv_file' => '',
        'url_structure' => '',
        'default_segments' => '',
        'has_header' => false,
    ];

    public ?string $lastMessage = null;

    public function mount(): void
    {
        MultiPageStorage::ensureDirs();

        $this->form->fill([
            'page_id' => $this->page_id,
            'data' => $this->data,
        ]);
    }

    /**
     * Filament v5: Forms use Schema (not Forms\Form)
     */
    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(2)->schema([
                Section::make('Select Page')->schema([
                    Forms\Components\Select::make('page_id')
                        ->label('Base Page')
                        ->options(fn() => Post::query()
                            ->where('type', 'page')
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->toArray())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn() => $this->loadPageConfig())
                        ->required(),
                ])->columnSpan(1),

                Section::make('CSV Upload')->schema([
                    Forms\Components\FileUpload::make('csv_upload')
                        ->label('Upload CSV')
                        ->disk('local')
                        ->directory(MultiPageStorage::CSVS)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->preserveFilenames()
                        ->maxSize(10240)
                        ->dehydrated(false)
                        ->helperText('Uploaded to storage/app/private/' . MultiPageStorage::CSVS)

                        // ✅ Store immediately (Livewire v3 / Filament v5 safe)
                        ->afterStateUpdated(function ($state, callable $set): void {
                            MultiPageStorage::ensureDirs();

                            // Livewire typically gives TemporaryUploadedFile here
                            if ($state instanceof TemporaryUploadedFile) {
                                $name = $state->getClientOriginalName();

                                $state->storeAs(MultiPageStorage::CSVS, $name, 'local');

                                // ✅ Set the selected CSV for generation
                                $this->data['csv_file'] = $name;

                                // ✅ Refresh form state
                                $this->form->fill([
                                    'page_id' => $this->page_id,
                                    'data' => $this->data,
                                ]);
                            }

                            // ✅ Clear upload field state (remove temp)
                            $set('csv_upload', null);

                            // Optional: refresh whole UI
                            $this->dispatch('$refresh');
                        }),
                ])->columnSpan(1),
            ]),

            Section::make('Multi Page Settings')->schema([
                Forms\Components\Toggle::make('data.enabled')
                    ->label('Enable Multi Page'),

                Forms\Components\Select::make('data.csv_file')
                    ->label('CSV File')
                    ->options(fn() => $this->csvOptions())
                    ->searchable()
                    ->helperText('Pick an existing file in storage/app/private/' . MultiPageStorage::CSVS),

                Forms\Components\Toggle::make('data.has_header')
                    ->label('CSV has header row (skip first row)'),

                Forms\Components\TextInput::make('data.url_structure')
                    ->label('URL Structure')
                    ->helperText('Example: products/{col1}/{col2} or {col1}/{col2}')
                    ->required(),

                Forms\Components\TextInput::make('data.default_segments')
                    ->label('Default Segments (comma separated)')
                    ->helperText('Example: Bangladesh, Dhaka (used to 301 redirect default URL to /{page-slug})'),
            ]),
        ]);
    }

    /**
     * Filament v5: actions appear in the page header
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveConfig')
                ->label('Save Settings')
                ->action(fn() => $this->savePageConfig())
                ->color('primary'),

            Action::make('generate')
                ->label('Generate Links')
                ->action(fn() => $this->generateLinks())
                ->color('success'),

            Action::make('downloadTracker')
                ->label('Download Tracker JSON')
                ->action(fn() => $this->downloadTracker())
                ->color('gray'),
        ];
    }

    private function csvOptions(): array
    {
        MultiPageStorage::ensureDirs();

        $disk = Storage::disk('local');
        $disk->makeDirectory(MultiPageStorage::CSVS);

        $files = $disk->files(MultiPageStorage::CSVS);

        $out = [];
        foreach ($files as $f) {
            $name = basename($f);
            $out[$name] = $name;
        }

        ksort($out);
        return $out;
    }

    private function loadPageConfig(): void
    {
        $this->lastMessage = null;

        if (!$this->page_id) {
            return;
        }

        $page = Post::query()
            ->whereKey($this->page_id)
            ->where('type', 'page')
            ->first();

        if (!$page) {
            $this->lastMessage = 'Page not found.';
            return;
        }

        $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
        $cfg = is_array($meta['multipage'] ?? null) ? $meta['multipage'] : [];

        $this->data = array_merge($this->data, [
            'enabled' => (bool) ($cfg['enabled'] ?? false),
            'csv_file' => (string) ($cfg['csv_file'] ?? ''),
            'url_structure' => (string) ($cfg['url_structure'] ?? ''),
            'default_segments' => (string) ($cfg['default_segments'] ?? ''),
            'has_header' => (bool) ($cfg['has_header'] ?? false),
        ]);

        $this->form->fill([
            'page_id' => $this->page_id,
            'data' => $this->data,
        ]);
    }

    private function savePageConfig(): void
    {
        $this->lastMessage = null;

        $state = $this->form->getState();
        $pageId = (int) ($state['page_id'] ?? 0);

        $page = Post::query()
            ->whereKey($pageId)
            ->where('type', 'page')
            ->first();

        if (!$page) {
            $this->lastMessage = 'Page not found.';
            return;
        }

        $data = $state['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
        $meta['multipage'] = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'csv_file' => trim((string) ($data['csv_file'] ?? '')),
            'url_structure' => trim((string) ($data['url_structure'] ?? '')),
            'default_segments' => trim((string) ($data['default_segments'] ?? '')),
            'has_header' => (bool) ($data['has_header'] ?? false),
        ];

        $page->meta_json = $meta;
        $page->save();

        $this->lastMessage = 'Saved.';
    }

    private function generateLinks(): void
    {
        $this->lastMessage = null;

        $state = $this->form->getState();
        $pageId = (int) ($state['page_id'] ?? 0);

        $page = Post::query()
            ->whereKey($pageId)
            ->where('type', 'page')
            ->first();

        if (!$page) {
            $this->lastMessage = 'Page not found.';
            return;
        }

        $this->savePageConfig();

        $gen = new MultiPageGenerator();
        $res = $gen->generateForPage($page);

        $this->lastMessage = "Generated {$res['count']} links. Tracker: {$res['tracker_path']}";
    }

    private function downloadTracker(): ?BinaryFileResponse
    {
        $state = $this->form->getState();
        $pageId = (int) ($state['page_id'] ?? 0);

        $page = Post::query()
            ->whereKey($pageId)
            ->where('type', 'page')
            ->first();

        if (!$page) {
            $this->lastMessage = 'Page not found.';
            return null;
        }

        // Keep consistent with LinksPage fallback logic if slug is empty
        $trackerKey = (string) ($page->slug ?? '');
        if (trim($trackerKey) === '') {
            $trackerKey = 'multipage-' . (int) $page->id;
        }

        $path = MultiPageStorage::TRACKERS . '/' . $trackerKey . '.json';

        if (!Storage::disk('local')->exists($path)) {
            $this->lastMessage = 'Tracker not found. Generate first.';
            return null;
        }

        return response()->download(
            Storage::disk('local')->path($path),
            $trackerKey . '-tracker.json'
        );
    }
}