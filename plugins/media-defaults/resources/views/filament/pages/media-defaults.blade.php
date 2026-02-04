<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border bg-white p-4">
            <div class="text-sm text-gray-600">
                These defaults are applied when you open <b>Edit Media</b>.
                Only empty fields are auto-filled (existing values are never overwritten).
            </div>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
