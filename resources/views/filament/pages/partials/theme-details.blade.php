@php
    $m = $theme['manifest'] ?? [];
    $assets = $m['assets'] ?? [];
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500">Name</div>
            <div class="font-semibold">{{ $m['name'] ?? $slug }}</div>
        </div>

        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500">Version</div>
            <div class="font-semibold">{{ $m['version'] ?? '' }}</div>
        </div>

        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500">Author</div>
            <div class="font-semibold">{{ $m['author'] ?? '—' }}</div>
        </div>

        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500">Parent</div>
            <div class="font-semibold">{{ $m['parent'] ?? '—' }}</div>
        </div>
    </div>

    <div class="rounded-lg border p-3">
        <div class="text-xs text-gray-500 mb-2">Templates</div>
        <pre class="text-xs overflow-auto">{{ json_encode($m['templates'] ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>

    <div class="rounded-lg border p-3">
        <div class="text-xs text-gray-500 mb-2">Menus</div>
        <pre class="text-xs overflow-auto">{{ json_encode($m['menus'] ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>

    <div class="rounded-lg border p-3">
        <div class="text-xs text-gray-500 mb-2">Sidebars</div>
        <pre class="text-xs overflow-auto">{{ json_encode($m['sidebars'] ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>

    <div class="rounded-lg border p-3">
        <div class="text-xs text-gray-500 mb-2">Assets</div>
        <pre class="text-xs overflow-auto">{{ json_encode($assets, JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
