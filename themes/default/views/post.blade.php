<h1>{{ $post->title }}</h1>

{!! app(\App\Cms\Content\Blocks\BlockRenderer::class)->render($post->content_json) !!}
