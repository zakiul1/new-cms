@extends('theme::layouts.app')

@section('title', $post->title)

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>

        {!! app(\App\Cms\Content\Blocks\BlockRenderer::class)->render($post->content_json) !!}
    </article>
@endsection
