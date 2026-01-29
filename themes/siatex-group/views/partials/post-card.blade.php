@php
    /** @var \App\Models\Post $post */

    // Featured image (assuming meta.featured_media_id or similar)
    $meta = is_array($post->meta ?? null) ? $post->meta : [];
    $featuredId = (int) ($meta['featured_media_id'] ?? 0);

    $media = $featuredId ? \App\Models\Media::query()->whereKey($featuredId)->first() : null;
    $img = $media?->thumbUrl() ?: $media?->url();

    $title = (string) ($post->title ?? '');
    $excerpt = (string) ($post->excerpt ?? '');
    if ($excerpt === '') {
        $plain = trim(strip_tags((string) ($post->content ?? '')));
        $excerpt = \Illuminate\Support\Str::words($plain, 18);
    }

    $url = url('/posts/' . $post->slug);
@endphp

<article class="siatex-card">
    <a class="siatex-card__img" href="{{ $url }}">
        @if ($img)
            <img src="{{ $img }}" alt="{{ e($title) }}">
        @else
            <div class="siatex-card__imgFallback"></div>
        @endif
    </a>

    <div class="siatex-card__body">
        <a href="{{ $url }}" class="siatex-card__title">
            {{ $title }}
        </a>

        <div class="siatex-card__excerpt">
            {{ $excerpt }}
        </div>

        <a href="{{ $url }}" class="siatex-card__more">Read More</a>
    </div>
</article>
