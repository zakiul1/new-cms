<?php

namespace Plugins\ContactForm\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Plugins\ContactForm\ContactLead;
use Plugins\ContactForm\ContactSubmission;
use Plugins\ContactForm\Services\SubmissionSender;
use Plugins\ContactForm\Support\Installer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactSubmissions extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'plugins.contact-form::filament.contact-submissions';

    protected static ?string $title = 'Contact Submissions';
    protected static ?string $navigationLabel = 'Contact Submissions';
    protected static string|\UnitEnum|null $navigationGroup = 'CMS';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';
    protected static ?int $navigationSort = 61;

    public function mount(): void
    {
        Installer::ensureInstalled();
    }

    protected function getTableQuery(): Builder
    {
        if (!Schema::hasTable('contact_submissions')) {
            return ContactSubmission::query()->whereRaw('1=0');
        }

        return ContactSubmission::query()->orderByDesc('id');
    }

    private function streamCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            $header = array_keys($rows[0] ?? [
                'name' => '',
                'email' => '',
                'country_name' => '',
                'whatsapp' => '',
                'date' => '',
            ]);
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn($v) => is_scalar($v) || $v === null ? $v : json_encode($v), array_values($row)));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->headerActions([
                Action::make('download')
                    ->label('Download Permanent Data (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        // ✅ Export ONLY permanent table: contact_leads
                        if (!Schema::hasTable('contact_leads')) {
                            return $this->streamCsv([
                                [
                                    'name' => '',
                                    'email' => '',
                                    'country_name' => 'Unknown',
                                    'whatsapp' => '',
                                    'date' => '',
                                ]
                            ], 'contact-leads.csv');
                        }

                        $rows = ContactLead::query()
                            ->orderByDesc('id')
                            ->limit(5000)
                            ->get()
                            ->map(function (ContactLead $l) {
                                $country = trim((string) ($l->country_name ?? ''));
                                if ($country === '') {
                                    $country = 'Unknown';
                                }

                                // date only, when email first stored
                                $dateOnly = $l->created_at ? $l->created_at->format('Y-m-d') : '';

                                return [
                                    'name' => (string) ($l->name ?? ''),
                                    'email' => (string) ($l->email ?? ''),
                                    'country_name' => $country,
                                    'whatsapp' => (string) ($l->whatsapp ?? ''),
                                    'date' => $dateOnly,
                                ];
                            })
                            ->toArray();

                        if (empty($rows)) {
                            $rows = [
                                [
                                    'name' => '',
                                    'email' => '',
                                    'country_name' => 'Unknown',
                                    'whatsapp' => 'Not Given',
                                    'date' => '',
                                ]
                            ];
                        }

                        return $this->streamCsv($rows, 'contact-leads.csv');
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('payload.subject')
                    ->label('Subject')
                    ->state(fn(ContactSubmission $record) => (string) ((is_array($record->payload ?? null) ? ($record->payload['subject'] ?? '') : '') ?: ($record->subject ?? '')))
                    ->limit(40),
                Tables\Columns\TextColumn::make('attempts')->label('Attempts')->sortable(),
                Tables\Columns\TextColumn::make('next_retry_at')->label('Next Retry')->dateTime('Y-m-d H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('last_error')->label('Last Error')->limit(60)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sent_at')->label('Sent At')->dateTime('Y-m-d H:i:s')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'sent' => 'Sent',
                ]),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Retry Now')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn(ContactSubmission $record) => $record->status === 'pending')
                    ->action(function (ContactSubmission $record) {
                        try {
                            app(SubmissionSender::class)->attemptSend($record);
                            $record->refresh();

                            if ($record->status === 'sent') {
                                Notification::make()->title('Sent successfully')->success()->send();
                            } else {
                                Notification::make()
                                    ->title('Still pending')
                                    ->body($record->last_error ? ('Error: ' . $record->last_error) : 'No error message.')
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()->title('Retry failed')->danger()->send();
                        }
                    }),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Submission Details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn(ContactSubmission $record) => view(
                        'plugins.contact-form::filament.partials.submission-view',
                        ['s' => $record]
                    )),
            ]);
    }
}