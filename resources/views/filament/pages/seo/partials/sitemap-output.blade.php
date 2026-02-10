@php
    $state = value($data) ?? [];
@endphp

<div class="space-y-4">
    <div>
        <label class="fi-fo-field-wrp-label text-sm font-medium">Directory</label>
        <input type="text" wire:model.live="data.directory" placeholder="" class="fi-input w-full">
        <div class="text-xs text-gray-500 mt-1">
            If empty, files go to public disk root. Example: <code>sitemaps</code>
        </div>
    </div>

    <div>
        <label class="fi-fo-field-wrp-label text-sm font-medium">Max</label>
        <input type="number" min="100" wire:model.live="data.max_links" class="fi-input w-full">
        <div class="text-xs text-gray-500 mt-1">
            Maximum links per XML file (auto split if more).
        </div>
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" wire:model.live="data.show_in_robots" class="fi-checkbox-input">
        <span class="text-sm font-medium">Show sitemap in robots.txt</span>
    </label>
</div>
