@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Media $record */
    $record = $getRecord();

    $title = (string) ($record->title ?: ($record->original_filename ?: 'Media #' . $record->id));
    $title = trim($title) !== '' ? trim($title) : 'Media #' . $record->id;

    // ✅ Prefer thumb, then url
    $thumb = $record->thumbUrl('jpeg') ?: $record->thumbUrl() ?: $record->url();

    $sizeKb = number_format(((int) $record->size) / 1024, 1);
    $timeText = optional($record->created_at)->diffForHumans() ?: '';

    $type = (string) ($record->mime_type ?: 'application/octet-stream');
    $typeLabel = strtoupper((string) strtok($type, '/'));

    // ✅ Edit + frontend view
    $editUrl = \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $record]);
    $viewUrl = filled($record->slug)
        ? (function_exists('cms_slug_url')
            ? cms_slug_url((string) $record->slug)
            : url('/' . trim((string) $record->slug, '/') . '/'))
        : $record->url();
@endphp

@once
    <style>
        :root {
            --mlb-gap: 14px;
            --mlb-meta: 54px;
            /* fixed meta height */
        }

        /* ✅ Only style inside the card. DO NOT override .fi-ta-content-grid here. */

        .mlb-tile {
            width: 100%;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .10);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* ✅ Perfect square image area */
        .mlb-media {
            position: relative;
            aspect-ratio: 1 / 1;
            background: #f1f5f9;
            overflow: hidden;
        }

        .mlb-media img {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            object-fit: cover !important;
        }

        /* ✅ Meta area fixed height */
        .mlb-meta {
            height: var(--mlb-meta);
            border-top: 1px solid rgba(15, 23, 42, .06);
            padding: 10px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .mlb-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .mlb-row {
            margin-top: 2px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 11px;
            color: #64748b;
            min-width: 0;
            line-height: 1.2;
        }

        .mlb-row .left {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mlb-row .right {
            white-space: nowrap;
        }

        .mlb-badge {
            position: absolute;
            left: 8px;
            top: 8px;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 6px;
            background: rgba(255, 255, 255, .92);
            color: #334155;
        }

        .mlb-actions {
            position: absolute;
            right: 8px;
            top: 8px;
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity .15s ease;
        }

        .mlb-tile:hover .mlb-actions {
            opacity: 1;
        }

        .mlb-action {
            font-size: 11px;
            font-weight: 600;
            padding: 6px 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, .95);
            color: #0f172a;
            text-decoration: none;
        }

        .mlb-action:hover {
            text-decoration: underline;
        }
    </style>
@endonce

{{-- ✅ Click card => Edit (WP-like) --}}
<a href="{{ $editUrl }}" class="mlb-tile">
    <div class="mlb-media">
        <img src="{{ $thumb }}" alt="{{ e($title) }}" loading="lazy" decoding="async">
        <div class="mlb-badge">{{ $typeLabel }}</div>

        {{-- ✅ actions: stop click so it doesn't open edit --}}
        <div class="mlb-actions" onclick="event.stopPropagation();">
            <a href="{{ $editUrl }}" class="mlb-action">Edit</a>
            <a href="{{ $viewUrl }}" target="_blank" rel="noopener noreferrer" class="mlb-action">View</a>
        </div>
    </div>

    <div class="mlb-meta">
        <div class="mlb-title" title="{{ e($title) }}">{{ Str::limit($title, 40) }}</div>
        <div class="mlb-row">
            <span class="left">{{ $timeText }}</span>
            <span class="right">{{ $sizeKb }} KB</span>
        </div>
    </div>
</a>
