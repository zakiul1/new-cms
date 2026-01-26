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
use Filament\Schemas\Schema;

use RuntimeException;

class PluginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'CMS';
    protected static ?string $title = 'Plugin Settings';

    public string $pluginSlug = '';
    public array $data = [];

    public function getView(): string
    {
        return 'filament.pages.cms.plugin-settings';
    }

    // ✅ Register the form name so $this->form exists
    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->pluginSlug = (string) request()->query('slug', '');

        if ($this->pluginSlug === '') {
            $plugins = app(PluginManager::class)->all();

            foreach ($plugins as $slug => $manifest) {
                $raw = is_array($manifest->raw ?? null) ? $manifest->raw : [];
                $fields = $raw['settings']['fields'] ?? null;

                if (is_array($fields) && !empty($fields)) {
                    $this->redirect(static::getUrl() . '?slug=' . $slug);
                    return;
                }
            }

            Notification::make()
                ->title('No plugin settings available yet')
                ->warning()
                ->send();

            $this->redirect(\App\Filament\Pages\Cms\Plugins::getUrl());
            return;
        }

        $this->fillFormFromStorage();

        // ✅ IMPORTANT: fill the Filament form state too
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
{
    $config = $this->schemaOrFail();

    return $schema
        ->statePath('data')
        ->schema($this->toFilamentFields($config['fields']));
}


    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $schema = $this->schemaOrFail();
        $settings = app(Settings::class);

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

    private function schemaOrFail(): array
    {
        $plugins = app(PluginManager::class);

        // ⚠️ You must have this method in PluginManager
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
                if (is_array($opts)) {
                    $opts = array_combine($opts, $opts) ?: [];
                } else {
                    $opts = [];
                }
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
