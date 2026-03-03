<?php

namespace Plugins\LinkMate\Filament\Pages;

use App\Cms\Core\SettingsRepository;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class LinkMateSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'CMS';
    protected static ?string $navigationLabel = 'LinkMate';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?int $navigationSort = 65;

    protected string $view = 'linkmate::filament.pages.linkmate-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    private function settingsGroup(): string
    {
        return \Plugins\LinkMate\LinkMate::SETTINGS_GROUP;
    }

    private function defaultKeywords(): string
    {
        return implode("\n", [
            'T-Shirt Manufacturer',
            'T-Shirt Manufacturers',
            'Polo Shirt Manufacturer',
            'Hoodie Manufacturer',
            'Hoodie Manufacturers',
            'Underwear Manufacturer',
            'Custom T-Shirt Manufacturer',
            'T-Shirt Factory',
            'Custom Sports Jersey Manufacturer',
            'OEM T-Shirt Manufacturer',
            'Trouser Manufacturer',
            'Promotional T-Shirt Supplier',
            'Hoodie Manufacturer',
            'Garment Manufacturer',
            'Denim Jeans Manufacturer',
            'Jeans Manufacturer',
            'Denim Pants Manufacturer',
            'Jeans Manufacturer',
            'Blank T-Shirt Supplier',
            'Blank T-Shirt Manufacturer',
            'Blank T-Shirt Manufacturers',
            'Pajama Manufacturer',
            'Pajama Supplier',
            'Pyjama Manufacturer',
            'Pyjama Set Manufacturers',
            'Pyjama Supplier',
            'Bangladesh T-Shirt Supplier',
            'Offshore Workwear T-Shirt Supplier',
        ]);
    }

    private function defaultUrls(): string
    {
        return implode("\n", [
            'https://www.siatex.ca',
            'https://www.siatexglobal.com',
            'https://www.siatexsourcing.com',
            'https://www.siatex.com',
            'https://www.siatexbd.com',
            'https://www.paimexco.com',
            'https://www.aboroni.com',
            'https://www.siatexgroup.com',
            'https://www.aptexltd.com',
            'https://primatexltd.com',
            'https://www.pritomtex.com',
            'https://www.wearpkd.com',
            'https://www.pkdclothing.com',
            'https://www.t-shirtmanufacturer.com',
        ]);
    }

    /**
     * Post types list:
     * - auto from posts table (distinct type)
     * - always include post + page
     * - add media
     * - add siatex_tag (Tag Defaults plugin rendering type)
     */
    private function postTypeOptions(): array
    {
        $types = Post::query()
            ->select('type')
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->map(fn($t) => (string) $t)
            ->filter(fn($t) => $t !== '')
            ->values()
            ->all();

        foreach (['post', 'page'] as $must) {
            if (!in_array($must, $types, true)) {
                $types[] = $must;
            }
        }

        sort($types);

        $out = [];
        foreach ($types as $t) {
            $out[$t] = ucfirst(str_replace(['-', '_'], ' ', $t));
        }

        $out['media'] = 'Media';

        // ✅ Added so LinkMate can run on Tag Defaults hook
        $out['siatex_tag'] = 'Siatex Tag';

        return $out;
    }

    public function mount(SettingsRepository $settings): void
    {
        $group = $this->settingsGroup();
        $typeOptions = $this->postTypeOptions();

        // ✅ Default enabled types = ALL checked (like WP screenshot)
        $defaultEnabled = array_keys($typeOptions);

        $enabledTypes = $settings->get($group, 'enabled_types', $defaultEnabled);
        $enabledTypes = is_array($enabledTypes) ? $enabledTypes : $defaultEnabled;

        // ✅ IMPORTANT: show defaults if saved value is empty string
        $keywordsSaved = (string) $settings->get($group, 'keywords', '');
        $urlsSaved = (string) $settings->get($group, 'urls', '');

        $keywordsValue = trim($keywordsSaved) !== '' ? $keywordsSaved : $this->defaultKeywords();
        $urlsValue = trim($urlsSaved) !== '' ? $urlsSaved : $this->defaultUrls();

        $this->data = [
            'keywords' => $keywordsValue,
            'urls' => $urlsValue,

            'enabled_types' => $enabledTypes,

            'max_links' => (int) $settings->get($group, 'max_links', 3),
            'word_gap' => (int) $settings->get($group, 'word_gap', 10),
            'max_keyword_uses' => (int) $settings->get($group, 'max_keyword_uses', 3),

            // ✅ screenshot defaults:
            'allow_in_bold' => (bool) $settings->get($group, 'allow_in_bold', true),          // checked
            'allow_in_headings' => (bool) $settings->get($group, 'allow_in_headings', false), // unchecked
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Form::make([
                    // ✅ 2 column layout (Filament Schemas Grid)
                    Grid::make(2)->schema([
                        Textarea::make('keywords')
                            ->label('Keywords (one per line)')
                            ->rows(8),

                        Textarea::make('urls')
                            ->label('URLs (one per line)')
                            ->rows(8),
                    ]),

                    Section::make('Enable for Post Types')
                        ->schema([
                            CheckboxList::make('enabled_types')
                                ->label('')
                                ->options(fn() => $this->postTypeOptions())
                                ->columns(2)
                                ->helperText('All post types are enabled by default (like WP).'),
                        ]),

                    Grid::make(2)->schema([
                        TextInput::make('max_links')
                            ->label('Max Links per Post')
                            ->numeric()
                            ->minValue(0)
                            ->default(3),

                        TextInput::make('word_gap')
                            ->label('Word Gap Between Links')
                            ->helperText('Minimum words between linked keywords.')
                            ->numeric()
                            ->minValue(0)
                            ->default(10),
                    ]),

                    TextInput::make('max_keyword_uses')
                        ->label('Max Keyword Uses per Post')
                        ->helperText('Maximum number of times a single keyword can be linked in a post.')
                        ->numeric()
                        ->minValue(0)
                        ->default(3),

                    // ✅ Add spacing so footer button isn't glued to last toggle
                    Section::make('Advanced')
                        ->schema([
                            Toggle::make('allow_in_bold')
                                ->label('Allow Links Inside <b> / <strong>')
                                ->default(true),

                            Toggle::make('allow_in_headings')
                                ->label('Allow Links Inside Headings (<h1> - <h6>)')
                                ->default(false),
                        ])
                        ->extraAttributes(['class' => 'pb-4']),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Changes')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(SettingsRepository $settings): void
    {
        $group = $this->settingsGroup();
        $data = $this->form->getState();

        $typeOptions = $this->postTypeOptions();
        $allTypes = array_keys($typeOptions);

        $enabled = $data['enabled_types'] ?? [];
        $enabled = is_array($enabled) ? $enabled : [];
        $enabled = array_values(array_intersect($enabled, $allTypes));

        $keywords = (string) ($data['keywords'] ?? '');
        $urls = (string) ($data['urls'] ?? '');

        // ✅ If user clears textarea, keep defaults (optional WP-like safety)
        if (trim($keywords) === '') {
            $keywords = $this->defaultKeywords();
        }
        if (trim($urls) === '') {
            $urls = $this->defaultUrls();
        }

        $settings->set($group, 'keywords', $keywords);
        $settings->set($group, 'urls', $urls);
        $settings->set($group, 'enabled_types', $enabled);

        $settings->set($group, 'max_links', (int) ($data['max_links'] ?? 3));
        $settings->set($group, 'word_gap', (int) ($data['word_gap'] ?? 10));
        $settings->set($group, 'max_keyword_uses', (int) ($data['max_keyword_uses'] ?? 3));

        $settings->set($group, 'allow_in_bold', (bool) ($data['allow_in_bold'] ?? true));
        $settings->set($group, 'allow_in_headings', (bool) ($data['allow_in_headings'] ?? false));

        Notification::make()
            ->success()
            ->title('Saved')
            ->send();

        $this->data['enabled_types'] = $enabled;
        $this->form->fill([
            ...$data,
            'keywords' => $keywords,
            'urls' => $urls,
            'enabled_types' => $enabled,
        ]);
    }

    // ✅ Top-right header Save button (optional; keeps only one bottom button)
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveTop')
                ->label('Save Changes')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action(fn() => $this->save(app(SettingsRepository::class))),
        ];
    }
}