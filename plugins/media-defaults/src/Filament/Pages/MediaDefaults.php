<?php

namespace Plugins\MediaDefaults\Filament\Pages;

use App\Cms\Core\Settings;
use App\Filament\Forms\Components\WpClassicEditor;
use App\Models\Taxonomy;
use App\Models\Term;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use UnitEnum;

class MediaDefaults extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Media';
    protected static ?string $navigationLabel = 'Media Defaults';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 60;

    protected string $view = 'media-defaults::filament.pages.media-defaults';

    /**
     * Selected media category term id (PUBLIC only).
     */
    public ?int $activeCategoryId = null;

    /**
     * Options for select: [id => name]
     */
    public array $categoryOptions = [];

    public array $data = [
        'default_title' => '',
        'default_description' => '',
        'default_sub_title' => '',
        'default_sub_description' => '',
    ];

    protected function getForms(): array
    {
        return ['form'];
    }

    public function mount(): void
    {
        $this->categoryOptions = $this->loadPublicMediaCategoryOptions();

        // Default to first option if none selected
        if ($this->activeCategoryId === null && !empty($this->categoryOptions)) {
            $firstId = array_key_first($this->categoryOptions);
            $this->activeCategoryId = $firstId !== null ? (int) $firstId : null;
        }

        $this->loadDefaultsIntoForm();
    }

    /**
     * Returns [id => name] for PUBLIC media categories.
     */
    protected function loadPublicMediaCategoryOptions(): array
    {
        $taxonomyId = Taxonomy::idByKey('media_category');
        if (!$taxonomyId) {
            return [];
        }

        /** @var Collection<int, Term> $terms */
        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('visibility', 'public')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Example: [12 => "t-shirt", 15 => "polo", ...]
        return $terms->pluck('name', 'id')->all();
    }

    /**
     * Load defaults for selected category (category-wise) with global fallback.
     */
    protected function loadDefaultsIntoForm(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        // Global fallback
        $global = [
            'default_title' => (string) $settings->get('default_title', '', $group),
            'default_description' => (string) $settings->get('default_description', '', $group),
            'default_sub_title' => (string) $settings->get('default_sub_title', '', $group),
            'default_sub_description' => (string) $settings->get('default_sub_description', '', $group),
        ];

        $categoryDefaults = (array) $settings->get('category_defaults', [], $group);

        $cat = [];
        if ($this->activeCategoryId !== null) {
            $cat = $categoryDefaults[(string) $this->activeCategoryId] ?? [];
            $cat = is_array($cat) ? $cat : [];
        }

        $this->data = [
            'default_title' => (string) ($cat['default_title'] ?? $global['default_title']),
            'default_description' => (string) ($cat['default_description'] ?? $global['default_description']),
            'default_sub_title' => (string) ($cat['default_sub_title'] ?? $global['default_sub_title']),
            'default_sub_description' => (string) ($cat['default_sub_description'] ?? $global['default_sub_description']),
        ];

        $this->form->fill($this->data);
    }

    /**
     * Called when dropdown changes (Livewire).
     */
    public function updatedActiveCategoryId($value): void
    {
        $termId = is_numeric($value) ? (int) $value : null;

        // Validate it's in allowed options
        if ($termId !== null && !array_key_exists($termId, $this->categoryOptions)) {
            Notification::make()->title('Invalid category')->danger()->send();
            return;
        }

        $this->activeCategoryId = $termId;
        $this->loadDefaultsIntoForm();
    }

    /**
     * A small schema for the category picker section in the header area.
     * We'll render it from the blade using $this->categoryForm.
     */
    public function categoryForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('activeCategoryId')
            ->schema([
                Select::make('')
                    ->label('Media Category')
                    ->options(fn() => $this->categoryOptions)
                    ->searchable()
                    ->native(false)
                    ->placeholder('Select category...')
                    ->required(),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                TextInput::make('default_title')
                    ->label('Default Title')
                    ->maxLength(255)
                    ->helperText('Used only if Media title is empty.')
                    ->columnSpanFull(),

                WpClassicEditor::make('default_description')
                    ->label('Default Description (Product)')
                    ->height(260)
                    ->columnSpanFull()
                    ->helperText('Used only if Media description is empty.'),

                TextInput::make('default_sub_title')
                    ->label('Default Sub title')
                    ->maxLength(255)
                    ->helperText('Used only if Media Sub title is empty.')
                    ->columnSpanFull(),

                WpClassicEditor::make('default_sub_description')
                    ->label('Default Sub description')
                    ->height(260)
                    ->columnSpanFull()
                    ->helperText('Used only if Media Sub description is empty.'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(fn() => $this->save()),
        ];
    }

    public function save(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        $state = $this->form->getState();
        $this->data = is_array($state) ? $state : $this->data;

        if ($this->activeCategoryId === null) {
            Notification::make()->title('Please select a category')->danger()->send();
            return;
        }

        $categoryDefaults = (array) $settings->get('category_defaults', [], $group);
        if (!is_array($categoryDefaults)) {
            $categoryDefaults = [];
        }

        $categoryDefaults[(string) $this->activeCategoryId] = [
            'default_title' => (string) ($this->data['default_title'] ?? ''),
            'default_description' => (string) ($this->data['default_description'] ?? ''),
            'default_sub_title' => (string) ($this->data['default_sub_title'] ?? ''),
            'default_sub_description' => (string) ($this->data['default_sub_description'] ?? ''),
        ];

        $settings->set('category_defaults', $categoryDefaults, $group);

        Notification::make()->title('Saved')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'categoryOptions' => $this->categoryOptions,
        ];
    }
}