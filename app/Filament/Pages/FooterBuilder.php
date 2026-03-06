<?php

namespace App\Filament\Pages;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\SettingsRepository;
use App\Filament\Forms\Components\WpClassicEditor;
use App\Models\Menu;
use App\Models\Widget;
use App\Models\WidgetPlacement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Throwable;

class FooterBuilder extends Page
{
    protected static ?string $navigationLabel = 'Footer Builder';
    protected static \UnitEnum|string|null $navigationGroup = 'Appearance';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected string $view = 'filament.pages.footer-builder';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(SettingsRepository $settings): void
    {
        $cols = (int) $settings->get('core', 'footer_columns', 3);
        $cols = max(3, min(5, $cols));

        $layout = $settings->get('core', 'footer_builder_layout', null);
        $bottomFooterContent = (string) $settings->get('core', 'footer_bottom_content', '');

        $decoded = null;
        if (is_string($layout) && trim($layout) !== '') {
            $decoded = json_decode($layout, true);
        }

        $columns = is_array($decoded) ? $decoded : $this->buildFromDb($cols);

        $columns = array_values(is_array($columns) ? $columns : []);
        while (count($columns) < $cols) {
            $columns[] = ['blocks' => []];
        }
        if (count($columns) > $cols) {
            $columns = array_slice($columns, 0, $cols);
        }

        $this->form->fill([
            'footer_columns' => $cols,
            'columns' => $columns,
            'bottom_footer_content' => $bottomFooterContent,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveFooter')
                ->label('Save Footer')
                ->icon('heroicon-o-check')
                ->color('warning')
                ->keyBindings(['mod+s'])
                ->action(function (SettingsRepository $settings, CmsCacheVersions $versions) {
                    $this->save($settings, $versions);
                }),

            Action::make('clearFooterCache')
                ->label('Clear Footer Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (CmsCacheVersions $versions, SettingsRepository $settings) {
                    $cols = (int) $settings->get('core', 'footer_columns', 3);
                    $cols = max(3, min(5, $cols));

                    for ($i = 1; $i <= $cols; $i++) {
                        $versions->bump('widget_area', "footer-{$i}");
                    }

                    $versions->bumpRender();

                    Notification::make()
                        ->success()
                        ->title('Footer cache cleared')
                        ->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Footer Layout')
                    ->description('Create a clean footer using columns and content blocks.')
                    ->schema([
                        Select::make('footer_columns')
                            ->label('Footer Columns')
                            ->options([
                                3 => '3 Columns',
                                4 => '4 Columns',
                                5 => '5 Columns',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Choose how many columns you want to show in the footer.')
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $cols = max(3, min(5, (int) $state));

                                $columns = $get('columns') ?? [];
                                if (!is_array($columns)) {
                                    $columns = [];
                                }

                                $columns = array_values($columns);

                                while (count($columns) < $cols) {
                                    $columns[] = ['blocks' => []];
                                }

                                if (count($columns) > $cols) {
                                    $columns = array_slice($columns, 0, $cols);
                                }

                                $set('columns', $columns);
                            }),

                        Repeater::make('columns')
                            ->label('Footer Columns')
                            ->minItems(3)
                            ->maxItems(5)
                            ->defaultItems(3)
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed()
                            ->addActionLabel('Add Column')
                            ->itemLabel(function (array $state, Get $get, $component): string {
                                static $i = 0;
                                $i++;
                                return 'Column ' . $i;
                            })
                            ->schema([
                                Repeater::make('blocks')
                                    ->label('Blocks')
                                    ->default([])
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable()
                                    ->addActionLabel('Add Block')
                                    ->itemLabel(function (array $state): string {
                                        $type = (string) ($state['type'] ?? 'block');
                                        $title = trim((string) ($state['title'] ?? ''));

                                        $typeLabel = match ($type) {
                                            'text' => 'Text',
                                            'menu' => 'Menu',
                                            'categories' => 'Categories',
                                            'shortcode' => 'Shortcode',
                                            default => 'Block',
                                        };

                                        return $title !== ''
                                            ? "{$typeLabel}: {$title}"
                                            : $typeLabel;
                                    })
                                    ->schema([
                                        Section::make()
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Block Type')
                                                    ->options([
                                                        'text' => 'Text / HTML',
                                                        'menu' => 'Menu',
                                                        'categories' => 'Categories',
                                                        'shortcode' => 'Shortcode',
                                                    ])
                                                    ->required()
                                                    ->native(false)
                                                    ->live()
                                                    ->default('text')
                                                    ->helperText('Choose what kind of content this block will display.'),

                                                TextInput::make('title')
                                                    ->label('Block Title')
                                                    ->maxLength(120)
                                                    ->placeholder('Example: Quick Links, Contact, Categories')
                                                    ->helperText('Optional heading shown above the block.'),

                                                Toggle::make('enabled')
                                                    ->label('Enabled')
                                                    ->default(true)
                                                    ->inline(false),
                                            ])
                                            ->columns(3),

                                        Section::make('Text Content')
                                            ->description('Use this editor for footer text, HTML, and shortcode content.')
                                            ->visible(fn(Get $get) => $get('type') === 'text')
                                            ->schema([
                                                WpClassicEditor::make('settings.content')
                                                    ->label('Content')
                                                    ->height(300)
                                                    ->columnSpanFull(),
                                            ]),

                                        Section::make('Menu Settings')
                                            ->description('Show one existing menu inside this footer block.')
                                            ->visible(fn(Get $get) => $get('type') === 'menu')
                                            ->schema([
                                                Select::make('settings.menu_id')
                                                    ->label('Menu')
                                                    ->options(fn() => Menu::query()->orderBy('name')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    ->native(false)
                                                    ->required(fn(Get $get) => $get('type') === 'menu')
                                                    ->placeholder('Select a menu'),
                                            ]),

                                        Section::make('Categories Settings')
                                            ->description('Display a list of categories in the footer.')
                                            ->visible(fn(Get $get) => $get('type') === 'categories')
                                            ->schema([
                                                TextInput::make('settings.limit')
                                                    ->label('Max Categories')
                                                    ->numeric()
                                                    ->default(10)
                                                    ->minValue(1)
                                                    ->maxValue(100)
                                                    ->placeholder('10'),

                                                Toggle::make('settings.show_count')
                                                    ->label('Show Post Count')
                                                    ->default(false)
                                                    ->inline(false),
                                            ])
                                            ->columns(2),

                                        Section::make('Shortcode Settings')
                                            ->description('Use a shortcode that will render on the frontend.')
                                            ->visible(fn(Get $get) => $get('type') === 'shortcode')
                                            ->schema([
                                                Textarea::make('settings.code')
                                                    ->label('Shortcode')
                                                    ->rows(4)
                                                    ->placeholder('[your_shortcode]')
                                                    ->required(fn(Get $get) => $get('type') === 'shortcode'),
                                            ]),

                                        TextInput::make('widget_id')
                                            ->dehydrated()
                                            ->visible(false),
                                    ]),
                            ]),

                        Section::make('Bottom Footer')
                            ->description('This content appears below the footer columns. Shortcodes and HTML can be used here.')
                            ->schema([
                                WpClassicEditor::make('bottom_footer_content')
                                    ->label('Bottom Footer Content')
                                    ->height(260)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(SettingsRepository $settings, CmsCacheVersions $versions): void
    {
        $state = $this->form->getState();

        $cols = max(3, min(5, (int) ($state['footer_columns'] ?? 3)));
        $columns = $state['columns'] ?? [];
        $bottomFooterContent = (string) ($state['bottom_footer_content'] ?? '');

        if (!is_array($columns)) {
            $columns = [];
        }

        $columns = array_values($columns);

        while (count($columns) < $cols) {
            $columns[] = ['blocks' => []];
        }

        if (count($columns) > $cols) {
            $columns = array_slice($columns, 0, $cols);
        }

        try {
            DB::transaction(function () use ($settings, $versions, $cols, &$columns, $bottomFooterContent) {
                $settings->set('core', 'footer_columns', $cols);
                $settings->set('core', 'footer_bottom_content', $bottomFooterContent);

                for ($i = 1; $i <= $cols; $i++) {
                    $areaKey = "footer-{$i}";
                    $blocks = $columns[$i - 1]['blocks'] ?? [];

                    if (!is_array($blocks)) {
                        $blocks = [];
                    }

                    WidgetPlacement::query()
                        ->where('widget_area_key', $areaKey)
                        ->delete();

                    $sort = 1;

                    foreach ($blocks as $bi => $block) {
                        if (!is_array($block)) {
                            continue;
                        }

                        $type = (string) ($block['type'] ?? '');
                        if (!in_array($type, ['text', 'menu', 'categories', 'shortcode'], true)) {
                            continue;
                        }

                        $title = isset($block['title']) ? trim((string) $block['title']) : '';
                        $enabled = (bool) ($block['enabled'] ?? true);

                        $settingsArr = $block['settings'] ?? [];
                        if (!is_array($settingsArr)) {
                            $settingsArr = [];
                        }

                        $widgetId = isset($block['widget_id']) && is_numeric($block['widget_id'])
                            ? (int) $block['widget_id']
                            : 0;

                        if ($widgetId > 0) {
                            $widget = Widget::query()->find($widgetId);
                            if (!$widget) {
                                $widgetId = 0;
                            }
                        }

                        if ($widgetId <= 0) {
                            $widget = Widget::query()->create([
                                'type' => $type,
                                'title' => $title !== '' ? $title : null,
                                'settings' => $settingsArr ?: null,
                                'is_enabled' => $enabled,
                            ]);

                            $widgetId = (int) $widget->id;
                            $columns[$i - 1]['blocks'][$bi]['widget_id'] = $widgetId;
                        } else {
                            Widget::query()
                                ->whereKey($widgetId)
                                ->update([
                                    'type' => $type,
                                    'title' => $title !== '' ? $title : null,
                                    'settings' => $settingsArr ?: null,
                                    'is_enabled' => $enabled,
                                ]);
                        }

                        WidgetPlacement::query()->create([
                            'widget_area_key' => $areaKey,
                            'widget_id' => $widgetId,
                            'sort_order' => $sort++,
                            'overrides' => null,
                            'visibility' => null,
                        ]);
                    }

                    $versions->bump('widget_area', $areaKey);
                }

                $settings->set('core', 'footer_builder_layout', json_encode($columns));
                $versions->bumpRender();
            });

            $this->form->fill([
                'footer_columns' => $cols,
                'columns' => $columns,
                'bottom_footer_content' => $bottomFooterContent,
            ]);

            Notification::make()
                ->success()
                ->title('Footer saved')
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Save failed')
                ->body('Check logs for details.')
                ->send();
        }
    }

    private function buildFromDb(int $cols): array
    {
        $cols = max(3, min(5, $cols));
        $out = [];

        for ($i = 1; $i <= $cols; $i++) {
            $areaKey = "footer-{$i}";

            $rows = WidgetPlacement::query()
                ->with('widget')
                ->where('widget_area_key', $areaKey)
                ->orderBy('sort_order')
                ->get();

            $blocks = [];

            foreach ($rows as $p) {
                if (!$p->widget) {
                    continue;
                }

                $w = $p->widget;

                $blocks[] = [
                    'widget_id' => (int) $w->id,
                    'type' => (string) $w->type,
                    'title' => (string) ($w->title ?? ''),
                    'enabled' => (bool) $w->is_enabled,
                    'settings' => is_array($w->settings) ? $w->settings : [],
                ];
            }

            $out[] = ['blocks' => $blocks];
        }

        return $out;
    }
}