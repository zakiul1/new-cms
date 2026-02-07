@extends('theme::layouts.app')

@section('title', $term->name)

@section('content')
    <div class="cms-container" style="padding: 24px 0;">
        <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 6px;">
            {{ $term->name }}
        </h1>

        @if (!empty($term->description))
            <p style="opacity:.8; margin-bottom: 18px;">{{ $term->description }}</p>
        @endif

        @if ($isProductCategory)
            <p style="margin-bottom: 18px; font-weight: 600;">Showing products</p>
        @endif

        @if ($posts->count() === 0)
            <p>No items found.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
                @foreach ($posts as $post)
                    <a href="{{ cms_post_url($post) }}" style="display:block;text-decoration:none;color:inherit;">
                        <div style="border:1px solid rgba(0,0,0,.08);border-radius:12px;padding:14px;">
                            <div style="font-weight:700;margin-bottom:6px;">
                                {{ $post->title }}
                            </div>
                            @if (!empty($post->excerpt))
                                <div style="opacity:.75;font-size:14px;">
                                    {{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}
                                </div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div style="margin-top: 20px;">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
@endsection
