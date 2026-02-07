@php
    use Illuminate\Support\Js;
@endphp

<x-filament::page>
    {{-- Toolbar --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 items-center gap-3">
            <input type="search" wire:model.debounce.400ms="search" placeholder="Search themes..."
                class="w-full max-w-md rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-900" />

            <div class="hidden sm:flex items-center gap-2">
                <button type="button" wire:click="$set('filter','all')"
                    class="rounded-lg px-3 py-2 text-sm border {{ $filter === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white dark:bg-gray-900' }}">
                    All
                </button>

                <button type="button" wire:click="$set('filter','active')"
                    class="rounded-lg px-3 py-2 text-sm border {{ $filter === 'active' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white dark:bg-gray-900' }}">
                    Active
                </button>

                <button type="button" wire:click="$set('filter','updates')"
                    class="rounded-lg px-3 py-2 text-sm border {{ $filter === 'updates' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white dark:bg-gray-900' }}">
                    Has updates
                </button>
            </div>
        </div>
    </div>

    {{-- Cards --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->filteredThemes as $slug => $t)
            @php
                $m = $t['manifest'] ?? [];

                $screenshot = $m['screenshot'] ?? null;
                $previewUrl = $screenshot ? asset('themes/' . $slug . '/' . ltrim($screenshot, '/')) : null;

                $current = (string) ($m['version'] ?? '');
                $latest = (string) ($m['latest_version'] ?? '');
                $hasUpdate = $latest !== '' && $current !== '' && version_compare($latest, $current, '>');
            @endphp

            <div wire:key="theme-{{ $slug }}"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                {{-- Preview --}}
                <div class="relative aspect-[16/10] bg-gray-100 dark:bg-gray-800">
                    @if ($previewUrl)
                        <img src="{{ $previewUrl }}" class="h-full w-full object-cover"
                            alt="{{ $m['name'] ?? $slug }}">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-sm text-gray-500">
                            No preview
                        </div>
                    @endif

                    {{-- Badges --}}
                    <div class="absolute left-3 top-3 flex items-center gap-2">
                        @if ($active === $slug)
                            <x-filament::badge color="success">Active</x-filament::badge>
                        @endif

                        @if ($hasUpdate)
                            <x-filament::badge color="warning">Update {{ $latest }}</x-filament::badge>
                        @endif
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-4">
                    <div class="min-w-0">
                        <div class="truncate text-base font-semibold text-gray-900 dark:text-white">
                            {{ $m['name'] ?? $slug }}
                        </div>

                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <span class="font-mono">{{ $slug }}</span>
                            <span>•</span>
                            <span>v{{ $m['version'] ?? '' }}</span>

                            @if (!empty($m['author']))
                                <span>•</span>
                                <span class="truncate max-w-[160px]">{{ $m['author'] }}</span>
                            @endif
                        </div>

                        @if (!empty($m['description']))
                            <div class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ $m['description'] }}
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="mt-4 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <x-filament::button size="sm" color="gray" type="button"
                                wire:click="mountAction('details', { slug: {{ Js::from($slug) }} })">
                                Details
                            </x-filament::button>

                            <x-filament::button size="sm" tag="a" color="gray"
                                href="{{ url('/customizer?theme=' . $slug) }}">
                                Customize
                            </x-filament::button>
                        </div>

                        <div>
                            @if ($active !== $slug)
                                <x-filament::button size="sm" type="button"
                                    wire:click="activateTheme({{ Js::from($slug) }})" wire:loading.attr="disabled"
                                    wire:target="activateTheme">
                                    Activate
                                </x-filament::button>
                            @else
                                <x-filament::button size="sm" disabled>
                                    Active
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Needed so Filament action modals render --}}
    <x-filament-actions::modals />
</x-filament::page>
