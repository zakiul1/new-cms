@php
    $state = value($data) ?? [];

    /** @var array<string,string> $contentTypes */
    $contentTypes = value($contentTypes) ?? []; // ['post'=>'Posts','page'=>'Pages','siatex-tags'=>'Siatex Tags', ...]

    // Selected types array
    $selectedTypes = $state['include_types'] ?? [];
    if (!is_array($selectedTypes)) {
        $selectedTypes = [];
    }

    $includeMedia = (bool) ($state['include_media'] ?? false);

    // Status text
    $selectedLabels = [];
    foreach ($selectedTypes as $t) {
        $t = (string) $t;
        if (isset($contentTypes[$t])) {
            $selectedLabels[] = $contentTypes[$t];
        } else {
            // fallback
            $selectedLabels[] = ucwords(str_replace(['-', '_'], ' ', $t));
        }
    }
@endphp

<div class="space-y-4">
    {{-- Dynamic content types --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @forelse($contentTypes as $type => $label)
            <label class="flex items-center gap-2">
                <input type="checkbox" value="{{ $type }}" wire:model.live="data.include_types"
                    class="fi-checkbox-input">
                <span class="text-sm font-medium">{{ $label }}</span>
            </label>
        @empty
            <div class="text-sm text-gray-500">
                No content types found.
            </div>
        @endforelse

        {{-- Media stays separate --}}
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="data.include_media" class="fi-checkbox-input">
            <span class="text-sm font-medium">Media</span>
        </label>
    </div>

    <div class="rounded-xl border bg-white p-3 text-sm text-gray-600">
        ✅ Only <b>PUBLIC</b> content appears in sitemap automatically (private content never appears).
    </div>

    <div class="text-xs text-gray-500">
        Current:
        Types={{ count($selectedLabels) ? implode(', ', $selectedLabels) : 'None' }},
        Media={{ $includeMedia ? 'ON' : 'OFF' }}
    </div>
</div>
