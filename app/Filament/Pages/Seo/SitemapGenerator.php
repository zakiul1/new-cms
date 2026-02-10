<?php

namespace App\Filament\Pages\Seo;

use App\Cms\Core\SettingsRepository;
use App\Cms\Seo\SitemapGenerator as GeneratorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class SitemapGenerator extends Page
{
    protected static ?string $navigationLabel = 'Sitemap Generator';
    protected static string|UnitEnum|null $navigationGroup = 'SEO';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected string $view = 'filament.pages.seo.sitemap-generator';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(SettingsRepository $settings): void
    {
        $directory = (string) $settings->get('seo', 'sitemap_directory', '');

        $this->form->fill([
            'include_posts' => (bool) $settings->get('seo', 'sitemap_include_posts', true),
            'include_pages' => (bool) $settings->get('seo', 'sitemap_include_pages', true),
            'include_media' => (bool) $settings->get('seo', 'sitemap_include_media', false),

            'directory' => $directory,
            'max_links' => (int) $settings->get('seo', 'sitemap_max_links', 1000),
            'show_in_robots' => (bool) $settings->get('seo', 'sitemap_show_in_robots', true),

            // defaults 0.9
            'posts_priority' => (string) $settings->get('seo', 'sitemap_posts_priority', '0.9'),
            'pages_priority' => (string) $settings->get('seo', 'sitemap_pages_priority', '0.9'),
            'media_priority' => (string) $settings->get('seo', 'sitemap_media_priority', '0.9'),

            'posts_changefreq' => (string) $settings->get('seo', 'sitemap_posts_changefreq', 'weekly'),
            'pages_changefreq' => (string) $settings->get('seo', 'sitemap_pages_changefreq', 'monthly'),
            'media_changefreq' => (string) $settings->get('seo', 'sitemap_media_changefreq', 'monthly'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete_sitemap')
                ->label('Delete Sitemap')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (GeneratorService $generator) {
                    $generator->deleteAll();

                    Notification::make()
                        ->success()
                        ->title('Sitemap deleted')
                        ->body('All generated sitemap files removed.')
                        ->send();
                }),
            Action::make('save')
                ->label('Update Settings')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action(fn() => $this->save(app(SettingsRepository::class)))
                ->keyBindings(['mod+s']),



            Action::make('view_sitemap')
                ->label('View Sitemap')
                ->icon('heroicon-o-eye')
                ->url(fn() => url('/sitemap.xml'), shouldOpenInNewTab: true),
            Action::make('generate_all')
                ->label('Generate All')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function (GeneratorService $generator) {
                    $this->save(app(SettingsRepository::class));

                    $files = $generator->generateAll();

                    Notification::make()
                        ->success()
                        ->title('Sitemap generated')
                        ->body('Generated: ' . implode(', ', array_map('basename', $files)))
                        ->send();
                }),


        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make()
                    ->columns([
                        'default' => 1,
                        'lg' => 3,
                    ])
                    ->schema([
                        // LEFT (span 2)
                        Grid::make()
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ])
                            ->schema([
                                Section::make('Content')
                                    ->description('Select what should be included. Only PUBLIC content will appear in sitemap (private is excluded automatically).')
                                    ->schema([
                                        SchemaView::make('filament.pages.seo.partials.sitemap-content')
                                            ->viewData([
                                                'data' => fn() => $this->data,
                                            ]),
                                    ]),

                                Section::make('Change Frequency & Priority')
                                    ->description('Applies to generated URLs for each content type.')
                                    ->schema([
                                        SchemaView::make('filament.pages.seo.partials.sitemap-frequency-priority')
                                            ->viewData([
                                                'data' => fn() => $this->data,
                                            ]),
                                    ]),
                            ]),

                        // RIGHT sidebar (span 1)
                        Section::make('')
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->extraAttributes(['class' => 'space-y-6'])
                            ->schema([
                                Section::make('Output')
                                    ->collapsed()
                                    ->schema([
                                        SchemaView::make('filament.pages.seo.partials.sitemap-output')
                                            ->viewData([
                                                'data' => fn() => $this->data,
                                            ]),
                                    ]),

                                Section::make('Status')
                                    ->schema([
                                        SchemaView::make('filament.pages.seo.partials.sitemap-status')
                                            ->viewData([
                                                'data' => fn() => $this->data,
                                                'lastGenerated' => fn() => (string) app(SettingsRepository::class)->get('seo', 'sitemap_last_generated_at', '—'),
                                            ]),
                                    ]),

                                Section::make('Generated XML Files')
                                    ->description('View the generated XML files (public disk).')
                                    ->schema([
                                        SchemaView::make('filament.pages.seo.partials.sitemap-files')
                                            ->viewData([
                                                'files' => fn() => $this->sitemapFilesForUi((string) ($this->data['directory'] ?? '')),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function save(SettingsRepository $settings): void
    {
        // ✅ IMPORTANT: inputs are plain Blade (SchemaView), so Livewire updates $this->data.
        // Filament form state may be empty/stale when no real Form fields exist.
        $state = $this->data;

        $settings->set('seo', 'sitemap_include_posts', (bool) ($state['include_posts'] ?? true));
        $settings->set('seo', 'sitemap_include_pages', (bool) ($state['include_pages'] ?? true));
        $settings->set('seo', 'sitemap_include_media', (bool) ($state['include_media'] ?? false));

        $settings->set('seo', 'sitemap_directory', (string) ($state['directory'] ?? ''));
        $settings->set('seo', 'sitemap_max_links', (int) ($state['max_links'] ?? 1000));
        $settings->set('seo', 'sitemap_show_in_robots', (bool) ($state['show_in_robots'] ?? true));

        $settings->set('seo', 'sitemap_posts_priority', (string) ($state['posts_priority'] ?? '0.9'));
        $settings->set('seo', 'sitemap_pages_priority', (string) ($state['pages_priority'] ?? '0.9'));
        $settings->set('seo', 'sitemap_media_priority', (string) ($state['media_priority'] ?? '0.9'));

        $settings->set('seo', 'sitemap_posts_changefreq', (string) ($state['posts_changefreq'] ?? 'weekly'));
        $settings->set('seo', 'sitemap_pages_changefreq', (string) ($state['pages_changefreq'] ?? 'monthly'));
        $settings->set('seo', 'sitemap_media_changefreq', (string) ($state['media_changefreq'] ?? 'monthly'));

        // category selection is automatic
        $settings->set('seo', 'sitemap_post_category_ids', []);
        $settings->set('seo', 'sitemap_media_category_ids', []);

        Notification::make()
            ->success()
            ->title('Updated')
            ->body('Settings saved.')
            ->send();
    }

    /**
     * @return array<int, array{name:string,url:string,size:string,modified:string}>
     */
    private function sitemapFilesForUi(string $directory): array
    {
        $disk = Storage::disk('public');
        $dir = trim($directory, '/');

        $paths = $dir === '' ? $disk->files() : $disk->files($dir);

        $paths = array_values(array_filter($paths, function (string $path): bool {
            $name = basename($path);

            if ($name === 'sitemap.xml') {
                return true;
            }

            return (bool) preg_match('/^(pages|posts|media)(-\d+)?\.xml$/', $name);
        }));

        usort($paths, function (string $a, string $b): int {
            $ra = $this->fileRank(basename($a));
            $rb = $this->fileRank(basename($b));

            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return basename($a) <=> basename($b);
        });

        return array_map(function (string $path) use ($disk): array {
            $name = basename($path);

            $url = $disk->url($path);

            $size = $disk->exists($path) ? (int) $disk->size($path) : 0;
            $modified = $disk->exists($path) ? (int) $disk->lastModified($path) : 0;

            return [
                'name' => $name,
                'url' => $url,
                'size' => $size ? number_format($size / 1024, 1) . ' KB' : '—',
                'modified' => $modified ? date('Y-m-d H:i:s', $modified) : '—',
            ];
        }, $paths);
    }

    private function fileRank(string $name): int
    {
        if ($name === 'sitemap.xml') {
            return 0;
        }
        if (str_starts_with($name, 'pages')) {
            return 1;
        }
        if (str_starts_with($name, 'posts')) {
            return 2;
        }
        if (str_starts_with($name, 'media')) {
            return 3;
        }

        return 9;
    }
}