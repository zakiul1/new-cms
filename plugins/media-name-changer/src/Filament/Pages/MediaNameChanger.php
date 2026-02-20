<?php

namespace Plugins\MediaNameChanger\Filament\Pages;

use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Plugins\MediaNameChanger\Support\MediaRenameService;
use Throwable;
use UnitEnum;

class MediaNameChanger extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Media';
    protected static ?string $navigationLabel = 'Media Name Changer';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?int $navigationSort = 65;

    protected string $view = 'media-name-changer::filament.pages.media-name-changer';

    public array $data = [
        'category_id' => null,
        'move_to_id' => null,
        'names' => '',
    ];

    public array $categoryOptions = [];
    public array $moveToOptions = [];

    public ?string $batchKey = null;

    // Used by Blade to decide whether to poll
    public bool $isRunning = false;

    public function mount(): void
    {
        $this->categoryOptions = $this->loadCategoryOptionsWithCounts();
        $this->moveToOptions = [null => 'None'] + $this->categoryOptions;

        if ($this->data['category_id'] === null && !empty($this->categoryOptions)) {
            $first = array_key_first($this->categoryOptions);
            $this->data['category_id'] = $first !== null ? (int) $first : null;
        }

        $this->form->fill(['data' => $this->data]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bulk rename media')
                ->schema([
                    Select::make('data.category_id')
                        ->label('Media Category')
                        ->options(fn() => $this->categoryOptions)
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('data.move_to_id')
                        ->label('Move To')
                        ->options(fn() => $this->moveToOptions)
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Textarea::make('data.names')
                        ->label('')
                        ->rows(18)
                        ->helperText(fn(Get $get) => 'Line count: ' . $this->lineCount((string) $get('data.names'))),
                ])
                ->columns(2),
        ]);
    }

    public function filterNames(): void
    {
        $this->data = $this->form->getState()['data'] ?? $this->data;

        $lines = $this->normalizedLines($this->data['names'] ?? '');

        // remove duplicates (case-insensitive)
        $seen = [];
        $unique = [];
        foreach ($lines as $x) {
            $k = mb_strtolower($x);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $unique[] = $x;
        }

        // remove ones that would conflict with existing slugs
        $kept = [];
        $removed = [];

        foreach ($unique as $name) {
            $slug = Str::slug(Str::limit($name, 120, ''));
            if ($slug === '') {
                $removed[] = "{$name} (invalid)";
                continue;
            }

            $exists = Media::query()->where('slug', $slug)->exists();
            if ($exists) {
                $removed[] = "{$name} (slug exists)";
                continue;
            }

            $kept[] = $name;
        }

        $this->data['names'] = implode("\n", $kept);
        $this->form->fill(['data' => $this->data]);

        Notification::make()
            ->title('Filter complete')
            ->body('Kept: ' . count($kept) . ', Removed: ' . count($removed))
            ->success()
            ->send();
    }

    /**
     * Start rename by preparing a cached batch state.
     * Actual rename happens one-by-one in tickRename() via wire:poll.
     */
    public function startRename(): void
    {
        $this->data = $this->form->getState()['data'] ?? $this->data;

        $categoryId = is_numeric($this->data['category_id'] ?? null) ? (int) $this->data['category_id'] : null;
        if (!$categoryId) {
            Notification::make()->title('Select a category')->danger()->send();
            return;
        }

        $names = $this->normalizedLines((string) ($this->data['names'] ?? ''));
        if (count($names) === 0) {
            Notification::make()->title('Enter at least 1 line')->danger()->send();
            return;
        }

        $mediaIds = $this->mediaIdsByCategory($categoryId);

        if (count($mediaIds) === 0) {
            Notification::make()->title('No media found in that category')->warning()->send();
            return;
        }

        // WP behavior: error if media > lines
        if (count($mediaIds) > count($names)) {
            Notification::make()
                ->title('Error')
                ->body('Image Found ' . count($mediaIds) . ', but Text entered ' . count($names) . ' lines')
                ->danger()
                ->send();
            return;
        }

        $moveToId = is_numeric($this->data['move_to_id'] ?? null) ? (int) $this->data['move_to_id'] : null;

        $this->batchKey = 'media_name_changer:' . Str::random(24);

        Cache::put($this->batchKey, [
            'status' => 'running',
            'total' => count($mediaIds),
            'done' => 0,
            'errors' => [],
            'media_ids' => array_values($mediaIds),
            'names' => array_values($names),
            'move_to_id' => $moveToId,
        ], now()->addHours(2));

        $this->isRunning = true;

        Notification::make()
            ->title('Rename started')
            ->body('Processing ' . count($mediaIds) . ' media items…')
            ->success()
            ->send();
    }

    /**
     * Called repeatedly by wire:poll while running.
     * Processes ONE media item per tick:
     * - renames media
     * - increments progress
     * - removes used name lines from textarea (dynamic reduction)
     */
    public function tickRename(): void
    {
        if (!$this->batchKey) {
            $this->isRunning = false;
            return;
        }

        $state = Cache::get($this->batchKey);
        if (!is_array($state)) {
            $this->isRunning = false;
            return;
        }

        if (($state['status'] ?? '') !== 'running') {
            $this->isRunning = false;
            return;
        }

        $done = (int) ($state['done'] ?? 0);
        $total = (int) ($state['total'] ?? 0);
        $mediaIds = is_array($state['media_ids'] ?? null) ? $state['media_ids'] : [];
        $names = is_array($state['names'] ?? null) ? $state['names'] : [];
        $moveToId = $state['move_to_id'] ?? null;

        if ($done >= $total) {
            Cache::put($this->batchKey, array_merge($state, ['status' => 'finished']), now()->addHours(2));
            $this->isRunning = false;
            return;
        }

        $mediaId = (int) ($mediaIds[$done] ?? 0);
        $name = (string) ($names[$done] ?? '');

        try {
            $media = Media::query()->find($mediaId);
            if (!$media) {
                throw new \RuntimeException("Media not found: {$mediaId}");
            }

            /** @var MediaRenameService $svc */
            $svc = app(MediaRenameService::class);
            $svc->renameOne($media, $name, is_numeric($moveToId) ? (int) $moveToId : null);
        } catch (Throwable $e) {
            $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
            $errors[] = "ID {$mediaId}: " . $e->getMessage();
            $state['errors'] = $errors;
        }

        $done++;

        $newStatus = $done >= $total ? 'finished' : 'running';

        Cache::put($this->batchKey, array_merge($state, [
            'done' => $done,
            'status' => $newStatus,
        ]), now()->addHours(2));

        // ✅ Dynamic textarea shrink: remove used lines
        $remainingNames = array_slice($names, $done);
        $this->data['names'] = implode("\n", $remainingNames);
        $this->form->fill(['data' => $this->data]);

        if ($newStatus === 'finished') {
            $this->isRunning = false;

            Notification::make()
                ->title('Rename finished')
                ->body("Processed {$total} media items.")
                ->success()
                ->send();
        }
    }

    public function progress(): array
    {
        if (!$this->batchKey) {
            return ['status' => 'idle', 'total' => 0, 'done' => 0, 'errors' => []];
        }

        $state = Cache::get($this->batchKey);

        return is_array($state) ? $state : ['status' => 'idle', 'total' => 0, 'done' => 0, 'errors' => []];
    }

    // ---------------- helpers ----------------

    private function normalizedLines(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, fn($x) => $x !== ''));

        return $lines;
    }

    private function lineCount(string $text): int
    {
        return count($this->normalizedLines($text));
    }

    public function getFormStatePath(): string
    {
        return 'data';
    }

    private function mediaIdsByCategory(int $termId): array
    {
        return \Illuminate\Support\Facades\DB::table('termables')
            ->join('media', 'media.id', '=', 'termables.termable_id')
            ->where('termables.term_id', $termId)
            ->where('termables.termable_type', \App\Models\Media::class)
            ->orderBy('termables.termable_id')
            ->pluck('termables.termable_id')
            ->map(fn($x) => (int) $x)
            ->all();
    }

    private function loadCategoryOptionsWithCounts(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId) {
            return [];
        }

        /** @var Collection<int, Term> $terms */
        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $counts = $this->termMediaCounts($taxonomyId);

        $byParent = [];
        foreach ($terms as $t) {
            $pid = (int) ($t->parent_id ?: 0);
            $byParent[$pid] ??= [];
            $byParent[$pid][] = $t;
        }

        $out = [];
        $walk = function ($parentId, $depth) use (&$walk, &$out, $byParent, $counts) {
            foreach (($byParent[$parentId] ?? []) as $term) {
                $id = (int) $term->id;
                $prefix = str_repeat('— ', max(0, $depth));
                $count = (int) ($counts[$id] ?? 0);
                $out[$id] = "{$prefix}{$term->name} ({$count})";
                $walk($id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $out;
    }

    private function termMediaCounts(int $taxonomyId): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('termables')
            ->join('terms', 'terms.id', '=', 'termables.term_id')
            ->join('media', 'media.id', '=', 'termables.termable_id')
            ->where('terms.taxonomy_id', $taxonomyId)
            ->where('termables.termable_type', '=', \App\Models\Media::class)
            ->selectRaw('termables.term_id as term_id, count(*) as c')
            ->groupBy('termables.term_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->term_id] = (int) $r->c;
        }
        return $out;
    }
}