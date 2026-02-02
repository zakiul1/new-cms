@php
    /** @var \App\Models\Post $post */

    $media = $post->featuredMedia;

    $title = (string) ($post->title ?? '');

    $excerpt = (string) ($post->excerpt ?? '');
    if ($excerpt === '') {
        $plain = trim(strip_tags((string) ($post->content ?? '')));
        $excerpt = \Illuminate\Support\Str::words($plain, 28);
    }
    $excerpt = \Illuminate\Support\Str::words($excerpt, 22, '…');

    $url = cms_post_url($post);

@endphp

<article class="flex gap-6 py-6">
    <a href="{{ $url }}" class="block h-28 w-28 shrink-0 overflow-hidden rounded bg-slate-100"
        aria-label="{{ e($title) }}">
        @if ($media && $media->isImage())
            {!! cms_picture(
                $media,
                [
                    'alt' => e($title),
                    'class' => 'h-full w-full object-cover',
                    'sizes' => '112px',
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ],
                'thumb',
                ['thumb', 'medium', 'medium_large'],
            ) !!}
        @else
            <div class="h-full w-full bg-slate-200"></div>
        @endif
    </a>

    <div class="min-w-0">
        <a href="{{ $url }}" class="block text-[18px] font-semibold leading-snug text-[#1f5f99] hover:underline">
            {{ $title }}
        </a>

        <div class="mt-2 text-sm leading-7 text-slate-600">
            {{-- safe truncation without line-clamp plugin --}}
            <div class="max-h-[5.25rem] overflow-hidden">
                {{ $excerpt }}
            </div>
        </div>

        <a href="{{ $url }}"
            class="mt-2 inline-flex items-center gap-2 text-sm text-slate-700 hover:text-slate-900">
            <span>Read More</span>
            <span
                class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-200 text-slate-600">›</span>
        </a>
    </div>
</article>
