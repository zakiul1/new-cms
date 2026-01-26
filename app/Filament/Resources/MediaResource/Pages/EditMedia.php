<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Cms\Media\MediaUploader;
use App\Filament\Resources\MediaResource;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    /** @var TemporaryUploadedFile|UploadedFile|null */
    protected TemporaryUploadedFile|UploadedFile|null $pendingReplaceFile = null;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                // LEFT: Preview
                Section::make('Preview')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->schema([
                        Placeholder::make('preview')
                            ->label('')
                            ->content(function (): HtmlString {
                                /** @var Media|null $record */
                                $record = $this->record;

                                if (!$record) {
                                    return new HtmlString('');
                                }

                                $html = view('filament.media.edit-preview', [
                                    'record' => $record->loadMissing(['variants', 'terms']),
                                ])->render();

                                return new HtmlString($html);
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),

                // RIGHT: Editable fields
                Section::make('Details')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->maxLength(255)
                            ->required(),

                        TextInput::make('alt')
                            ->label('Alt text')
                            ->maxLength(255),

                        Textarea::make('caption')
                            ->label('Caption')
                            ->rows(2),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4),

                        // IMPORTANT: do NOT set dehydrated(false) here
                        // We capture it and remove before model update.
                        FileUpload::make('replace_file')
                            ->label('Replace file')
                            ->storeFiles(false)
                            ->helperText('Replaces original file. Variants regenerate for images.'),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Capture replace_file safely (FileUpload may return array)
        $replace = $data['replace_file'] ?? null;

        if (is_array($replace)) {
            $replace = collect($replace)->first();
        }

        if ($replace instanceof TemporaryUploadedFile || $replace instanceof UploadedFile) {
            $this->pendingReplaceFile = $replace;
        }

        // Never save this into DB
        unset($data['replace_file']);

        return $data;
    }

    protected function afterSave(): void
    {
        if (!$this->pendingReplaceFile) {
            return;
        }

        /** @var Media $record */
        $record = $this->record;

        app(MediaUploader::class)->replace($record, $this->pendingReplaceFile);

        // Reload record so url/filename changes reflect immediately
        $record->refresh();

        // Refill the form so UI uses fresh record data
        $this->fillForm();

        $this->pendingReplaceFile = null;

        Notification::make()
            ->title('File replaced.')
            ->success()
            ->send();

        // If you want: queue regen button stays separate, but replace already regenerates.
        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->delete();

                    Notification::make()
                        ->title('Deleted.')
                        ->success()
                        ->send();

                    $this->redirect(MediaResource::getUrl('index'));
                }),



            Action::make('regenerate')
                ->label('Regenerate variants')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn(Media $record) => $record->isImage())
                ->action(function (Media $record): void {
                    // Only if your job supports (mediaId, force). If not, remove the "true".
                    $job = GenerateMediaVariants::dispatch($record->id, true);

                    $queueEnabled = (bool) config('cms-media.queue.enabled', true);
                    if ($queueEnabled) {
                        $connection = (string) config('cms-media.queue.connection', config('queue.default'));
                        $queue = (string) config('cms-media.queue.queue', 'media');
                        $job->onConnection($connection)->onQueue($queue);
                    }

                    Notification::make()
                        ->title('Variant regeneration queued.')
                        ->success()
                        ->send();
                }),


            Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MediaResource::getUrl('index')),
        ];
    }
}