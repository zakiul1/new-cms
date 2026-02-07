<?php

namespace App\Filament\Pages\Cms;

use BackedEnum;
use App\Cms\Core\Settings;
use App\Cms\Plugins\PluginManager;
use App\Cms\Plugins\PluginSettingsSchema;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;

use Filament\Schemas\Schema;

class PluginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';
    protected static ?string $title = 'Plugin Settings';


    // ✅ do not use $slug (Page has static $slug)
    public string $pluginSlug = '';

    // Form state
    public array $data = [];

    public function getView(): string
    {
        return 'filament.pages.cms.plugin-settings';
    }

    // ✅ this creates $this->form
    protected function getForms(): array
    {
        return ['form'];
    }

    // ✅ IMPORTANT: do NOT show in sidebar (needs ?slug=...)
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->pluginSlug = (string) request()->query('slug', '');

        // If opened without slug, bounce back to Plugins page with a warning
        if ($this->pluginSlug === '') {
            Notification::make()
                ->title('Missing plugin slug.')
                ->warning()
                ->send();

            $this->redirect(\App\Filament\Pages\Cms\Plugins::getUrl());
            return;
        }

        // Load saved values into $this->data
        $this->fillFormFromStorage();

        // ✅ Also fill Filament form state
        $this->form->fill($this->data);
    }

    public function form(Schema $form): Schema
    {
        $config = $this->schemaOrFail();

        return $form
            ->statePath('data')
            ->schema($this->toFilamentFields($config['fields']));
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
        $schema = $this->schemaOrFail();
        $settings = app(Settings::class);

        // ✅ Always read latest state from Filament form
        $state = $this->form->getState();
        $this->data = is_array($state) ? $state : [];

        foreach ($schema['fields'] as $f) {
            $key = $f['key'];

            $settings->set(
                $key,
                $this->data[$key] ?? ($f['default'] ?? null),
                $schema['group']
            );
        }

        Notification::make()->title('Saved')->success()->send();
    }

    private function fillFormFromStorage(): void
    {
        $schema = $this->schemaOrFail();
        $settings = app(Settings::class);

        $state = [];
        foreach ($schema['fields'] as $f) {
            $key = $f['key'];
            $default = $f['default'] ?? null;
            $state[$key] = $settings->get($key, $default, $schema['group']);
        }

        $this->data = $state;
    }

    /**
     * @return array{group:string,fields:array<int,array<string,mixed>>}
     */
    private function schemaOrFail(): array
    {
        $plugins = app(PluginManager::class);

        // ✅ PluginManager must have manifest($slug)
        $manifest = $plugins->manifest($this->pluginSlug);

        if (!$manifest) {
            throw new RuntimeException("Plugin not found: {$this->pluginSlug}");
        }

        $schema = app(PluginSettingsSchema::class)->schema($manifest);

        if (!$schema) {
            throw new RuntimeException("Plugin has no settings: {$this->pluginSlug}");
        }

        return $schema;
    }

    private function toFilamentFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $f) {
            $key = $f['key'];
            $label = $f['label'] ?? $key;
            $helper = $f['helper'] ?? '';
            $required = (bool) ($f['required'] ?? false);
            $type = $f['type'] ?? 'text';

            if ($type === 'toggle') {
                $c = Toggle::make($key)->label($label);
            } elseif ($type === 'select') {
                $opts = $f['options'] ?? [];
                $opts = is_array($opts) ? (array_combine($opts, $opts) ?: []) : [];
                $c = Select::make($key)->label($label)->options($opts);
            } elseif ($type === 'number') {
                $c = TextInput::make($key)->label($label)->numeric();
            } else {
                $c = TextInput::make($key)->label($label);
            }

            if ($helper) {
                $c->helperText($helper);
            }

            if ($required) {
                $c->required();
            }

            $out[] = $c;
        }

        return $out;
    }
}