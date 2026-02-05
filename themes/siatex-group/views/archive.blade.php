{{-- themes/siatex-group/views/archive.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Term $term */
        /** @var \Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection $posts */

        $title = isset($title) && is_string($title) && trim($title) !== '' ? trim($title) : $term->name ?? 'Archive';
        $desc = trim((string) ($term->description ?? ''));

        // ✅ Better empty state label (category / tag / archive)
        $taxonomyKey = null;
        try {
            $taxonomyKey = (string) ($term->taxonomy?->key ?? '');
        } catch (\Throwable $e) {
            $taxonomyKey = null;
        }

        $label = match ($taxonomyKey) {
            'category' => 'category',
            'tag' => 'tag',
            'media_category' => 'media category',
            default => 'archive',
        };

        // ✅ Pagination-safe count check
        $count = method_exists($posts, 'total') ? (int) $posts->total() : (int) $posts->count();
    @endphp

    <div class="cms-container mx-auto px-4 py-8">

        {{-- Breadcrumb --}}
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-700">{{ $title }}</span>
        </nav>

        <header class="mt-5">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                {{ $title }}
            </h1>

            @if ($desc !== '')
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ $desc }}
                </p>
            @endif
        </header>

        {{-- Posts Grid --}}
        @if ($count === 0)
            <div class="mt-10 rounded bg-slate-50 p-6 text-sm text-slate-600">
                No posts found in this {{ $label }}.
            </div>
        @else
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($posts as $post)
                    @php
                        /** @var \App\Models\Post $post */
                        $postUrl = cms_post_url($post);
                        $postTitle = trim((string) ($post->title ?? ''));
                        $postTitle = $postTitle !== '' ? $postTitle : 'Untitled';
                        $excerpt = trim(strip_tags((string) ($post->excerpt ?? '')));
                    @endphp

                    <a href="{{ $postUrl }}"
                        class="group block rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-300">
                        {{-- Featured image (if exists) --}}
                        @if (!empty($post->featuredMedia))
                            <div class="mb-3 aspect-[16/10] overflow-hidden rounded-xl bg-slate-50">
                                {!! cms_picture(
                                    $post->featuredMedia,
                                    [
                                        'alt' => e($postTitle),
                                        'class' => 'h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]',
                                        'loading' => 'lazy',
                                        'decoding' => 'async',
                                    ],
                                    'medium',
                                    ['thumb', 'medium', 'medium_large'],
                                ) !!}
                            </div>
                        @endif

                        <h2 class="text-base font-semibold leading-snug text-slate-900 line-clamp-2">
                            {{ $postTitle }}
                        </h2>

                        @if ($excerpt !== '')
                            <p class="mt-2 text-sm leading-6 text-slate-600 line-clamp-3">
                                {{ $excerpt }}
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>

            {{-- Pagination (only if paginator) --}}
            @if (method_exists($posts, 'links'))
                <div class="mt-10">
                    {{ $posts->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
