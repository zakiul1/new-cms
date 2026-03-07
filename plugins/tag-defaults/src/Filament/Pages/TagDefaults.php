<?php

namespace Plugins\TagDefaults\Filament\Pages;

use App\Cms\Core\Settings;
use App\Filament\Forms\Components\WpClassicEditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class TagDefaults extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Tags';
    protected static ?string $navigationLabel = 'Tags Default';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 61;

    protected string $view = 'tag-defaults::filament.pages.tag-defaults';

    public ?int $previewTagId = null;

    public string $previewNonce = '';

    /**
     * IMPORTANT:
     * - Keep default_custom_json as STRING in the form state
     * - Convert to array ONLY when saving to Settings
     */
    public array $data = [
        'default_title' => '',
        'default_description' => '',
        'default_sub_title' => '',
        'default_sub_description' => '',

        // SEO defaults (Premium)
        'default_seo_title' => '',
        'default_seo_description' => '',
        'default_seo_canonical' => '',
        'default_seo_robots' => '',
        'default_seo_og_image' => '',

        'default_assets_css' => '',
        'default_assets_js' => '',

        // string in UI
        'default_custom_json' => '',
    ];

    protected function getForms(): array
    {
        return ['form'];
    }

    public function mount(): void
    {
        $this->previewNonce = Str::random(10);

        $this->previewTagId = $this->pickDefaultPreviewTagId();
        $this->loadDefaultsIntoForm();
        $this->syncPreviewSession();
    }

    protected function pickDefaultPreviewTagId(): ?int
    {
        if (class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
            return \Plugins\SiatexTags\Models\SiatexTag::query()->latest('id')->value('id');
        }

        return (int) (\Illuminate\Support\Facades\DB::table('siatex_tags')->latest('id')->value('id') ?: 0) ?: null;
    }

    /**
     * Convert array|null to pretty JSON string for textarea
     */
    protected function jsonToTextarea($value): string
    {
        if (blank($value)) {
            return '';
        }

        if (is_array($value)) {
            return (string) (json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) ?: '');
        }

        return (string) $value;
    }

    protected function loadDefaultsIntoForm(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.tag-defaults';

        $this->data = [
            'default_title' => (string) $settings->get('default_title', '', $group),
            'default_description' => (string) $settings->get('default_description', '', $group),
            'default_sub_title' => (string) $settings->get('default_sub_title', '', $group),
            'default_sub_description' => (string) $settings->get('default_sub_description', '', $group),

            'default_seo_title' => (string) $settings->get('default_seo_title', '', $group),
            'default_seo_description' => (string) $settings->get('default_seo_description', '', $group),
            'default_seo_canonical' => (string) $settings->get('default_seo_canonical', '', $group),
            'default_seo_robots' => (string) $settings->get('default_seo_robots', '', $group),
            'default_seo_og_image' => (string) $settings->get('default_seo_og_image', '', $group),

            'default_assets_css' => (string) $settings->get('default_assets_css', '', $group),
            'default_assets_js' => (string) $settings->get('default_assets_js', '', $group),

            'default_custom_json' => $this->jsonToTextarea($settings->get('default_custom_json', null, $group)),
        ];

        $this->form->fill([
            'previewTagId' => $this->previewTagId,
            'data' => $this->data,
        ]);

        $this->bustPreview();
    }

    public function updatedPreviewTagId($value): void
    {
        $id = is_numeric($value) ? (int) $value : null;
        $this->previewTagId = $id;
        $this->bustPreview();
    }

    protected function syncPreviewSession(): void
    {
        $state = $this->form->getState();
        if (!is_array($state)) {
            $state = [];
        }

        $payload = [
            'data' => is_array($state['data'] ?? null) ? $state['data'] : $this->data,
        ];

        session()->put('tag_defaults_preview_state', $payload);
    }

    protected function bustPreview(): void
    {
        $this->syncPreviewSession();
        $this->previewNonce = Str::random(10);
    }

    protected function seoRobotsOptions(): array
    {
        return [
            '' => '— (no default)',
            'index, follow' => 'index, follow',
            'noindex, follow' => 'noindex, follow',
            'index, nofollow' => 'index, nofollow',
            'noindex, nofollow' => 'noindex, nofollow',
            'noarchive' => 'noarchive',
            'nosnippet' => 'nosnippet',
            'noimageindex' => 'noimageindex',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 1,
            ])
            ->schema([
                Tabs::make('TagDefaultsTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                Section::make('Tags Default')
                                    ->description('These defaults are applied when you open Edit Siatex Tag. Only empty fields are auto-filled.')
                                    ->statePath('data')
                                    ->schema([
                                        TextInput::make('default_title')
                                            ->label('H1')
                                            ->maxLength(255)
                                            ->helperText('Used only if Tag title is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        WpClassicEditor::make('default_description')
                                            ->label('Default Content (Product)')
                                            ->height(260)
                                            ->helperText('Used only if Tag content is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        TextInput::make('default_sub_title')
                                            ->label('Default Sub title')
                                            ->maxLength(255)
                                            ->helperText('Used only if Tag Sub title is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        WpClassicEditor::make('default_sub_description')
                                            ->label('Default Sub description')
                                            ->height(260)
                                            ->helperText('Used only if Tag Sub description is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('SEO (Premium)')
                                    ->description('Defaults for tag SEO. Applied only when tag meta_json.seo fields are empty.')
                                    ->statePath('data')
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('default_seo_title')
                                            ->label('Default SEO Title')
                                            ->maxLength(655)
                                            ->helperText('Used only if meta_json.seo.title is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Textarea::make('default_seo_description')
                                            ->label('Default SEO Description')
                                            ->rows(4)
                                            ->helperText('Used only if meta_json.seo.description is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        TextInput::make('default_seo_canonical')
                                            ->label('Default Canonical URL')
                                            ->maxLength(500)
                                            ->helperText('Optional. Used only if meta_json.seo.canonical is empty.')
                                            ->placeholder('https://example.com/tag/slug')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Select::make('default_seo_robots')
                                            ->label('Default Robots')
                                            ->options(fn() => $this->seoRobotsOptions())
                                            ->native(false)
                                            ->helperText('Optional. Used only if meta_json.seo.robots is empty.')
                                            ->live()
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        TextInput::make('default_seo_og_image')
                                            ->label('Default OG Image')
                                            ->maxLength(500)
                                            ->helperText('Optional. Used only if meta_json.seo.og_image is empty.')
                                            ->placeholder('https://example.com/storage/og.jpg')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),
                                    ]),

                                Actions::make([
                                    Action::make('saveBottomContent')
                                        ->label('Save Data')
                                        ->icon('heroicon-o-check')
                                        ->color('primary')
                                        ->action(fn() => $this->save()),
                                ])
                                    ->alignment('right')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Section::make('Custom CSS & JS')
                                    ->description('Optional: these are for preview use unless you also apply them on frontend.')
                                    ->statePath('data')
                                    ->schema([
                                        Textarea::make('default_assets_css')
                                            ->label('Default CSS')
                                            ->rows(10)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Textarea::make('default_assets_js')
                                            ->label('Default JS')
                                            ->rows(10)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Textarea::make('default_custom_json')
                                            ->label('Default JSON (WP-like)')
                                            ->helperText('Valid JSON only. Used if Edit Tag JSON is empty. (Do not include <script> tag)')
                                            ->rows(14)
                                            ->placeholder("{\n  \"company\": {\n    \"name\": \"Jason Ltd\"\n  }\n}")
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),
                                    ]),

                                Actions::make([
                                    Action::make('saveBottomCustom')
                                        ->label('Save Data')
                                        ->icon('heroicon-o-check')
                                        ->color('primary')
                                        ->action(fn() => $this->save()),
                                ])
                                    ->alignment('right')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Frontend Preview')
                            ->schema([
                                Section::make('Preview')
                                    ->schema([
                                        Select::make('previewTagId')
                                            ->label('Preview tag')
                                            ->helperText('Select a tag to preview your defaults on frontend.')
                                            ->native(false)
                                            ->searchable()
                                            ->searchPrompt('Search tag...')
                                            ->noSearchResultsMessage('No tag found for this search.')
                                            ->options(function (): array {
                                                if (!class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
                                                    return [];
                                                }

                                                return \Plugins\SiatexTags\Models\SiatexTag::query()
                                                    ->latest('id')
                                                    ->limit(100)
                                                    ->get(['id', 'slug', 'title'])
                                                    ->mapWithKeys(function ($t) {
                                                        $label = trim((string) ($t->title ?: $t->slug));
                                                        $slug = (string) $t->slug;

                                                        return [
                                                            $t->id => Str::limit($label, 60) . '  (/' . $slug . ')',
                                                        ];
                                                    })
                                                    ->all();
                                            })
                                            ->getSearchResultsUsing(function (string $search): array {
                                                if (!class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
                                                    return [];
                                                }

                                                $q = \Plugins\SiatexTags\Models\SiatexTag::query();
                                                $search = trim($search);

                                                if ($search !== '') {
                                                    $q->where(function ($w) use ($search) {
                                                        $w->where('title', 'like', '%' . $search . '%')
                                                            ->orWhere('slug', 'like', '%' . $search . '%');
                                                    });
                                                }

                                                return $q->latest('id')
                                                    ->limit(100)
                                                    ->get(['id', 'slug', 'title'])
                                                    ->mapWithKeys(function ($t) {
                                                        $label = trim((string) ($t->title ?: $t->slug));
                                                        $slug = (string) $t->slug;

                                                        return [
                                                            $t->id => Str::limit($label, 60) . '  (/' . $slug . ')',
                                                        ];
                                                    })
                                                    ->all();
                                            })
                                            ->getOptionLabelUsing(function ($value): ?string {
                                                $id = is_numeric($value) ? (int) $value : null;
                                                if (!$id || !class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
                                                    return null;
                                                }

                                                $t = \Plugins\SiatexTags\Models\SiatexTag::query()->find($id);
                                                if (!$t) {
                                                    return null;
                                                }

                                                $label = trim((string) ($t->title ?: $t->slug));

                                                return Str::limit($label, 80) . '  (/' . (string) $t->slug . ')';
                                            })
                                            ->live()
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Placeholder::make('frontend_preview')
                                            ->label('')
                                            ->content(function (Get $get): HtmlString {
                                                $tagId = $get('previewTagId');
                                                $tagId = is_numeric($tagId) ? (int) $tagId : null;

                                                if (!$tagId || !class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
                                                    return new HtmlString('<div class="text-sm text-slate-500">Select a tag to preview.</div>');
                                                }

                                                $tag = \Plugins\SiatexTags\Models\SiatexTag::query()->find($tagId);
                                                if (!$tag || !filled($tag->slug)) {
                                                    return new HtmlString('<div class="text-sm text-slate-500">Preview tag not found or missing slug.</div>');
                                                }

                                                $url = url('/' . ltrim((string) $tag->slug, '/'))
                                                    . '?td_preview=1&_ts=' . urlencode($this->previewNonce);

                                                return new HtmlString(
                                                    '<iframe src="' . e($url) . '" class="w-full rounded-xl border" style="height: 70vh;"></iframe>'
                                                );
                                            })
                                            ->dehydrated(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshPreview')
                ->label('Refresh preview')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->bustPreview()),

            Action::make('save')
                ->label('Save Data')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(fn() => $this->save()),
        ];
    }

    public function save(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.tag-defaults';

        $state = $this->form->getState();

        if (is_array($state) && isset($state['data']) && is_array($state['data'])) {
            $this->data = $state['data'];
        }

        $jsonString = trim((string) ($this->data['default_custom_json'] ?? ''));

        $jsonArray = null;
        if ($jsonString !== '') {
            $decoded = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Notification::make()
                    ->title('Invalid JSON')
                    ->body('Default JSON (WP-like) must be valid JSON.')
                    ->danger()
                    ->send();
                return;
            }

            $jsonArray = $decoded;
        }

        $settings->set('default_title', (string) ($this->data['default_title'] ?? ''), $group);
        $settings->set('default_description', (string) ($this->data['default_description'] ?? ''), $group);
        $settings->set('default_sub_title', (string) ($this->data['default_sub_title'] ?? ''), $group);
        $settings->set('default_sub_description', (string) ($this->data['default_sub_description'] ?? ''), $group);

        $settings->set('default_seo_title', (string) ($this->data['default_seo_title'] ?? ''), $group);
        $settings->set('default_seo_description', (string) ($this->data['default_seo_description'] ?? ''), $group);
        $settings->set('default_seo_canonical', (string) ($this->data['default_seo_canonical'] ?? ''), $group);
        $settings->set('default_seo_robots', (string) ($this->data['default_seo_robots'] ?? ''), $group);
        $settings->set('default_seo_og_image', (string) ($this->data['default_seo_og_image'] ?? ''), $group);

        $settings->set('default_assets_css', (string) ($this->data['default_assets_css'] ?? ''), $group);
        $settings->set('default_assets_js', (string) ($this->data['default_assets_js'] ?? ''), $group);

        $settings->set('default_custom_json', $jsonArray, $group);

        $this->syncPreviewSession();
        $this->bustPreview();

        Notification::make()->title('Saved')->success()->send();
    }
}