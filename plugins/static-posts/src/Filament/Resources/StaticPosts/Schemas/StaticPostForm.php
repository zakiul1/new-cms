<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticPosts\Schemas;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Forms\Components\WpClassicEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaticPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
           ->columns(['default' => 1, 'xl' => 12])

            ->components([

                // LEFT: Tabs (2/3)
              Tabs::make('Editor')
    ->columnSpan(['default' => 1, 'xl' => 9])

                    ->tabs([

                        // ✅ Content tab
                        Tab::make('Content')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!filled($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('slug', filled($state) ? Str::slug((string) $state) : null);
                                    })
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    ->rule(function ($record) {
                                        return Rule::unique('posts', 'slug')->ignore($record?->id);
                                    })
                                    ->helperText('Leave blank to auto-generate. Must be globally unique (posts + pages).'),

                                // Optional permalink preview (like Posts)
                                Placeholder::make('permalink_preview')
                                    ->label('Permalink')
                                    ->content(function ($record, Get $get) {
                                        $base = rtrim((string) config('app.url'), '/');
                                        $slug = trim((string) $get('slug'), '/');
                                        if ($slug === '') {
                                            $slug = Str::slug((string) ($get('title') ?? ''));
                                        }
                                        $slug = $slug !== '' ? $slug : '(auto)';
                                        return "{$base}/static/{$slug}";
                                    }),

                                // Content editor
                                WpClassicEditor::make('content_json')
                                    ->label('Content')
                                    ->height(320)
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            $html = $state['html'] ?? '';
                                            return is_string($html) ? $html : '';
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

                                /**
                                 * ✅ NEW FIELD (under Content as you requested)
                                 * Used by shortcode when [sp lmbtn] is present.
                                 */
                                TextInput::make('meta_json.static_posts.learn_more_url')
                                    ->label('Learnmore button link')
                                    ->helperText('Optional. If empty, frontend will use "#" for Learn more button.')
                                    ->maxLength(2000)
                                    ->nullable()
                                    ->columnSpanFull(),

                                Textarea::make('excerpt')
                                    ->label('Excerpt')
                                    ->rows(4)
                                    ->maxLength(2000)
                                    ->nullable(),
                            ]),

                        // ✅ Custom CSS & JS + JSON tab
                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Textarea::make('meta_json.assets.css')
                                    ->label('Custom CSS (Paste Row CSS Without <style> tags)')
                                    ->helperText('Applies to this post only. Output inside <head>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.assets.js')
                                    ->label('Custom JS (Paste Script Without <script> tags)')
                                    ->helperText('Applies to this post only. Output before </body>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.custom_json')
                                    ->label('Custom JSON (Paste Valid JSON)')
                                    ->helperText('Valid JSON only. Saved per post. (Do not include <script> tag)')
                                    ->rows(18)
                                    ->nullable()
                                    ->rules(['json'])
                                    ->formatStateUsing(function ($state) {
                                        if (blank($state)) {
                                            return '';
                                        }
                                        if (is_array($state)) {
                                            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                                        }
                                        return (string) $state;
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        if (blank($state)) {
                                            return null;
                                        }
                                        $decoded = json_decode((string) $state, true);
                                        return $decoded ?? (string) $state;
                                    }),
                            ]),

                        // ✅ Frontend Preview tab
                        Tab::make('Frontend Preview')
                            ->schema([
                                Placeholder::make('frontend_preview')
                                    ->label('')
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="text-sm text-gray-600">Save the post first to preview the real frontend page.</div>'
                                            );
                                        }
                                        $url = url('/static/' . $record->slug);
                                        return new \Illuminate\Support\HtmlString(
                                            '<a class="text-primary-600 underline" target="_blank" href="' . e($url) . '">' . e($url) . '</a>'
                                        );
                                    }),
                            ]),
                    ]),

                // RIGHT: Publish panel (1/3)
              Section::make('Publish')
    ->columnSpan(['default' => 1, 'xl' => 3])

                    ->schema([

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
                            ->helperText('If selected, frontend will use that template. If empty, theme default view is used.')
                            ->options([
                                '' => 'Theme Default (static-posts/show.blade.php)',
                                'default' => 'Default',
                            ])
                            ->default('')
                            ->native(false)
                            ->dehydrateStateUsing(fn($state) => is_string($state) ? $state : ''),

                        Select::make('static_category_term_ids')
                            ->label('Categories')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(100)
                            ->options(function () {
                                $taxonomy = \App\Models\Taxonomy::query()
                                    ->where('key', 'static_category')
                                    ->first();

                                if (!$taxonomy) {
                                    return [];
                                }

                                return \App\Models\Term::query()
                                    ->where('taxonomy_id', $taxonomy->id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->afterStateHydrated(function (Select $component, $record) {
                                if (!$record) {
                                    return;
                                }

                                $termIds = $record->terms()
                                    ->whereHas('taxonomy', fn($q) => $q->where('key', 'static_category'))
                                    ->pluck('terms.id')
                                    ->all();

                                $component->state($termIds);
                            })
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function ($record, $state) {
                                if (!$record) {
                                    return;
                                }

                                $taxonomy = \App\Models\Taxonomy::query()
                                    ->where('key', 'static_category')
                                    ->first();

                                if (!$taxonomy) {
                                    return;
                                }

                                $ids = collect($state ?? [])
                                    ->filter(fn($v) => is_numeric($v))
                                    ->map(fn($v) => (int) $v)
                                    ->unique()
                                    ->values()
                                    ->all();

                                $current = $record->terms()->pluck('terms.id')->all();

                                $staticTermIds = \App\Models\Term::query()
                                    ->where('taxonomy_id', $taxonomy->id)
                                    ->pluck('id')
                                    ->all();

                                $keep = array_values(array_diff($current, $staticTermIds));
                                $final = array_values(array_unique(array_merge($keep, $ids)));

                                $record->terms()->sync($final);
                            }),

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

                        Textarea::make('meta_json.static_posts.svg_icon')
                            ->label('SVG Icon (code)')
                            ->helperText('Paste raw SVG code. Saved in meta_json.static_posts.svg_icon for frontend.')
                            ->rows(6)
                            ->extraAttributes([
                                'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                            ])
                            ->nullable(),
                    ]),
            ]);
    }
}