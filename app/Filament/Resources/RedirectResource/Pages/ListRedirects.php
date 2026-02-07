<?php

namespace App\Filament\Resources\RedirectResource\Pages;

use App\Filament\Resources\RedirectResource;
use App\Models\Redirect;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('import_csv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('csv')
                        ->label('CSV file')
                        ->acceptedFileTypes(['text/csv', 'text/plain'])
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->preserveFilenames(),

                    Select::make('default_code')
                        ->label('Default status code')
                        ->options([
                            301 => '301 (Permanent)',
                            302 => '302 (Temporary)',
                            307 => '307 (Temporary, strict)',
                            308 => '308 (Permanent, strict)',
                        ])
                        ->default(301)
                        ->required(),

                    TextInput::make('delimiter')
                        ->label('Delimiter')
                        ->default(',')
                        ->maxLength(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $path = storage_path('app/' . $data['csv']);
                    $delimiter = (string) ($data['delimiter'] ?? ',');
                    $defaultCode = (int) ($data['default_code'] ?? 301);

                    if (!is_file($path)) {
                        Notification::make()->title('CSV not found')->danger()->send();
                        return;
                    }

                    $rows = array_map('str_getcsv', file($path));
                    if (!$rows || count($rows) < 1) {
                        Notification::make()->title('CSV is empty')->danger()->send();
                        return;
                    }

                    // If header present, detect
                    $header = array_map(fn($v) => strtolower(trim((string) $v)), $rows[0] ?? []);
                    $hasHeader = in_array('from', $header, true) || in_array('from_path', $header, true);

                    $start = $hasHeader ? 1 : 0;
                    $imported = 0;

                    for ($i = $start; $i < count($rows); $i++) {
                        $line = $rows[$i];
                        $rawFrom = trim((string) ($line[0] ?? ''));
                        $rawTo = trim((string) ($line[1] ?? ''));
                        $rawCode = trim((string) ($line[2] ?? ''));

                        if ($rawFrom === '' || $rawTo === '') {
                            continue;
                        }

                        $from = $this->normalizePath($rawFrom);
                        $to = $this->normalizeTo($rawTo);

                        $code = $defaultCode;
                        if ($rawCode !== '' && ctype_digit($rawCode)) {
                            $code = (int) $rawCode;
                            if (!in_array($code, [301, 302, 307, 308], true)) {
                                $code = $defaultCode;
                            }
                        }

                        if ($from === $to) {
                            continue;
                        }

                        Redirect::query()->updateOrCreate(
                            ['from_path' => $from],
                            ['to_path' => $to, 'status_code' => $code]
                        );

                        $imported++;
                    }

                    Notification::make()
                        ->title("Imported {$imported} redirects")
                        ->success()
                        ->send();
                }),
        ];
    }

    private function normalizePath(string $p): string
    {
        $p = trim($p);
        if ($p === '' || $p === '/')
            return '/';
        $p = '/' . ltrim($p, '/');
        return rtrim($p, '/');
    }

    private function normalizeTo(string $p): string
    {
        $p = trim($p);
        if ($p === '')
            return '/';

        if (Str::startsWith($p, ['http://', 'https://'])) {
            return $p;
        }

        if ($p === '/')
            return '/';
        $p = '/' . ltrim($p, '/');
        return rtrim($p, '/');
    }
}