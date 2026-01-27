<x-filament-panels::page>
    @once
        @vite('resources/js/filament/monaco.js')
    @endonce

    <div class="grid grid-cols-12 gap-4">
        {{-- Left: file tree --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="rounded-xl border p-3">
                <div class="font-semibold mb-2">Files</div>

                <div class="space-y-1 max-h-[70vh] overflow-auto">
                    @forelse($this->files as $f)
                        @php
                            $path = (string) ($f['path'] ?? '');
                            $label = (string) ($f['label'] ?? $path);
                            $isBinary = (bool) ($f['binary'] ?? false);
                        @endphp

                        <button type="button" wire:click="selectFile(@js($path))"
                            @class([
                                'w-full text-left px-2 py-1 rounded-lg hover:bg-gray-100',
                                'bg-gray-100 font-semibold' => $this->filePath === $path,
                            ])>
                            <div class="text-xs text-gray-500 flex items-center gap-2">
                                <span>{{ $path }}</span>
                                @if ($this->filePath === $path && $this->dirty)
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800">●</span>
                                @endif
                            </div>

                            <div class="text-sm flex items-center gap-2">
                                <span>{{ $label }}</span>

                                @if ($isBinary)
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">binary</span>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="text-sm text-gray-500">No files found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right: editor --}}
        <div class="col-span-12 lg:col-span-8">
            <div class="rounded-xl border p-3">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="font-semibold flex items-center gap-2">
                            <span>Editor</span>
                            @if ($this->dirty)
                                <span class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Unsaved</span>
                            @endif
                        </div>

                        <div class="text-xs text-gray-500">
                            Plugin: {{ $this->pluginSlug }}
                            @if ($this->filePath)
                                · File: {{ $this->filePath }}
                            @endif
                        </div>
                    </div>

                    <div class="text-xs">
                        @if ($this->readOnly)
                            <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800">Read-only</span>
                        @else
                            <span class="px-2 py-1 rounded bg-green-100 text-green-800">Editing</span>
                        @endif
                    </div>
                </div>

                {{-- Filament form (selects + hidden content state) --}}
                <div class="space-y-4">
                    {{ $this->form }}

                    {{-- Monaco mount point --}}
                    <div id="plugin-monaco" wire:ignore data-livewire-id="{{ $this->getId() }}"
                        data-readonly="{{ $this->readOnly ? '1' : '0' }}" class="rounded-lg border overflow-hidden"
                        style="height: 65vh;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Unsaved changes modal --}}
    <div x-data="{ open: @entangle('showSwitchModal') }" x-show="open" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="open=false; $wire.cancelSwitch()"></div>

        <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div class="font-semibold text-lg">Unsaved changes</div>
            <div class="text-sm text-gray-600 mt-1">
                You have unsaved changes. What do you want to do?
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" class="px-3 py-2 rounded-lg border" @click="open=false; $wire.cancelSwitch()">
                    Cancel
                </button>

                <button type="button" class="px-3 py-2 rounded-lg border border-red-300 text-red-700"
                    wire:click="confirmDiscardAndSwitch">
                    Discard & Switch
                </button>

                <button type="button" class="px-3 py-2 rounded-lg bg-black text-white"
                    wire:click="confirmSaveAndSwitch">
                    Save & Switch
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
