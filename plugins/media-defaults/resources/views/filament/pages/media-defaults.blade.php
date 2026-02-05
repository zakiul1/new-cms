<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Info --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-sm text-gray-600">
                These defaults are applied when you open <b>Edit Media</b>.
                Only empty fields are auto-filled (existing values are never overwritten).
            </p>
        </div>

        {{-- Category Dropdown (PUBLIC only) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Media Category</div>
                    <div class="text-xs text-gray-500">Select a category to set its defaults.</div>
                </div>

                @php
                    $activeName = null;
                    if (!empty($categoryOptions) && isset($activeCategoryId) && $activeCategoryId !== null) {
                        $activeName = $categoryOptions[$activeCategoryId] ?? null;
                    }
                @endphp

                <div class="text-xs text-gray-500">
                    Active:
                    <span class="font-medium text-gray-900">{{ $activeName ?? '—' }}</span>
                </div>
            </div>

            @if (empty($categoryOptions))
                <div class="mt-3 text-sm text-gray-600">
                    No <b>public</b> media categories found.
                    Create media categories or set them as public to configure defaults.
                </div>
            @else
                <div class="mt-4 max-w-md">
                    <label class="mb-1 block text-xs font-medium text-gray-700">
                        Choose category
                    </label>

                    <select wire:model.live="activeCategoryId"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                               outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20">
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>

                    <p class="mt-2 text-xs text-gray-500">
                        If you have many categories and need searching, I’ll convert this to a Filament searchable
                        Select.
                    </p>
                </div>
            @endif
        </div>

        {{-- Form --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            {{ $this->form }}
        </div>
    </div>
</x-filament-panels::page>
