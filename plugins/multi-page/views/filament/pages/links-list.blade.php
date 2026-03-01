<x-filament-panels::page>
    @if (!$page)
        <div class="rounded-xl border p-4">Page not found.</div>
    @else
        <div class="rounded-xl border p-4 mb-4">
            <div class="font-semibold">{{ $page->title }}</div>
            <div class="text-sm text-gray-600">Generated Links: {{ count($links) }}</div>
        </div>

        <div class="rounded-xl border p-4">
            @if (empty($links))
                <div class="text-sm text-gray-600">No links yet. Click “Generate” from the page editor.</div>
            @else
                <ul class="list-disc pl-6 space-y-1">
                    @foreach ($links as $link)
                        <li>
                            <a class="text-primary-600 underline" href="{{ url($link) }}" target="_blank">
                                {{ $link }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-filament-panels::page>
