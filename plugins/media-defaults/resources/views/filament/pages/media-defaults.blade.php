<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Info --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-sm text-gray-600">
                These defaults are applied when you open <b>Edit Media</b>.
                Only empty fields are auto-filled (existing values are never overwritten).
            </p>
        </div>

        {{-- Form (Category panel is inside the form now) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            {{ $this->form }}
        </div>
    </div>
</x-filament-panels::page>
