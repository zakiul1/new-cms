@php
    /** @var \Illuminate\Support\Collection|\App\Models\Post[] $posts */
@endphp

<div class="my-10">
    <div class="grid grid-cols-2 gap-8 lg:grid-cols-4">
        @foreach ($posts as $post)
            @php
                $title = trim((string) ($post->title ?? '')) ?: 'Untitled';
                $url = function_exists('cms_post_url')
                    ? cms_post_url($post)
                    : (function_exists('cms_slug_url')
                        ? cms_slug_url((string) ($post->slug ?? ''))
                        : url('/' . trim((string) ($post->slug ?? ''), '/') . '/'));
            @endphp

            <a href="{{ $url }}" class="group block text-center">
                <div class="mx-auto w-full max-w-[240px]">
                    @if (!empty($post->featuredMedia))
                        <div class="aspect-square overflow-hidden  bg-slate-50">
                            {!! cms_picture(
                                $post->featuredMedia,
                                [
                                    'alt' => e($title),
                                    'class' => 'h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.03]',
                                    'loading' => 'lazy',
                                ],
                                'medium',
                            ) !!}
                        </div>
                    @else
                        <div class="aspect-square  bg-slate-100"></div>
                    @endif
                </div>

                <h3 class="mt-4 text-sm font-semibold leading-snug text-[#1f5f99]">
                    {{ $title }}
                </h3>
            </a>
        @endforeach
    </div>
</div>
