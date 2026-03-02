@props([
    'files' => [],
    'selected' => null,
])

<div class="mt-3 space-y-2">
    @if (!count($files))
        <div class="text-sm text-gray-500">No CSV files uploaded yet.</div>
    @else
        <div class="border rounded-lg overflow-hidden">
            @foreach ($files as $file)
                <div
                    class="flex items-center justify-between px-3 py-2 border-b last:border-b-0
                        {{ $selected === $file ? 'bg-gray-50' : '' }}">
                    <button type="button" class="text-sm font-medium text-gray-900 hover:underline truncate"
                        style="max-width: 220px;" wire:click="selectCsv('{{ $file }}')">
                        {{ $file }}
                    </button>

                    <div class="flex gap-2">
                        <button type="button" class="fi-btn fi-btn-color-gray fi-size-xs"
                            wire:click="downloadCsv('{{ $file }}')">
                            Download
                        </button>

                        <button type="button" class="fi-btn fi-btn-color-danger fi-size-xs"
                            wire:click="deleteCsv('{{ $file }}')" wire:confirm="Delete this file?">
                            Delete
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
