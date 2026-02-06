@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */
        $title = (string) ($post->title ?? '');

        // ✅ Do NOT override if controller already provided it
        if (!isset($adminEditUrl)) {
            $panelId = 'admin';

            // Use correct resource depending on type
            if (($post->type ?? 'post') === 'page' && class_exists(\App\Filament\Resources\Pages\PageResource::class)) {
                $adminEditUrl = \App\Filament\Resources\Pages\PageResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: $panelId,
                );
            } elseif (class_exists(\App\Filament\Resources\Posts\PostResource::class)) {
                $adminEditUrl = \App\Filament\Resources\Posts\PostResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: $panelId,
                );
            } else {
                $adminEditUrl = url('/lara-admin');
            }
        }
    @endphp

    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-12">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 md:text-5xl">
                {{ $title }}
            </h1>
        </div>
    </section>
@endsection
