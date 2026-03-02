<?php

namespace Plugins\SiatexTags\Filament\Resources\SiatexTagResource\Pages;

use App\Filament\Forms\Components\WpClassicEditor;
use App\Models\CmsSetting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Plugins\SiatexTags\Filament\Resources\SiatexTagResource;
use Plugins\SiatexTags\Models\SiatexTag;
use Plugins\SiatexTags\Support\MediaCategoryOptions;

class EditSiatexTag extends EditRecord
{
    protected static string $resource = SiatexTagResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * ✅ IMPORTANT FIX:
     * If SEO title is empty in the form, Filament sometimes doesn't overwrite the existing JSON value.
     * This method ensures empty SEO title truly clears meta_json.seo.title so Tag Defaults can apply.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $meta = $data['meta_json'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $seo = $meta['seo'] ?? [];
        if (!is_array($seo)) {
            $seo = [];
        }

        // Normalize SEO title (trim + NBSP cleanup)
        $seoTitle = (string) ($seo['title'] ?? '');
        $seoTitle = html_entity_decode($seoTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $seoTitle = str_replace("\xc2\xa0", ' ', $seoTitle); // NBSP
        $seoTitle = trim($seoTitle);

        // If empty -> remove key so defaults can apply
        if ($seoTitle === '') {
            unset($seo['title']);
        } else {
            $seo['title'] = $seoTitle;
        }

        $meta['seo'] = $seo;
        $data['meta_json'] = $meta;

        return $data;
    }

    private function getSlugTemplate(): string
    {
        $value = (string) CmsSetting::query()
            ->where('key', 'siatex_tags.slug_template')
            ->value('value');

        return trim($value);
    }

    private function makeSlugFromTemplate(string $title): string
    {
        $template = $this->getSlugTemplate();

        $base = $template !== ''
            ? str_ireplace(['[tag]', '[tags]'], $title, $template)
            : $title;

        return Str::slug($base);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                Tabs::make('Editor')
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Tag Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // ✅ Use slug template if slug is empty
                                        if (!filled($get('slug'))) {
                                            $set('slug', $this->makeSlugFromTemplate((string) $state));
                                        }

                                        // ❌ Removed: auto-filling meta_json.seo.title from H1
                                        // This was blocking Tag Defaults "Default SEO Title"
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn($state, Set $set) => $set('slug', Str::slug((string) $state)))
                                    ->dehydrateStateUsing(fn($state) => Str::slug((string) $state))
                                    ->rule(function (?SiatexTag $record) {
                                        return Rule::unique('siatex_tags', 'slug')->ignore($record?->id);
                                    }),

                                Placeholder::make('permalink_preview')
                                    ->label('Permalink')
                                    ->content(function (?SiatexTag $record, Get $get) {
                                        $slug = $record?->slug ?: (string) $get('slug');
                                        $slug = trim($slug) !== '' ? $slug : '(save to preview)';
                                        // ✅ no /tag/ prefix
                                        return url('/' . ltrim($slug, '/'));
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

                                TextInput::make('meta_json.subtitle')
                                    ->label('Sub title')
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                WpClassicEditor::make('meta_json.sub_description')
                                    ->label('Sub description')
                                    ->height(180)
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            return (string) ($state['html'] ?? '');
                                        }
                                        return is_string($state) ? $state : '';
                                    })
                                    ->dehydrateStateUsing(fn($state) => is_string($state) ? $state : ''),

                                Section::make('SEO (Premium)')
                                    ->description('Control how this tag page appears in Google and when shared on social media.')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('meta_json.seo.title')
                                            ->label('SEO Title')
                                            ->maxLength(1000)
                                            ->live(onBlur: true),

                                        Textarea::make('meta_json.seo.description')
                                            ->label('Meta Description')
                                            ->rows(3)
                                            ->maxLength(1000)
                                            ->live(onBlur: true),

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

                                        TextInput::make('meta_json.seo.og_image')
                                            ->label('OpenGraph Image (optional)')
                                            ->maxLength(255),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Textarea::make('meta_json.assets.css')
                                    ->label('Custom CSS (without <style>)')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.assets.js')
                                    ->label('Custom JS (without <script>)')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.custom_json')
                                    ->label('Custom JSON (valid JSON)')
                                    ->rows(16)
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
                                        $state = trim((string) $state);
                                        if ($state === '') {
                                            return null;
                                        }
                                        $decoded = json_decode($state, true);
                                        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                                    })
                                    ->live(onBlur: true),
                            ]),

                        Tab::make('Frontend Preview')
                            ->schema([
                                Placeholder::make('frontend_preview')
                                    ->label('')
                                    ->content(function (?SiatexTag $record): \Illuminate\Support\HtmlString {
                                        if (!$record) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="text-sm text-gray-600">Save first to preview.</div>'
                                            );
                                        }

                                        // ✅ no /tag/ prefix
                                        $url = url('/' . ltrim((string) $record->slug, '/'));

                                        return new \Illuminate\Support\HtmlString(
                                            '<iframe src="' . e($url) . '" class="w-full rounded-xl border" style="height: 70vh;"></iframe>'
                                        );
                                    })
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Media')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Select::make('media_category_term_id')
                            ->label('Media category')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn(): array => MediaCategoryOptions::options()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var SiatexTag $record */
        $record = $this->record;

        return [
            DeleteAction::make(),

            Action::make('visit')
                ->label('Visit')
                ->icon('heroicon-o-arrow-top-right-on-square')
                // ✅ no /tag/ prefix
                ->url(fn() => url('/' . ltrim((string) $record->slug, '/')))
                ->openUrlInNewTab(),

            // ✅ overwrite existing slug using template
            Action::make('regenerateSlug')
                ->label('Regenerate Slug')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () use ($record) {
                    $newSlug = $this->makeSlugFromTemplate((string) $record->title);

                    if ($newSlug === '') {
                        Notification::make()->title('Cannot generate slug')->danger()->send();
                        return;
                    }

                    // avoid duplicates
                    $exists = SiatexTag::query()
                        ->where('slug', $newSlug)
                        ->where('id', '!=', $record->id)
                        ->exists();

                    if ($exists) {
                        Notification::make()->title('Slug already exists')->danger()->send();
                        return;
                    }

                    $record->slug = $newSlug;
                    $record->save();

                    Notification::make()->title('Slug regenerated')->success()->send();
                }),

            Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => static::getResource()::getUrl()),

            $this->getSaveFormAction()
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save changes')
                ->color('primary')
                ->formId('form'),
        ];
    }
}