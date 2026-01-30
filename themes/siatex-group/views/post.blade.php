@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $title = (string) ($post->title ?? '');
        $excerpt = trim((string) ($post->excerpt ?? ''));
        $media = $post->featuredMedia;

        $category = null;
        try {
            $category = $post->categories()->first();
        } catch (\Throwable $e) {
            $category = null;
        }
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>

            @if ($category)
                <a class="text-slate-600 hover:underline" href="{{ url('/category/' . $category->slug) }}">
                    {{ $category->name }}
                </a>
                <span class="mx-2 text-slate-300">/</span>
            @endif

            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    {{-- Hero --}}
    <section class="bg-slate-50">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid items-center gap-8 lg:grid-cols-2">
                <div>
                    <div class="h-1 w-20 bg-red-500"></div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">
                        Your Tech-pack, Our production
                    </div>

                    <h1 class="mt-3 text-4xl font-extrabold leading-tight tracking-tight text-slate-900">
                        {{ $title }}
                    </h1>

                    @if ($excerpt !== '')
                        <p class="mt-4 text-sm leading-7 text-slate-700">
                            {{ $excerpt }}
                        </p>
                    @endif

                    <a href="#" class="mt-6 inline-flex items-center rounded bg-[#1f5f99] px-5 py-3 text-sm font-semibold text-white hover:bg-[#194f7f]">
                        Get Price
                    </a>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    @if ($media && $media->isImage())
                        {!! cms_picture($media, [
                            'alt' => e($title),
                            'class' => 'w-full rounded-lg object-cover',
                            'sizes' => '(max-width: 1024px) 100vw, 560px',
                            'loading' => 'eager',
                            'decoding' => 'async',
                        ], 'large', ['medium','large']) !!}
                    @else
                        <div class="h-80 w-full rounded-lg bg-slate-100"></div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Content --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="prose prose-slate max-w-none">
                {!! app(\App\Cms\Content\Blocks\BlockRenderer::class)->render($post->content_json ?? []) !!}
            </div>
        </div>
    </section>
@endsection
