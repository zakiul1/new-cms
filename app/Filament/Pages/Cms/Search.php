<?php

namespace App\Filament\Pages\Cms;

use App\Cms\Search\SearchIndex;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class Search extends Page
{
    protected string $view = 'filament.pages.cms.search';

    protected static string|UnitEnum|null $navigationGroup = 'CMS';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $title = 'Search';
    protected static ?int $navigationSort = 999;

    public string $q = '';
    public ?string $type = null; // post|page|null
    public array $results = [];
    public int $total = 0;

    public function searchNow(): void
    {
        $q = trim($this->q);

        if ($q === '') {
            $this->results = [];
            $this->total = 0;

            Notification::make()
                ->title('Type something to search')
                ->warning()
                ->send();

            return;
        }

        $res = app(SearchIndex::class)->search($q, 1, 20, $this->type);

        $this->results = array_map(static fn($d) => [
            'type' => (string) $d->entity_type,
            'title' => (string) $d->title,
            'url' => (string) $d->url,
        ], $res['items'] ?? []);

        $this->total = (int) ($res['total'] ?? 0);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reindex')
                ->label('Rebuild Index')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Rebuild search index')
                ->modalDescription('This will rebuild the search index. If you choose “Truncate”, it clears everything first (slower, but clean).')
                ->form([
                    Toggle::make('truncate')
                        ->label('Truncate index first')
                        ->helperText('Recommended if search results look incorrect or outdated.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    try {
                        $cmd = 'cms:search-reindex';
                        if (!empty($data['truncate'])) {
                            $cmd .= ' --truncate';
                        }

                        \Artisan::call($cmd);

                        Notification::make()
                            ->title('Search index rebuilt')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        report($e);

                        Notification::make()
                            ->title('Rebuild failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}