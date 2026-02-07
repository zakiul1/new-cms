<?php

namespace App\Filament\Pages;

use App\Cms\Menus\MenuRegistry;
use App\Models\Menu;
use App\Models\MenuAssignment;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use UnitEnum;
use BackedEnum;

class MenuLocations extends Page
{
    use InteractsWithForms;

  protected static string|UnitEnum|null $navigationGroup = 'CMS';
  
 
  

    public ?array $data = [];

     public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public function mount(): void
    {
        $this->form->fill($this->loadState());
    }

    public function form(Form $form): Form
    {
        $locations = app(MenuRegistry::class)->all();

        $schema = [];

        foreach ($locations as $key => $meta) {
            $schema[] = Select::make("locations.{$key}")
                ->label($meta['label'] . " ({$key})")
                ->options(fn() => Menu::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->nullable();
        }

        return $form
            ->schema([
                Section::make('Assign menus to registered locations')
                    ->schema($schema)
                    ->columns(['default' => 1, 'lg' => 2]),
            ])
            ->statePath('data');
    }

    protected function loadState(): array
    {
        $out = ['locations' => []];

        $assignments = MenuAssignment::query()->get()->keyBy('location_key');
        $locations = app(MenuRegistry::class)->all();

        foreach ($locations as $key => $_meta) {
            $out['locations'][$key] = $assignments[$key]->menu_id ?? null;
        }

        return $out;
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $locations = $state['locations'] ?? [];

        foreach ($locations as $key => $menuId) {
            if (blank($menuId)) {
                MenuAssignment::query()->where('location_key', $key)->delete();
                continue;
            }

            MenuAssignment::query()->updateOrCreate(
                ['location_key' => $key],
                ['menu_id' => (int) $menuId],
            );
        }

        $this->notify('success', 'Menu locations saved.');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save')
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }
}