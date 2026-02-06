@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $title = (string) ($post->title ?? '');
        $media = $post->featuredMedia;

        // ✅ Do NOT build Filament URLs in Blade (avoid panel context issues / wrong resource 404).
        // Controller should pass $adminEditUrl. If missing, fallback to dashboard only.
        $adminEditUrl = $adminEditUrl ?? url('/lara-admin');

        $category = null;
        try {
            $category = $post->categories()->first();
        } catch (\Throwable $e) {
            $category = null;
        }

        // -----------------------------
        // ✅ HERO CONTENT (under title)
        // Priority:
        // 1) content_json['html'] (WpClassicEditor / content_html virtual)
        // 2) BlockRenderer render(content_json) (if you still store blocks)
        // -----------------------------

        $allowedHtml = '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a><h1><h2><h3><h4><h5><h6>';

        $rawHtml = '';

        // 1) Preferred: WpClassicEditor stored HTML inside content_json['html']
        $htmlFromJson = data_get($post->content_json ?? [], 'html');
        if (is_string($htmlFromJson) && trim($htmlFromJson) !== '') {
            $rawHtml = $htmlFromJson;
        }

        // 2) Fallback: render blocks if not using html key
        if (trim($rawHtml) === '') {
            try {
                $rawHtml = (string) app(\App\Cms\Content\Blocks\BlockRenderer::class)->render(
                    $post->content_json ?? [],
                );
            } catch (\Throwable $e) {
                $rawHtml = '';
            }
        }

        // Sanitize for safe frontend output
        $heroHtml = trim($rawHtml) !== '' ? strip_tags($rawHtml, $allowedHtml) : '';
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>

            @if ($category)
                <a class="text-slate-600 hover:underline" href="{{ cms_term_url($category) }}">
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
            <div class="grid gap-8 lg:grid-cols-2">
                <div>
                    <div class="h-1 w-20 bg-red-500"></div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">
                        Your Tech-pack, Our production
                    </div>

                    <h1 class="mt-3 text-4xl font-extrabold leading-tight tracking-tight text-slate-900">
                        {{ $title }}
                    </h1>

                    {{-- ✅ SHOW POST CONTENT UNDER TITLE (HTML from editor) --}}
                    @if ($heroHtml !== '')
                        <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-slate-700">
                            {!! $heroHtml !!}
                        </div>
                    @endif

                    <a href="#"
                        class="mt-6 inline-flex items-center rounded bg-[#1f5f99] px-5 py-3 text-sm font-semibold text-white hover:bg-[#194f7f]">
                        Get Price
                    </a>
                </div>

                <div class="p-4">
                    @if ($media && method_exists($media, 'isImage') && $media->isImage())
                        {!! cms_picture(
                            $media,
                            [
                                'alt' => e($title),
                                'class' => 'w-full object-cover',
                                'sizes' => '(max-width: 1024px) 100vw, 560px',
                                'loading' => 'eager',
                                'decoding' => 'async',
                            ],
                            'large',
                            ['medium', 'medium_large', 'large'],
                        ) !!}
                    @else
                        <div class="h-80 w-full rounded-lg bg-slate-100"></div>
                    @endif
                </div>
            </div>
        </div>
    </section>



    {{-- ✅ Custom JSON (per post) for frontend + optional JSON-LD --}}
    @php
        $customJsonRaw =
            data_get($post->meta_json ?? [], 'custom_json', null) ?:
            data_get($post->meta_json ?? [], 'seo.custom_json', null);

        $customJson = null;

        if (is_array($customJsonRaw)) {
            $customJson = $customJsonRaw;
        } elseif (is_string($customJsonRaw)) {
            $str = trim($customJsonRaw);

            if ($str !== '') {
                $openTag = '<' . 'script';
                $closeTag = '</' . 'script' . '>';

                $openPos = stripos($str, $openTag);
                if ($openPos !== false) {
                    $gtPos = strpos($str, '>', $openPos);
                    if ($gtPos !== false) {
                        $endPos = stripos($str, $closeTag, $gtPos + 1);
                        if ($endPos !== false) {
                            $str = substr($str, $gtPos + 1, $endPos - ($gtPos + 1));
                            $str = trim((string) $str);
                        }
                    }
                }

                $decoded = json_decode($str, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $customJson = $decoded;
                }
            }
        }

        $isJsonLd = is_array($customJson) && (isset($customJson['@context']) || isset($customJson['@type']));
    @endphp
@endsection
