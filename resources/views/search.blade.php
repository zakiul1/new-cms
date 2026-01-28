@extends('layout')

@section('title', $q ? "Search: {$q}" : 'Search')

@section('content')
    <div class="cms-container" style="padding: 24px 16px;">
        <h1 style="font-size: 22px; font-weight: 800; margin-bottom: 10px;">Search</h1>

        <form method="GET" action="{{ route('cms.search') }}" style="display:flex; gap:10px; margin-bottom:16px;">
            <input name="q" value="{{ $q }}" placeholder="Search posts & pages..."
                style="flex:1; padding:10px 12px; border:1px solid rgba(0,0,0,.12); border-radius:10px;">
            <select name="type" style="padding:10px 12px; border:1px solid rgba(0,0,0,.12); border-radius:10px;">
                <option value="">All</option>
                <option value="page" @selected($type === 'page')>Pages</option>
                <option value="post" @selected($type === 'post')>Posts</option>
            </select>
            <button style="padding:10px 14px; border-radius:10px; border:1px solid rgba(0,0,0,.12);">
                Search
            </button>
        </form>

        @if (trim($q) === '')
            <div style="opacity:.7;">Type something to search.</div>
        @else
            <div style="opacity:.7; margin-bottom:14px;">
                {{ $total }} result(s) for <strong>{{ $q }}</strong>
            </div>

            <div style="display:flex; flex-direction:column; gap:12px;">
                @forelse($items as $doc)
                    <article style="padding:14px; border:1px solid rgba(0,0,0,.08); border-radius:14px;">
                        <div style="font-size:12px; opacity:.7; margin-bottom:6px;">
                            {{ strtoupper($doc->entity_type) }}
                            @if ($doc->published_at)
                                • {{ $doc->published_at->format('M d, Y') }}
                            @endif
                        </div>
                        <a href="{{ $doc->url }}" style="font-weight:800; font-size:16px;">
                            {{ $doc->title }}
                        </a>
                        @if ($doc->content)
                            <div style="margin-top:8px; opacity:.85;">
                                {{ \Illuminate\Support\Str::limit(strip_tags($doc->content), 180) }}
                            </div>
                        @endif
                    </article>
                @empty
                    <div style="opacity:.7;">No results found.</div>
                @endforelse
            </div>

            @php
                $totalPages = (int) ceil($total / $perPage);
            @endphp

            @if ($totalPages > 1)
                <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
                    @for ($i = 1; $i <= $totalPages; $i++)
                        <a href="{{ route('cms.search', ['q' => $q, 'type' => $type, 'page' => $i]) }}"
                            style="padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.12);
                                  {{ $i === $page ? 'font-weight:800;' : '' }}">
                            {{ $i }}
                        </a>
                    @endfor
                </div>
            @endif
        @endif
    </div>
@endsection
