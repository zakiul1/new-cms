<?php

namespace App\Filament\Pages\Cms;

use BackedEnum;
use App\Cms\Core\SafeMode;
use App\Cms\Plugins\PluginFileManager;
use App\Cms\Plugins\PluginManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class PluginEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'plugin-editor';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-code-bracket';
    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';
    protected static ?string $title = 'Plugin Editor';


    public string $pluginSlug = '';
    public string $filePath = '';
    public bool $readOnly = true;

    /** Dirty tracking */
    public bool $dirty = false;
    public string $originalContent = '';

    /** Confirm-switch modal */
    public bool $showSwitchModal = false;
    public ?string $pendingFilePath = null;
    public ?string $pendingPluginSlug = null;

    /** @var array<int, array{path:string,label:string,ext:string,editable:bool,binary:bool,mime:string}> */
    public array $files = [];

    public array $data = [
        'plugin' => '',
        'file' => '',
        'content' => '',
    ];

    public function getView(): string
    {
        return 'filament.pages.cms.plugin-editor';
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Premium page: open from Plugins list action
        return false;
    }

    public function mount(): void
    {
        $this->authorizeAccess();

        if (app(SafeMode::class)->isEnabled(request())) {
            $this->readOnly = true;
        }

        $slug = (string) request()->query('slug', '');
        if ($slug === '') {
            $all = app(PluginManager::class)->all();
            $slug = (string) array_key_first($all);
        }

        if ($slug === '') {
            Notification::make()->title('No plugins found')->warning()->send();
            $this->redirect(Plugins::getUrl());
            return;
        }

        $this->pluginSlug = $slug;
        $this->refreshTree();

        $file = (string) request()->query('file', '');
        if ($file === '' && !empty($this->files)) {
            $file = (string) ($this->files[0]['path'] ?? '');
        }

        if ($file !== '') {
            $this->openFileInternal($file);
        } else {
            $this->data['plugin'] = $this->pluginSlug;
            $this->data['file'] = '';
            $this->data['content'] = '';
            $this->originalContent = '';
            $this->dirty = false;
        }

        $this->form->fill($this->data);

        // Push current content to Monaco on first load
        $this->dispatchMonaco();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Select::make('plugin')
                    ->label('Plugin')
                    ->options($this->pluginOptions())
                    ->default(fn() => $this->pluginSlug)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $next = (string) $state;

                        // If dirty -> revert UI + ask confirm
                        if ($this->dirty && $next !== $this->pluginSlug) {
                            $this->pendingPluginSlug = $next;
                            $this->data['plugin'] = $this->pluginSlug;
                            $this->form->fill($this->data);

                            $this->showSwitchModal = true;
                            return;
                        }

                        $this->switchPluginInternal($next);
                    }),

                Select::make('file')
                    ->label('File')
                    ->options(fn() => $this->fileOptions())
                    ->default(fn() => $this->filePath)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $path = (string) $state;

                        if ($path === '' || $path === $this->filePath) {
                            return;
                        }

                        // If dirty -> revert UI + ask confirm
                        if ($this->dirty) {
                            $this->pendingFilePath = $path;

                            $this->data['file'] = $this->filePath;
                            $this->form->fill($this->data);

                            $this->showSwitchModal = true;
                            return;
                        }

                        $this->openFileInternal($path);
                        $this->form->fill($this->data);
                        $this->dispatchMonaco();
                    }),

                // Monaco writes into this via Livewire set()
                Hidden::make('content')->dehydrated(true),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleReadOnly')
                ->label(fn() => $this->readOnly ? 'Enable Editing' : 'Read-only')
                ->icon(fn() => $this->readOnly ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->action(function () {
                    $this->authorizeAccess();

                    if (app(SafeMode::class)->isEnabled(request())) {
                        Notification::make()->title('Safe Mode enabled. Editing blocked.')->danger()->send();
                        $this->readOnly = true;
                        $this->dispatchMonacoReadonly();
                        return;
                    }

                    $this->readOnly = !$this->readOnly;
                    $this->dispatchMonacoReadonly();
                }),

            Action::make('save')
                ->label($this->dirty ? 'Save ●' : 'Save')
                ->icon('heroicon-o-check')
                ->disabled(fn() => $this->readOnly || $this->filePath === '')
                ->action(fn() => $this->saveFile()),
        ];
    }

    /**
     * Left file tree click calls this.
     */
    public function selectFile(string $path): void
    {
        $this->authorizeAccess();

        $path = (string) $path;
        if ($path === '' || $path === $this->filePath) {
            return;
        }

        if ($this->dirty) {
            $this->pendingFilePath = $path;
            $this->pendingPluginSlug = null;
            $this->showSwitchModal = true;
            return;
        }

        $this->openFileInternal($path);
        $this->form->fill($this->data);
        $this->dispatchMonaco();
    }

    public function saveFile(): void
    {
        $this->authorizeAccess();

        if ($this->readOnly) {
            Notification::make()->title('Read-only mode')->warning()->send();
            return;
        }

        if ($this->filePath === '') {
            Notification::make()->title('No file selected')->warning()->send();
            return;
        }

        try {
            $fm = app(PluginFileManager::class);
            $fm->write($this->pluginSlug, $this->filePath, (string) ($this->data['content'] ?? ''));

            // Mark clean
            $this->originalContent = (string) ($this->data['content'] ?? '');
            $this->dirty = false;

            // Tell JS editor “saved”
            $this->dispatch('monaco-saved');

            Notification::make()->title('Saved')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Save failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function confirmSaveAndSwitch(): void
    {
        // Save current first
        $this->saveFile();

        // If save failed, keep modal open (dirty stays true)
        if ($this->dirty) {
            return;
        }

        $this->confirmDiscardAndSwitch();
    }

    public function confirmDiscardAndSwitch(): void
    {
        // Discard changes
        $this->dirty = false;
        $this->data['content'] = $this->originalContent;

        $this->showSwitchModal = false;

        if ($this->pendingPluginSlug) {
            $next = $this->pendingPluginSlug;
            $this->pendingPluginSlug = null;
            $this->pendingFilePath = null;

            $this->switchPluginInternal($next);
            $this->form->fill($this->data);
            $this->dispatchMonaco();
            return;
        }

        if ($this->pendingFilePath) {
            $nextFile = $this->pendingFilePath;
            $this->pendingFilePath = null;

            $this->openFileInternal($nextFile);
            $this->form->fill($this->data);
            $this->dispatchMonaco();
            return;
        }
    }

    public function cancelSwitch(): void
    {
        $this->showSwitchModal = false;
        $this->pendingFilePath = null;
        $this->pendingPluginSlug = null;
    }

    public function refreshTree(): void
    {
        $fm = app(PluginFileManager::class);
        $this->files = $fm->tree($this->pluginSlug);

        $this->data['plugin'] = $this->pluginSlug;

        // Keep file dropdown state in sync
        $this->data['file'] = $this->filePath;
    }

    private function openFileInternal(string $path): void
    {
        $this->authorizeAccess();

        $fm = app(PluginFileManager::class);

        $this->filePath = $path;
        $this->data['plugin'] = $this->pluginSlug;
        $this->data['file'] = $path;

        if ($fm->isBinary($this->pluginSlug, $path)) {
            $content = "Binary file: {$path}\n\nThis file can’t be edited as text.";
            $this->data['content'] = $content;
            $this->originalContent = $content;
            $this->dirty = false;
            return;
        }

        $content = $fm->read($this->pluginSlug, $path);

        $this->data['content'] = $content;
        $this->originalContent = $content;
        $this->dirty = false;
    }

    private function switchPluginInternal(string $slug): void
    {
        $this->authorizeAccess();

        $this->pluginSlug = $slug;
        $this->filePath = '';

        $this->refreshTree();

        $first = (string) ($this->files[0]['path'] ?? '');
        if ($first !== '') {
            $this->openFileInternal($first);
        } else {
            $this->data['plugin'] = $this->pluginSlug;
            $this->data['file'] = '';
            $this->data['content'] = '';
            $this->originalContent = '';
            $this->dirty = false;
        }
    }

    private function dispatchMonaco(): void
    {
        $ext = strtolower(pathinfo($this->filePath ?: '', PATHINFO_EXTENSION));

        $this->dispatch(
            'monaco-set',
            content: (string) ($this->data['content'] ?? ''),
            ext: $ext,
            readOnly: $this->readOnly,
        );
    }

    private function dispatchMonacoReadonly(): void
    {
        $this->dispatch(
            'monaco-readonly',
            readOnly: $this->readOnly,
        );
    }


    #[On('monaco-save')]
    public function onMonacoSave(): void
    {
        $this->saveFile();
    }

    #[On('monaco-dirty')]
    public function onMonacoDirty(bool $dirty = true): void
    {
        // Don’t mark dirty if still same as original
        $current = (string) ($this->data['content'] ?? '');
        $this->dirty = ($current !== $this->originalContent) ? $dirty : false;
    }

    private function pluginOptions(): array
    {
        $all = app(PluginManager::class)->all();
        $out = [];
        foreach ($all as $slug => $m) {
            $out[$slug] = ($m->name ?: $slug) . " ({$slug})";
        }
        return $out;
    }

    private function fileOptions(): array
    {
        $out = [];
        foreach ($this->files as $f) {
            $path = (string) ($f['path'] ?? '');
            if ($path !== '') {
                $out[$path] = $path;
            }
        }
        return $out;
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (method_exists($user, 'hasRole')) {
            abort_unless($user->hasRole('super-admin'), 403);
            return;
        }

        abort_unless((int) $user->id === 1, 403);
    }
}