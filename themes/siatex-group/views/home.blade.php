@extends('layouts.app')

@section('content')
    @php
        $settings = app(\App\Cms\Core\SettingsRepository::class);
        $hooks = app(\App\Cms\Hooks\Hooks::class);
        $now = now();

        // ✅ Selected "Homepage Page" (must be a published PAGE)
        // IMPORTANT: use SettingsRepository so it matches Filament ManageCmsSettings save()
        $homePageId = (int) $settings->get('core', 'homepage_page_id', 0);

        $homePage = $homePageId
            ? \App\Models\Post::query()
                ->whereKey($homePageId)
                ->where('type', 'page')
                ->where('status', 'published')
                ->where(function ($q) use ($now) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                })
                ->first()
            : null;

        // ✅ Latest posts (published + scheduled-safe)
        $posts = \App\Models\Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->latest('id')
            ->limit(18)
            ->get();
    @endphp

    {{-- ✅ Hero --}}
    {!! slider_render('home-hero') !!}

    {{-- ✅ Intro page content (optional) --}}
    @if ($homePage)
        <section class="bg-white">
            <div class="cms-container mx-auto px-4 py-10">


                <div class="prose prose-slate mt-4 max-w-none">
                    {!! $hooks->applyFilters(\App\Cms\Hooks\HookPoints::CMS_THE_CONTENT, (string) ($homePage->content ?? ''), [
                        'post' => $homePage,
                    ]) !!}
                </div>
            </div>
        </section>
    @endif

    {{-- ✅ Posts list --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-6">
            @if ($posts->isEmpty())
                <div class="rounded bg-slate-50 p-6 text-sm text-slate-600">
                    No posts found.
                </div>
            @else
                <div class="grid gap-x-12 divide-y divide-slate-200 md:grid-cols-2 md:divide-y-0">
                    @foreach ($posts as $post)
                        <div class="border-b border-slate-200 md:border-b-0">
                            @include('partials.post-card', ['post' => $post])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
