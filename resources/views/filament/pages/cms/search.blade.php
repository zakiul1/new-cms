<x-filament::page>
    <x-filament::section>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input wire:model.live.debounce.400ms="q" placeholder="Search..." />
                </x-filament::input.wrapper>
            </div>

            <div class="w-full lg:w-56">
                <x-filament::input.wrapper>
                    <select class="fi-input w-full" wire:model.live="type">
                        <option value="">All</option>
                        <option value="page">Pages</option>
                        <option value="post">Posts</option>
                    </select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button wire:click="searchNow">
                Search
            </x-filament::button>
        </div>

        <div class="mt-4 text-sm text-gray-500">
            Total: <span class="font-medium">{{ $total }}</span>
        </div>

        <div class="mt-4 space-y-2">
            @foreach ($results as $r)
                <div class="rounded-lg border p-3">
                    <div class="text-xs text-gray-500 mb-1">{{ strtoupper($r['type']) }}</div>
                    <a class="font-medium" href="{{ $r['url'] }}" target="_blank">
                        {{ $r['title'] }}
                    </a>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament::page>
