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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

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

                        FileUpload::make('replace_file')
                            ->label('Replace file')
                            ->storeFiles(false)
                            ->dehydrated(false)
                            ->helperText('Replaces original file. Variants regenerate for images.'),
                    ]),
            ]);
    }

    /**
     * ✅ Correct place to handle file replacement in Filament v5
     * This runs during Save, and the uploaded file is reliably available here.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Media $record */

        // Get file from live form state (not from $data)
        $state = $this->form->getState();
        $replace = $state['replace_file'] ?? null;

        // Never save this to DB
        unset($data['replace_file']);

        // Save normal fields
        $record->update($data);

        // Replace physical file + update DB filename/mime/size/dims
        if ($replace instanceof TemporaryUploadedFile || $replace instanceof UploadedFile) {
            app(MediaUploader::class)->replace($record, $replace);

            // Ensure page has latest DB values (filename/url/thumb)
            $record->refresh();

            Notification::make()
                ->title('File replaced.')
                ->success()
                ->send();
        }

        // Force UI refresh so preview updates immediately
        $this->dispatch('$refresh');

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open file')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn(Media $record) => $record->url())
                ->openUrlInNewTab(),

            Action::make('regenerate')
                ->label('Regenerate variants')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn(Media $record) => $record->isImage())
                ->action(function (Media $record): void {
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
        ];
    }
}