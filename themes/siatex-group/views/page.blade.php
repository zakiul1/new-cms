@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */
        $title = (string) ($post->title ?? '');
    @endphp

    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-12">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 md:text-5xl">
                {{ $title }}
            </h1>
        </div>
    </section>
@endsection
