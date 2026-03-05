<?php

namespace App\Filament\Pages\Seo;

use App\Cms\Core\SettingsRepository;
use App\Cms\Seo\SitemapGenerator as GeneratorService;
use App\Models\Post;
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

    /**
     * Get all content types (post types) found in DB.
     * Also appends virtual sitemap types (e.g. siatex-tags) if their plugin model exists.
     *
     * @return array<int, string>
     */
    private function discoverContentTypes(): array
    {
        $types = Post::query()
            ->select('type')
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values()
            ->all();

        // ✅ Virtual type: Siatex Tags (plugin)
        if (class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
            $types[] = 'siatex-tags';
        }

        return array_values(array_unique($types));
    }

    /**
     * Human label for a type slug.
     */
    private function typeLabel(string $type): string
    {
        // page => Pages, post => Posts, siatex-tags => Siatex Tags, etc.
        $t = str_replace(['_', '-'], ' ', $type);
        $t = trim($t);

        if ($type === 'page') {
            return 'Pages';
        }
        if ($type === 'post') {
            return 'Posts';
        }
        if ($type === 'siatex-tags') {
            return 'Siatex Tags';
        }

        return ucwords($t);
    }

    public function mount(SettingsRepository $settings): void
    {
        $directory = (string) $settings->get('seo', 'sitemap_directory', '');

        $availableTypes = $this->discoverContentTypes();

        // New dynamic selection (fallback to legacy)
        $selectedTypes = $settings->get('seo', 'sitemap_include_types', null);

        if (!is_array($selectedTypes) || count($selectedTypes) === 0) {
            // fallback to old toggles
            $selectedTypes = [];

            if ((bool) $settings->get('seo', 'sitemap_include_pages', true)) {
                $selectedTypes[] = 'page';
            }
            if ((bool) $settings->get('seo', 'sitemap_include_posts', true)) {
                $selectedTypes[] = 'post';
            }

            // ✅ Default include virtual type if available (and no saved selection exists yet)
            if (in_array('siatex-tags', $availableTypes, true)) {
                $selectedTypes[] = 'siatex-tags';
            }
        }

        // constrain to existing types
        $selectedTypes = array_values(array_intersect(array_map('strval', $selectedTypes), $availableTypes));

        // Precompute labels for blade
        $typeOptions = [];
        foreach ($availableTypes as $t) {
            $typeOptions[$t] = $this->typeLabel($t);
        }

        $this->form->fill([
            // ✅ Dynamic types
            'include_types' => $selectedTypes,
            'type_options' => $typeOptions, // for blade display

            // ✅ Keep media separate (not a Post type)
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

        // Also sync into $data because your UI uses Blade + Livewire updates $this->data
        $this->data = array_merge($this->data, $this->form->getState());
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
                                                // Provide options for dynamic types
                                                'contentTypes' => fn() => (array) ($this->data['type_options'] ?? []),
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
        $state = $this->data;

        // ✅ New dynamic types (array)
        $includeTypes = $state['include_types'] ?? [];
        if (!is_array($includeTypes)) {
            $includeTypes = [];
        }
        $includeTypes = array_values(array_filter(array_map(fn($v) => trim((string) $v), $includeTypes)));

        $settings->set('seo', 'sitemap_include_types', $includeTypes);

        // Keep legacy toggles updated for backward compatibility (optional)
        $settings->set('seo', 'sitemap_include_pages', in_array('page', $includeTypes, true));
        $settings->set('seo', 'sitemap_include_posts', in_array('post', $includeTypes, true));

        // ✅ Media toggle stays separate
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

        // Show sitemap.xml + any generated sitemap part xml files
        $paths = array_values(array_filter($paths, function (string $path): bool {
            $name = basename($path);

            if ($name === 'sitemap.xml') {
                return true;
            }

            // Any "{slug}.xml" or "{slug}-2.xml"
            return (bool) preg_match('/^[A-Za-z0-9\-_]+(?:-\d+)?\.xml$/', $name);
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

        // Prefer page/post/media first if they exist
        if (str_starts_with($name, 'page')) {
            return 1;
        }
        if (str_starts_with($name, 'post')) {
            return 2;
        }
        if (str_starts_with($name, 'media')) {
            return 3;
        }

        // Put siatex-tags near posts
        if (str_starts_with($name, 'siatex-tags')) {
            return 4;
        }

        return 9;
    }
}