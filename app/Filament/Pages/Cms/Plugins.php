<?php

namespace App\Filament\Pages\Cms;
use UnitEnum;
use BackedEnum;
use App\Cms\Plugins\PluginInstaller;
use App\Cms\Plugins\PluginManager;
use App\Cms\Plugins\PluginPublisher;
use App\Cms\Plugins\PluginUninstaller;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;



class Plugins extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Appearance';
    protected static ?string $navigationLabel = 'Plugins'; // optional
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece'; // optional
    protected static ?int $navigationSort = 30; // optional

    public function getView(): string
    {
        return 'filament.pages.cms.plugins';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn(): Collection => $this->pluginRecords())
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('version')->label('Version'),
                IconColumn::make('enabled')->boolean()->label('Enabled'),
                IconColumn::make('has_assets')->boolean()->label('Assets'),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn(array $record) => $record['enabled'] ? 'Disable' : 'Enable')
                    ->icon(fn(array $record) => $record['enabled'] ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(function (array $record) {
                        $plugins = app(PluginManager::class);

                        if ($record['enabled']) {
                            $plugins->disable($record['slug']);
                            Notification::make()->title('Plugin disabled')->success()->send();
                        } else {
                            $plugins->enable($record['slug']);
                            Notification::make()->title('Plugin enabled')->success()->send();
                        }

                        $this->dispatch('$refresh');
                    }),

                Action::make('publish')
                    ->label('Publish assets')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn(array $record) => (bool) $record['has_assets'])
                    ->action(function (array $record) {
                        app(PluginPublisher::class)->publish($record['slug']);
                        Notification::make()->title('Assets published')->success()->send();
                    }),
                Action::make('settings')
                    ->label('Settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn(array $record) => !empty(data_get($record, 'raw.settings.fields', [])))
                    ->url(fn(array $record) => \App\Filament\Pages\Cms\PluginSettings::getUrl() . '?slug=' . $record['slug'])

                ,
                Action::make('editor')
                    ->label('Edit Files')
                    ->icon('heroicon-o-code-bracket')
                    ->url(fn(array $record) => \App\Filament\Pages\Cms\PluginEditor::getUrl() . '?slug=' . $record['slug'])
                ,




                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->modalHeading('Plugin details')
                    ->modalContent(fn(array $record) => view(
                        'filament.pages.cms.partials.plugin-details',
                        ['json' => json_encode($record['raw'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}']
                    ))
                    ->modalSubmitAction(false),

                Action::make('uninstall')
                    ->label('Uninstall')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record) {
                        $plugins = app(PluginManager::class);

                        $plugins->disable($record['slug']);
                        app(PluginUninstaller::class)->uninstall($record['slug']);

                        Notification::make()->title('Plugin uninstalled')->success()->send();
                        $this->dispatch('$refresh');
                    }),
            ])
            ->headerActions([
                Action::make('install')
                    ->label('Install ZIP')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->modalHeading('Install plugin (.zip)')
                    ->form([
                        FileUpload::make('zip')
                            ->label('Plugin ZIP')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                            ->disk('local')
                            ->directory('tmp/plugin-zips')
                            ->preserveFilenames()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $relative = (string) $data['zip'];
                        $absolute = storage_path('app/' . ltrim($relative, '/'));

                        $slug = app(PluginInstaller::class)->installFromZip($absolute);

                        app(PluginPublisher::class)->publishIfMissing($slug);

                        Notification::make()->title("Installed: {$slug}")->success()->send();
                        $this->dispatch('$refresh');
                    }),

                Action::make('rescan')
                    ->label('Rescan')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        Notification::make()->title('Rescanned')->success()->send();
                        $this->dispatch('$refresh');
                    }),
            ]);
    }

    private function pluginRecords(): Collection
    {
        $plugins = app(PluginManager::class);

        $enabled = $plugins->enabledSlugs();
        $all = $plugins->all(); // slug => PluginManifest

        return collect($all)->map(function ($m, $slug) use ($enabled) {
            $assets = $m->assets ?? [];
            $hasAssets = !empty($assets['styles'] ?? []) || !empty($assets['scripts'] ?? []);

            return [
                'name' => $m->name ?: $slug,
                'slug' => $slug,
                'version' => $m->version ?: '',
                'enabled' => in_array($slug, $enabled, true),
                'has_assets' => $hasAssets,
                'raw' => $m->raw ?? [],
            ];
        })->values();
    }
}