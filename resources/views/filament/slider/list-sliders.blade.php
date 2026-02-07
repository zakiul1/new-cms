<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border bg-white p-4 space-y-3">
            <div class="text-sm font-semibold">Sliders</div>

            <div class="space-y-2">
                @foreach ($sliders as $s)
                    <button type="button"
                        class="w-full text-left rounded-lg border px-3 py-2 hover:bg-gray-50 {{ (int)$activeSliderId === (int)$s['id'] ? 'bg-gray-50 border-gray-300' : '' }}"
                        wire:click="$set('activeSliderId', {{ (int) $s['id'] }})"
                    >
                        <div class="font-semibold text-sm">{{ $s['name'] }}</div>
                        <div class="text-xs text-gray-500">Key: {{ $s['key'] }}</div>
                    </button>
                @endforeach
            </div>

            @if ($activeSliderId)
                <div class="text-xs text-gray-500 pt-2">
                    Use in theme:
                    <code class="px-1 py-0.5 bg-gray-100 rounded">{!! '{!! slider_render("' !!}{{ collect($sliders)->firstWhere('id',$activeSliderId)['key'] ?? '' }}{!! '") !!}' !!}</code>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2 rounded-xl border bg-white p-4">
            <div class="text-sm font-semibold mb-3">Slides</div>

            {{-- For now: show message. Next step: we’ll add CRUD UI for slides here --}}
            <div class="text-sm text-gray-600">
                Next step: add “Create Slide”, “Edit”, “Sort order”, and “Select image from Media Library”.
                Tell me if you want:
                <ul class="list-disc ms-5 mt-2 space-y-1">
                    <li>Single slider per page (Hero only)</li>
                    <li>Multiple sliders (home hero + category hero + etc.)</li>
                    <li>Drag & drop sorting like WordPress</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
