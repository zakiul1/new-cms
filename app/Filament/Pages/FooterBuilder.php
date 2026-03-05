<?php

namespace App\Filament\Pages;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\SettingsRepository;
use App\Models\Widget;
use App\Models\WidgetPlacement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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

        $decoded = null;
        if (is_string($layout) && trim($layout) !== '') {
            $decoded = json_decode($layout, true);
        }

        // If no saved layout, build from current DB placements (footer-1..footer-N)
        $columns = is_array($decoded) ? $decoded : $this->buildFromDb($cols);

        // Ensure exactly $cols columns
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
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveFooter')
                ->label('Save Footer')
                ->icon('heroicon-o-check')
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

                    Notification::make()->success()->title('Footer cache cleared')->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Footer Layout')
                    ->description('Build footer columns and blocks (widgets).')
                    ->schema([
                        Select::make('footer_columns')
                            ->label('Columns')
                            ->options([
                                3 => '3 Columns',
                                4 => '4 Columns',
                                5 => '5 Columns',
                            ])
                            ->required()
                            ->live() // v5 uses live() instead of reactive()
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
                            ->label('Columns')
                            ->minItems(3)
                            ->maxItems(5)
                            ->defaultItems(3)
                            ->schema([
                                Repeater::make('blocks')
                                    ->label('Blocks')
                                    ->reorderable()
                                    ->schema([
                                        Select::make('type')
                                            ->label('Block type')
                                            ->options([
                                                'text' => 'Text / HTML',
                                                'menu' => 'Menu',
                                                'categories' => 'Categories',
                                                'shortcode' => 'Shortcode',
                                            ])
                                            ->required()
                                            ->live(),

                                        TextInput::make('title')
                                            ->label('Title (optional)')
                                            ->maxLength(120),

                                        Toggle::make('enabled')
                                            ->label('Enabled')
                                            ->default(true),

                                        // TEXT block settings
                                        Section::make('Text')
                                            ->collapsed(fn(Get $get) => $get('type') !== 'text')
                                            ->schema([
                                                RichEditor::make('settings.content')
                                                    ->label('Content')
                                                    ->columnSpanFull(),
                                            ]),

                                        // MENU block settings
                                        Section::make('Menu')
                                            ->collapsed(fn(Get $get) => $get('type') !== 'menu')
                                            ->schema([
                                                Select::make('settings.menu_id')
                                                    ->label('Menu')
                                                    ->options(fn() => \App\Models\Menu::query()->orderBy('name')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    ->required(fn(Get $get) => $get('type') === 'menu'),
                                            ]),

                                        // CATEGORIES block settings
                                        Section::make('Categories')
                                            ->collapsed(fn(Get $get) => $get('type') !== 'categories')
                                            ->schema([
                                                TextInput::make('settings.limit')
                                                    ->label('Max categories')
                                                    ->numeric()
                                                    ->default(10)
                                                    ->minValue(1)
                                                    ->maxValue(100),

                                                Toggle::make('settings.show_count')
                                                    ->label('Show post count')
                                                    ->default(false),
                                            ]),

                                        // SHORTCODE block settings
                                        Section::make('Shortcode')
                                            ->collapsed(fn(Get $get) => $get('type') !== 'shortcode')
                                            ->schema([
                                                Textarea::make('settings.code')
                                                    ->label('Shortcode')
                                                    ->rows(4)
                                                    ->required(fn(Get $get) => $get('type') === 'shortcode'),
                                            ]),

                                        // Keep widget_id hidden so we can update same widget next save
                                        TextInput::make('widget_id')
                                            ->dehydrated()
                                            ->visible(false),
                                    ])
                                    ->default([]),
                            ])
                            ->reorderable(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(SettingsRepository $settings, CmsCacheVersions $versions): void
    {
        $state = $this->form->getState();

        $cols = max(3, min(5, (int) ($state['footer_columns'] ?? 3)));
        $columns = $state['columns'] ?? [];
        if (!is_array($columns)) {
            $columns = [];
        }

        // Normalize columns length
        $columns = array_values($columns);
        while (count($columns) < $cols) {
            $columns[] = ['blocks' => []];
        }
        if (count($columns) > $cols) {
            $columns = array_slice($columns, 0, $cols);
        }

        try {
            DB::transaction(function () use ($settings, $versions, $cols, &$columns) {
                // save columns count
                $settings->set('core', 'footer_columns', $cols);

                // sync widgets+placements per footer column
                for ($i = 1; $i <= $cols; $i++) {
                    $areaKey = "footer-{$i}";
                    $blocks = $columns[$i - 1]['blocks'] ?? [];
                    if (!is_array($blocks)) {
                        $blocks = [];
                    }

                    // wipe placements for this footer area and rebuild cleanly
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

                        // Create or update widget record
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

                            // persist widget_id back into layout so next save updates it
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

                        // Create placement
                        WidgetPlacement::query()->create([
                            'widget_area_key' => $areaKey,
                            'widget_id' => $widgetId,
                            'sort_order' => $sort++,
                            'overrides' => null,
                            'visibility' => null,
                        ]);
                    }

                    // bump widget-area cache
                    $versions->bump('widget_area', $areaKey);
                }

                // store layout JSON for UI reload/edit
                $settings->set('core', 'footer_builder_layout', json_encode($columns));

                // bump global render too (theme footer HTML depends on it)
                $versions->bumpRender();
            });

            // refresh form state (important because we inject widget_id on first save)
            $this->form->fill([
                'footer_columns' => $cols,
                'columns' => $columns,
            ]);

            Notification::make()->success()->title('Footer saved')->send();
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