@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Media $record */
    $record = $getRecord();

    $title = (string) ($record->title ?: ($record->original_filename ?: 'Media #' . $record->id));
    $thumb = $record->thumbUrl('jpeg') ?: $record->thumbUrl() ?: $record->url();

    $sizeKb = number_format(((int) $record->size) / 1024, 1);
    $timeText = optional($record->created_at)->diffForHumans() ?: '';

    $type = (string) ($record->mime_type ?: 'application/octet-stream');
    $typeLabel = strtoupper((string) strtok($type, '/'));

    $editUrl = \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $record]);
@endphp

@once
    <style>
        :root {
            --mlb-gap: 14px;
            --mlb-tile: 175px;
            /* ✅ set 160/180/200 you like */
            --mlb-meta: 54px;
            /* ✅ fixed meta height */
        }

        /* ✅ Filament table "grid" -> flex wrap */
        .fi-ta-content-grid {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: var(--mlb-gap) !important;
            align-items: flex-start !important;
        }

        .fi-ta-content-grid>.fi-ta-record {
            flex: 0 0 auto !important;
            width: var(--mlb-tile) !important;
            min-width: 0 !important;
        }

        .fi-ta-record-content-ctn,
        .fi-ta-record-content {
            width: 100% !important;
            min-width: 0 !important;
        }

        /* ✅ EXACT same card size always */
        .mlb-tile {
            width: 100%;
            height: var(--mlb-tile);
            /* ✅ makes it square by height too */
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .10);
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        /* ✅ Image area always same height */
        .mlb-media {
            position: relative;
            height: calc(var(--mlb-tile) - var(--mlb-meta));
            /* square - meta */
            background: #f1f5f9;
            overflow: hidden;
        }

        .mlb-media img {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            object-fit: cover !important;
        }

        /* ✅ Meta area always fixed height */
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
    </style>
@endonce

<div class="mlb-tile" wire:click="callTableAction('preview', {{ (int) $record->id }})">
    <div class="mlb-media">
        <img src="{{ $thumb }}" alt="{{ e($title) }}" loading="lazy">
        <div class="mlb-badge">{{ $typeLabel }}</div>

        <div class="mlb-actions" wire:click.stop>
            <a href="{{ $editUrl }}" class="mlb-action">Edit</a>
            <a href="{{ $record->url() }}" target="_blank" rel="noopener" class="mlb-action">View</a>
        </div>
    </div>

    <div class="mlb-meta">
        <div class="mlb-title" title="{{ $title }}">{{ Str::limit($title, 40) }}</div>
        <div class="mlb-row">
            <span class="left">{{ $timeText }}</span>
            <span class="right">{{ $sizeKb }} KB</span>
        </div>
    </div>
</div>
