@extends('layouts.app')

@section('content')
    @php
        $settings = app(\App\Cms\Core\Settings::class);

        // WP-like homepage page content
        $homePageId = (int) $settings->get('homepage_page_id', 0, 'core');
        $homePage = $homePageId ? \App\Models\Post::query()->whereKey($homePageId)->first() : null;

        // latest posts
        $posts = \App\Models\Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->latest('id')
            ->limit(18)
            ->get();

        // Slider render hook (plugin later)
        $sliderHtml = app(\App\Cms\Hooks\Hooks::class)->applyFilters('siatex.slider.html', '', 'home-hero');
    @endphp

    {{-- HERO --}}
    <section class="siatex-hero">
        <div class="cms-container">
            <div class="siatex-hero__inner">

                {{-- ✅ Plugin slider area --}}
                @if (trim($sliderHtml) !== '')
                    {!! $sliderHtml !!}
                @else
                    {{-- Fallback until plugin is ready --}}
                    <div class="siatex-hero__fallback">
                        <div class="siatex-hero__fallbackText">
                            <h1>Your Reliable Partner in Garment Manufacturing</h1>
                            <p>Slider plugin not installed yet. This area will be dynamic from “Siatex Sliders”.</p>
                        </div>
                        <div class="siatex-hero__fallbackBox">Hero Slider Placeholder</div>
                    </div>
                @endif

            </div>
        </div>
    </section>

    {{-- INTRO (from selected homepage page) --}}
    @if ($homePage)
        <section class="siatex-intro">
            <div class="cms-container">
                <h2 class="siatex-intro__title">{{ $homePage->title }}</h2>

                <div class="siatex-intro__content">
                    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters(
                        \App\Cms\Hooks\HookPoints::CMS_THE_CONTENT,
                        (string) ($homePage->content ?? ''),
                        ['post' => $homePage],
                    ) !!}
                </div>
            </div>
        </section>
    @endif

    {{-- POSTS GRID --}}
    <section class="siatex-posts">
        <div class="cms-container">
            <div class="siatex-posts__grid">
                @foreach ($posts as $post)
                    @include('partials.post-card', ['post' => $post])
                @endforeach
            </div>
        </div>
    </section>
@endsection
