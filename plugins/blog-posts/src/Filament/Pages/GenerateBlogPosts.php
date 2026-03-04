<?php

namespace Plugins\BlogPosts\Filament\Pages;

use App\Models\Post;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

class GenerateBlogPosts extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Blog Posts';
    protected static ?string $navigationLabel = 'Generate Posts';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?int $navigationSort = 4;

    // ✅ IMPORTANT: standalone Filament page must define slug (route)
    protected static ?string $slug = 'blog-posts/generate';

    protected string $view = 'blog-posts::filament.pages.generate-posts';

    public array $data = [
        'category_id' => null,
        'text' => '',
    ];

    public array $categoryOptions = [];

    public ?string $batchKey = null;
    public bool $isRunning = false;

    public function mount(): void
    {
        $this->categoryOptions = $this->loadCategoryOptionsWithCounts();

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
            Section::make('Generate blog posts')
                ->schema([
                    Select::make('data.category_id')
                        ->label('Blog Category')
                        ->options(fn() => $this->categoryOptions)
                        ->searchable()
                        ->preload()
                        ->required(),

                    Textarea::make('data.text')
                        ->label('')
                        ->rows(18)
                        ->helperText(fn(Get $get) => 'Posts found: ' . $this->blockCount((string) $get('data.text'))),
                ])
                ->columns(2),
        ]);
    }

    public function startGenerate(): void
    {
        $this->data = $this->form->getState()['data'] ?? $this->data;

        $categoryId = is_numeric($this->data['category_id'] ?? null) ? (int) $this->data['category_id'] : null;
        if (!$categoryId) {
            Notification::make()->title('Select a category')->danger()->send();
            return;
        }

        $taxonomyId = Taxonomy::query()->where('key', 'blog_category')->value('id');
        if (!$taxonomyId) {
            Notification::make()->title('Blog category taxonomy not found')->danger()->send();
            return;
        }

        $termOk = Term::query()->where('taxonomy_id', $taxonomyId)->whereKey($categoryId)->exists();
        if (!$termOk) {
            Notification::make()->title('Selected category is invalid')->danger()->send();
            return;
        }

        // ✅ IMPORTANT: blocks now mean 1 post each (Title paragraph + Content paragraph)
        $blocks = $this->normalizedBlocks((string) ($this->data['text'] ?? ''));
        if (count($blocks) === 0) {
            Notification::make()->title('Enter at least 1 post (Title + Content)')->danger()->send();
            return;
        }

        $this->batchKey = 'blog_posts_generate:' . Str::random(24);

        Cache::put($this->batchKey, [
            'status' => 'running',
            'total' => count($blocks),
            'done' => 0,
            'created' => 0,
            'errors' => [],
            'category_id' => $categoryId,
            'blocks' => array_values($blocks),
        ], now()->addHours(2));

        $this->isRunning = true;

        Notification::make()
            ->title('Generation started')
            ->body('Creating ' . count($blocks) . ' blog posts…')
            ->success()
            ->send();
    }

    public function tickGenerate(): void
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
        $blocks = is_array($state['blocks'] ?? null) ? $state['blocks'] : [];
        $categoryId = is_numeric($state['category_id'] ?? null) ? (int) $state['category_id'] : null;

        if ($done >= $total) {
            Cache::put($this->batchKey, array_merge($state, ['status' => 'finished']), now()->addHours(2));
            $this->isRunning = false;
            return;
        }

        $raw = (string) ($blocks[$done] ?? '');

        try {
            [$title, $contentText] = $this->splitTitleAndBody($raw);

            $title = trim((string) $title);
            if ($title === '') {
                throw new \RuntimeException('Missing title (first paragraph / first line).');
            }

            // ✅ enforce DB length limits
            $title = Str::limit($title, 255, '');

            $baseSlug = Str::slug($title) ?: 'item';
            $baseSlug = Str::limit($baseSlug, 191, '');
            $slug = $this->uniqueSlugForPostsWithLimit($baseSlug, 191);

            $html = $this->textToHtml($contentText);

            // ✅ REQUIRED by your DB: author_id
            $authorId = auth()->id() ?? 1;

            /** @var Post $post */
            $post = Post::query()->create([
                'type' => 'blog_post',
                'title' => $title,
                'slug' => $slug,
                'status' => 'published',
                'excerpt' => null,
                'content_json' => ['html' => $html],
                'meta_json' => [],
                'author_id' => $authorId,
            ]);

            // ✅ Attach category (do not wipe any other terms)
            if ($categoryId) {
                $current = $post->terms()->pluck('terms.id')->all();
                $final = array_values(array_unique(array_merge($current, [(int) $categoryId])));
                $post->terms()->sync($final);
            }

            $state['created'] = (int) ($state['created'] ?? 0) + 1;
        } catch (Throwable $e) {
            $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
            $errors[] = 'Post #' . ($done + 1) . ': ' . $e->getMessage();
            $state['errors'] = $errors;
        }

        $done++;
        $newStatus = $done >= $total ? 'finished' : 'running';

        Cache::put($this->batchKey, array_merge($state, [
            'done' => $done,
            'status' => $newStatus,
        ]), now()->addHours(2));

        // ✅ Dynamic textarea shrink: remove already-created posts (pair-wise)
        $remainingBlocks = array_slice($blocks, $done);
        $this->data['text'] = $this->blocksToTextarea($remainingBlocks);
        $this->form->fill(['data' => $this->data]);

        if ($newStatus === 'finished') {
            $this->isRunning = false;

            $created = (int) ($state['created'] ?? 0);

            Notification::make()
                ->title('Generation finished')
                ->body("Created {$created} blog posts.")
                ->success()
                ->send();
        }
    }

    public function progress(): array
    {
        if (!$this->batchKey) {
            return ['status' => 'idle', 'done' => 0, 'total' => 0, 'errors' => []];
        }

        $state = Cache::get($this->batchKey);

        if (!is_array($state)) {
            return ['status' => 'idle', 'done' => 0, 'total' => 0, 'errors' => []];
        }

        return [
            'status' => (string) ($state['status'] ?? 'idle'),
            'done' => (int) ($state['done'] ?? 0),
            'total' => (int) ($state['total'] ?? 0),
            'created' => (int) ($state['created'] ?? 0),
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
    }

    // -------------------------
    // Helpers
    // -------------------------

    /**
     * ✅ YOUR REQUIRED FORMAT:
     * Title paragraph
     * (blank line)
     * Content paragraph(s)
     *
     * Next post starts after 2+ blank lines.
     *
     * We parse by paragraphs and group pairs:
     * [title, body, title, body, ...]
     */
    private function normalizedBlocks(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        // split into paragraphs by blank lines
        $paras = preg_split("/\n{2,}/", $text) ?: [];
        $paras = array_values(array_filter(array_map(fn($p) => trim((string) $p), $paras), fn($p) => $p !== ''));

        $blocks = [];

        // pair paragraphs into posts (title + body)
        for ($i = 0; $i < count($paras); $i += 2) {
            $title = $paras[$i] ?? '';
            $body = $paras[$i + 1] ?? '';

            if ($title === '') {
                continue;
            }

            // if body missing, still generate but empty
            $blocks[] = trim($title) . "\n" . trim($body);
        }

        return $blocks;
    }

    private function blocksToTextarea(array $blocks): string
    {
        // Convert internal blocks back to textarea format:
        // Title\nBody  => Title\n\nBody
        $out = [];
        foreach ($blocks as $b) {
            [$t, $c] = $this->splitTitleAndBody((string) $b);

            $t = trim((string) $t);
            $c = trim((string) $c);

            if ($t === '' && $c === '') {
                continue;
            }

            if ($c === '') {
                $out[] = $t;
            } else {
                $out[] = $t . "\n\n" . $c;
            }
        }

        return implode("\n\n\n", $out);
    }

    private function blockCount(string $text): int
    {
        return count($this->normalizedBlocks($text));
    }

    private function splitTitleAndBody(string $block): array
    {
        $block = str_replace(["\r\n", "\r"], "\n", $block);

        $pos = strpos($block, "\n");
        if ($pos === false) {
            return [trim($block), ''];
        }

        $title = trim(substr($block, 0, $pos));
        $body = trim(substr($block, $pos + 1));

        return [$title, $body];
    }

    private function textToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        if ($text === '') {
            return '';
        }

        // Keep paragraphs (blank line = new paragraph)
        $paras = preg_split("/\n{2,}/", $text) ?: [];

        $html = [];
        foreach ($paras as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }

            $escaped = e($p);
            $escaped = nl2br($escaped, false);

            $html[] = "<p>{$escaped}</p>";
        }

        return implode("\n", $html);
    }

    private function uniqueSlugForPostsWithLimit(string $base, int $limit = 191): string
    {
        $base = trim($base);
        if ($base === '') {
            $base = 'item';
        }

        $slug = Str::limit($base, $limit, '');
        $i = 2;

        while (Post::query()->where('slug', $slug)->exists()) {
            $suffix = '-' . $i;
            $trimmedBase = Str::limit($base, $limit - strlen($suffix), '');
            $slug = $trimmedBase . $suffix;
            $i++;
        }

        return $slug;
    }

    private function loadCategoryOptionsWithCounts(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'blog_category')->value('id');
        if (!$taxonomyId) {
            return [];
        }

        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $counts = $this->termBlogPostCounts($taxonomyId);

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

    private function termBlogPostCounts(int $taxonomyId): array
    {
        $rows = DB::table('termables')
            ->join('terms', 'terms.id', '=', 'termables.term_id')
            ->join('posts', 'posts.id', '=', 'termables.termable_id')
            ->where('terms.taxonomy_id', $taxonomyId)
            ->where('termables.termable_type', '=', \App\Models\Post::class)
            ->where('posts.type', '=', 'blog_post')
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