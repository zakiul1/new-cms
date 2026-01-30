@extends('layouts.app')

@section('content')
    @php
        $settings = app(\App\Cms\Core\Settings::class);

        $homePageId = (int) $settings->get('homepage_page_id', 0, 'core');
        $homePage = $homePageId ? \App\Models\Post::query()->whereKey($homePageId)->first() : null;

        $posts = \App\Models\Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->latest('id')
            ->limit(18)
            ->get();

        $sliderHtml = app(\App\Cms\Hooks\Hooks::class)->applyFilters('siatex.slider.html', '', 'home-hero');
    @endphp

    {{-- Hero --}}
  
       {!! slider_render('dfd') !!}
   

    {{-- Intro page content (optional) --}}
    @if ($homePage)
        <section class="bg-white">
            <div class="cms-container mx-auto px-4 py-10">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $homePage->title }}</h2>

                <div class="prose prose-slate mt-4 max-w-none">
                    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters(
                        \App\Cms\Hooks\HookPoints::CMS_THE_CONTENT,
                        (string) ($homePage->content ?? ''),
                        ['post' => $homePage],
                    ) !!}
                </div>
            </div>
        </section>
    @endif

    {{-- Posts list --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-6">
            <div class="grid gap-x-12 divide-y divide-slate-200 md:grid-cols-2 md:divide-y-0">
                @foreach ($posts as $post)
                    <div class="border-b border-slate-200 md:border-b-0">
                        @include('partials.post-card', ['post' => $post])
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
