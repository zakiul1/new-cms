<?php

namespace Plugins\SiatexTags\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Plugins\SiatexTags\Models\SiatexTag;
use UnitEnum;

class ImportSiatexTags extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string|UnitEnum|null $navigationGroup = 'Tags';
    protected static ?string $navigationLabel = 'Add Tags';
    protected static ?string $title = 'Add Tags';

    protected string $view = 'siatex-tags::filament.import-siatex-tags';

    public array $data = [
        'lines' => '',
    ];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('lines')
                    ->label('Paste tags (one per line)')
                    ->rows(16)
                    ->required()
                    ->helperText('Duplicates will be skipped automatically.'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->action(function () {
                    $raw = (string) ($this->data['lines'] ?? '');

                    $rows = preg_split("/\r\n|\n|\r/", $raw) ?: [];
                    $rows = array_map(fn($v) => trim((string) $v), $rows);
                    $rows = array_values(array_filter($rows, fn($v) => $v !== ''));

                    if (empty($rows)) {
                        Notification::make()->title('Nothing to import')->warning()->send();
                        return;
                    }

                    $created = 0;
                    $skipped = 0;

                    foreach (array_unique($rows) as $title) {
                        $slug = Str::slug($title);

                        if ($slug === '' || SiatexTag::where('slug', $slug)->exists()) {
                            $skipped++;
                            continue;
                        }

                        SiatexTag::create([
                            'title' => $title,
                            'slug' => $slug,
                            'content_json' => ['html' => ''],
                            'meta_json' => [],
                            'media_category_term_id' => null,
                        ]);

                        $created++;
                    }

                    Notification::make()
                        ->title("Imported: {$created}, Skipped: {$skipped}")
                        ->success()
                        ->send();

                    $this->data['lines'] = '';
                    $this->form->fill($this->data);
                }),
        ];
    }
}