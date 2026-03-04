<x-filament-panels::page>
    @php($p = $this->progress())
    @php($total = (int) ($p['total'] ?? 0))
    @php($done = (int) ($p['done'] ?? 0))
    @php($created = (int) ($p['created'] ?? 0))
    @php($status = (string) ($p['status'] ?? 'idle'))
    @php($errors = is_array($p['errors'] ?? null) ? $p['errors'] : [])
    @php($percent = $total > 0 ? min(100, (int) round(($done / $total) * 100)) : 0)

    {{-- ✅ TOP Progress / Status --}}
    <div class="space-y-6 mb-6" @if ($this->isRunning) wire:poll.1200ms="tickGenerate" @endif>
        <x-filament::section>
            <div class="flex items-start justify-between gap-4">
                <div class="text-sm">
                    <div><strong>Status:</strong> {{ $status }}</div>
                    <div><strong>Progress:</strong> {{ $done }} / {{ $total }}</div>
                    @if ($status === 'finished')
                        <div><strong>Created:</strong> {{ $created }}</div>
                    @endif
                </div>

                @if ($this->isRunning)
                    <div class="text-xs text-gray-500">
                        Generating in progress…
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

    {{-- Same layout as Media Name Changer --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- LEFT: Textarea --}}
        <x-filament::section class="lg:col-span-2">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">
                        New posts (one block per post)
                    </div>
                </div>

                {{ $this->form->getComponent('data.text') }}

                <div class="text-xs text-gray-500">
                    Format: First line = <strong>Title</strong>. Next lines = <strong>Content</strong>. Separate posts
                    by an empty line.
                </div>
            </div>
        </x-filament::section>

        {{-- RIGHT: Category + Generate --}}
        <x-filament::section class="lg:col-span-1">
            <div class="space-y-4">
                {{ $this->form->getComponent('data.category_id') }}

                <x-filament::button class="w-full" color="info" wire:click="startGenerate"
                    wire:loading.attr="disabled" wire:target="startGenerate">
                    <span wire:loading.remove wire:target="startGenerate">Generate</span>
                    <span wire:loading wire:target="startGenerate">Starting…</span>
                </x-filament::button>

                <div class="text-xs text-gray-500">
                    Category is required. Posts will be created under the selected category.
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
