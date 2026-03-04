<?php

namespace Plugins\MultiPage\Filament\Schemas;

use App\Cms\Content\PermalinkManager;
use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Forms\Components\WpClassicEditor;
use App\Models\Post;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Plugins\MultiPage\Support\MultiPageStorage;

class MultiPageForm
{
    /**
     * Remove dynamic segment tokens from a title before generating a base slug.
     *
     * Goal: keep base slug static and clean:
     * - Remove {segment-N} / {{segment-N}}
     * - Also remove connector words ONLY when they are directly attached to a segment token
     *   like "for {segment-1}", "in ({segment-2})", "near {{segment-3}}", etc.
     */
    private static function stripSegmentTokens(string $title): string
    {
        $t = (string) $title;

        // Remove connector words when they appear right before a segment token (safe + SEO-friendly)
        // Examples removed:
        //   "for {segment-1}", "in ({segment-2})", "near {{segment-1}}", "around {segment-3}"
        $t = preg_replace(
            '/\b(for|in|at|near|around|within|from|to|across|throughout|inside|outside|by|with|without)\b\s*\(?\s*\{\{?\s*segment-\d+\s*\}?\}\s*\)?/i',
            '',
            $t
        ) ?? $t;

        // Remove remaining {segment-N} or {{segment-N}} anywhere
        $t = preg_replace('/\{\{?\s*segment-\d+\s*\}?\}/i', '', $t) ?? $t;

        // Remove empty parentheses/brackets left behind
        $t = preg_replace('/\(\s*\)/', '', $t) ?? $t;
        $t = preg_replace('/\[\s*\]/', '', $t) ?? $t;

        // If connector word became trailing last word (rare), remove it
        $t = preg_replace('/\b(for|in|at|near|around|within|from|to|across|throughout|inside|outside|by|with|without)\b\s*$/i', '', $t) ?? $t;

        // Cleanup punctuation / multiple spaces
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return trim($t);
    }

    public static function configure(Schema $schema): Schema
    {
        $publishSchema = [
            Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'scheduled' => 'Scheduled',
                ])
                ->default('published')
                ->required(),

            Select::make('meta_json.template')
                ->label('Template')
                ->helperText('If selected, frontend will use that template file. If empty, theme default view is used.')
                ->options(function (): array {
                    $base = [
                        '' => 'Theme Default',
                        'default' => 'Slider Template',
                    ];
                    if (function_exists('apply_filters')) {
                        $base = (array) apply_filters('cms.page_template_options', $base);
                    }
                    return $base;
                })
                ->default('')
                ->native(false)
                ->dehydrateStateUsing(fn($state) => is_string($state) ? $state : ''),

            MediaPicker::make('featured_media_ids')
                ->label('Featured Images')
                ->modalHeading('Featured images')
                ->multiple()
                ->maxItems(20),

            MediaPicker::make('product_media_ids')
                ->label('Product Images')
                ->modalHeading('Product images')
                ->multiple()
                ->maxItems(50),

            Section::make('Multipage Settings')
                ->schema([
                    Toggle::make('meta_json.multipage.enabled')
                        ->label('Enable Multiple Page')
                        ->default(true),

                    Select::make('meta_json.multipage.csv_file')
                        ->label('Data File')
                        ->options(function (): array {
                            MultiPageStorage::ensureDirs();

                            $disk = Storage::disk('local');
                            $files = $disk->files(MultiPageStorage::CSVS);

                            $out = [];
                            foreach ($files as $f) {
                                $name = basename($f);
                                $out[$name] = $name;
                            }
                            ksort($out);

                            return $out;
                        })
                        ->searchable()
                        ->helperText('Upload CSV from Multi Pages > Settings Multipages > Data tab (storage/app/private/...)')
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    TextInput::make('meta_json.multipage.url_structure')
                        ->label('Url Structure - Use {col1}, {col2}...')
                        ->placeholder('t-shirts-importers-in/{col1}')
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    TextInput::make('meta_json.multipage.default_segments')
                        ->label('Default Values for segments comma(,) separated')
                        ->placeholder('Bangladesh, Dhaka')
                        ->disabled(fn(Get $get) => !(bool) $get('meta_json.multipage.enabled')),

                    ViewField::make('multipage_buttons')
                        ->view('multi-page::filament.components.multipage-buttons')
                        ->viewData(function ($livewire): array {
                            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                            $id = $record?->getKey();

                            $links = [];
                            if ($id && method_exists($livewire, 'getGeneratedLinksForModal')) {
                                $links = (array) $livewire->getGeneratedLinksForModal();
                            }

                            return [
                                'recordId' => $id,
                                'links' => $links,
                            ];
                        })
                        ->dehydrated(false)
                        ->visible(fn(Get $get) => (bool) $get('meta_json.multipage.enabled')),
                ])
                ->collapsible()
                ->collapsed(false),
        ];

        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Tabs::make('Editor')
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                TextInput::make('title')
                                    ->label('H1')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $slugManual = (bool) ($get('meta_json._slug_manual') ?? false);

                                        if (!$slugManual) {
                                            // ✅ Keep base slug STATIC and clean
                                            $clean = self::stripSegmentTokens((string) $state);

                                            if ($clean !== '') {
                                                $set('slug', Str::slug($clean));
                                            }
                                        }

                                        $seoManual = (bool) ($get('meta_json._seo_title_manual') ?? false);
                                        if (!$seoManual) {
                                            $set('meta_json.seo.title', (string) $state);
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->helperText('Leave blank to auto-generate. Must be globally unique (posts + pages).')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Set $set): void {
                                        $value = filled($state) ? Str::slug((string) $state) : null;
                                        $set('slug', $value);

                                        // ✅ user touched slug -> stop auto updates from title
                                        $set('meta_json._slug_manual', true);
                                    })
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    ->rule(function (?Post $record) {
                                        return Rule::unique('posts', 'slug')->ignore($record?->id);
                                    }),

                                Placeholder::make('permalink_preview')
                                    ->label('Permalink')
                                    ->content(function (?Post $record, PermalinkManager $permalinks) {
                                        return $record ? $permalinks->pageUrl($record) : 'Will be generated after saving.';
                                    }),

                                WpClassicEditor::make('content_json')
                                    ->label('Content')
                                    ->height(320)
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            return (string) ($state['html'] ?? '');
                                        }
                                        return is_string($state) ? $state : '';
                                    })
                                    ->dehydrateStateUsing(function ($state, Get $get): array {
                                        $current = $get('content_json');
                                        if (!is_array($current)) {
                                            $current = [];
                                        }
                                        $current['html'] = is_string($state) ? $state : '';
                                        return $current;
                                    }),

                                WpClassicEditor::make('meta_json.after_banner')
                                    ->label('After Banner')
                                    ->height(180)
                                    ->columnSpanFull()
                                    ->helperText('Put shortcode here. It will render below the hero section on frontend.')
                                    ->formatStateUsing(
                                        fn($state): string => is_string($state)
                                        ? $state
                                        : (is_array($state) ? (string) ($state['html'] ?? '') : '')
                                    )
                                    ->dehydrateStateUsing(fn($state) => is_string($state) ? $state : ''),

                                TextInput::make('meta_json.subtitle')
                                    ->label('Sub Title')
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                WpClassicEditor::make('meta_json.sub_description')
                                    ->label('Sub Description')
                                    ->height(180)
                                    ->columnSpanFull()
                                    ->formatStateUsing(
                                        fn($state): string => is_string($state)
                                        ? $state
                                        : (is_array($state) ? (string) ($state['html'] ?? '') : '')
                                    )
                                    ->dehydrateStateUsing(fn($state) => is_string($state) ? $state : ''),

                                Section::make('SEO (Premium)')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('meta_json.seo.title')
                                            ->label('SEO Title')
                                            ->maxLength(1000)
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Set $set): void {
                                                $set('meta_json._seo_title_manual', true);
                                            }),

                                        Textarea::make('meta_json.seo.description')
                                            ->label('Meta Description')
                                            ->rows(3)
                                            ->maxLength(1000),

                                        TextInput::make('meta_json.seo.canonical')
                                            ->label('Canonical URL (optional)')
                                            ->maxLength(255),

                                        Select::make('meta_json.seo.robots')
                                            ->label('Robots')
                                            ->options([
                                                '' => 'Default (index, follow)',
                                                'index, follow' => 'index, follow',
                                                'noindex, follow' => 'noindex, follow',
                                                'index, nofollow' => 'index, nofollow',
                                                'noindex, nofollow' => 'noindex, nofollow',
                                            ])
                                            ->default(''),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Textarea::make('meta_json.assets.css')->label('Custom CSS')->rows(14),
                                Textarea::make('meta_json.assets.js')->label('Custom JS')->rows(14),
                                Textarea::make('meta_json.custom_json')->label('Custom JSON')->rows(18)->nullable()->rules(['json']),
                            ]),
                    ]),

                Section::make('Publish')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema($publishSchema),
            ]);
    }
}