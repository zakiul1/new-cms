<x-filament-panels::page>
    @php($p = $this->progress())
    @php($total = (int) ($p['total'] ?? 0))
    @php($done = (int) ($p['done'] ?? 0))
    @php($status = (string) ($p['status'] ?? 'idle'))
    @php($errors = is_array($p['errors'] ?? null) ? $p['errors'] : [])
    @php($percent = $total > 0 ? min(100, (int) round(($done / $total) * 100)) : 0)

    {{-- ✅ TOP Progress / Status --}}
    <div class="space-y-6 mb-6" @if ($this->isRunning) wire:poll.1200ms="tickRename" @endif>
        <x-filament::section>
            <div class="flex items-start justify-between gap-4">
                <div class="text-sm">
                    <div><strong>Status:</strong> {{ $status }}</div>
                    <div><strong>Progress:</strong> {{ $done }} / {{ $total }}</div>
                </div>

                @if ($this->isRunning)
                    <div class="text-xs text-gray-500">
                        Renaming in progress…
                    </div>
                @endif
            </div>

            @if ($total > 0)
                <div class="mt-3 w-full bg-gray-200 rounded">
                    <div class="bg-primary-600 text-xs leading-none py-1 text-center text-white rounded"
                        style="width: {{ $percent }}%">
                        {{ $percent }}%
                    </div>
                </div>
            @endif

            @if (!empty($errors))
                <div class="mt-4">
                    <div class="font-semibold text-danger-600">Errors:</div>
                    <ul class="list-disc ml-6 text-sm">
                        @foreach ($errors as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Two-column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- LEFT: Names textarea --}}
        <x-filament::section class="lg:col-span-2">
            <div class="space-y-4">
                {{-- Header line with Filter button --}}
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">
                        New names (one per line)
                    </div>

                    <x-filament::button size="sm" color="info" wire:click="filterNames"
                        wire:loading.attr="disabled" wire:target="filterNames">
                        <span wire:loading.remove wire:target="filterNames">Filter</span>
                        <span wire:loading wire:target="filterNames">Filtering…</span>
                    </x-filament::button>
                </div>

                {{-- Render ONLY the textarea field --}}
                {{ $this->form->getComponent('data.names') }}
            </div>
        </x-filament::section>

        {{-- RIGHT: Category + Move To --}}
        <x-filament::section class="lg:col-span-1">
            <div class="space-y-4">
                {{-- Render ONLY the select fields --}}
                {{ $this->form->getComponent('data.category_id') }}
                {{ $this->form->getComponent('data.move_to_id') }}

                {{-- Rename button below Move To --}}
                <x-filament::button class="w-full" color="info" wire:click="startRename" wire:loading.attr="disabled"
                    wire:target="startRename">
                    <span wire:loading.remove wire:target="startRename">Rename</span>
                    <span wire:loading wire:target="startRename">Starting…</span>
                </x-filament::button>

                {{-- Optional small instruction text --}}
                <div class="text-xs text-gray-500">
                    Enter one name per line. The number of lines must be equal to or greater than media found in the
                    selected category.
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
