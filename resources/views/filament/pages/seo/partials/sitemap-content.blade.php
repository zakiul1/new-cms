@php
    $state = value($data) ?? [];
    $includePosts = (bool) ($state['include_posts'] ?? true);
    $includePages = (bool) ($state['include_pages'] ?? true);
    $includeMedia = (bool) ($state['include_media'] ?? false);
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="data.include_posts" class="fi-checkbox-input">
            <span class="text-sm font-medium">Posts</span>
        </label>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="data.include_pages" class="fi-checkbox-input">
            <span class="text-sm font-medium">Pages</span>
        </label>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="data.include_media" class="fi-checkbox-input">
            <span class="text-sm font-medium">Media</span>
        </label>
    </div>

    <div class="rounded-xl border bg-white p-3 text-sm text-gray-600">
        ✅ Category selection is automatic: when Posts/Media is enabled, sitemap includes <b>ALL PUBLIC</b> categories
        (private categories never appear).
    </div>

    <div class="text-xs text-gray-500">
        Current: Posts={{ $includePosts ? 'ON' : 'OFF' }},
        Pages={{ $includePages ? 'ON' : 'OFF' }},
        Media={{ $includeMedia ? 'ON' : 'OFF' }}
    </div>
</div>
