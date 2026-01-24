@php
    /** @var \App\Filament\Forms\Components\MediaPicker $field */

    $statePath = $field->getStatePath();

    // IMPORTANT: we must pass plain strings inside Alpine attrs (avoid @js in x-data)
    $statePathJs = str_replace("'", "\\'", $statePath);

    // unique modal id per field instance
    $modalId = 'media-picker-' . md5($field->getId() . '|' . $statePath);
    $modalIdJs = str_replace("'", "\\'", $modalId);

    $multiple = $field->isMultiple();
    $maxItems = $field->getMaxItems();
    $selected = $field->getSelectedMedia();
@endphp

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    <div x-data="{
        modalId: '{{ $modalIdJs }}',
        statePath: '{{ $statePathJs }}',
        multiple: {{ $multiple ? 'true' : 'false' }},
        maxItems: {{ is_null($maxItems) ? 'null' : (int) $maxItems }},
        state: $wire.entangle('{{ $statePathJs }}').live,
    
        open() {
            this.$dispatch('open-modal', { id: this.modalId })
        },
    
        close() {
            this.$dispatch('close-modal', { id: this.modalId })
        },
    
        handleApply(e) {
            if (!e || !e.detail) return;
            if (e.detail.statePath !== this.statePath) return;
    
            const ids = Array.isArray(e.detail.ids) ? e.detail.ids : [];
            const clean = ids.map((x) => Number(x)).filter((n) => Number.isFinite(n) && n > 0);
    
            if (this.multiple) {
                this.state = clean;
            } else {
                this.state = clean.length ? clean[0] : null;
            }
    
            this.close();
        },
    }" x-on:media-library-apply.window="handleApply($event)" class="space-y-3">
        {{-- Preview strip --}}
        <div class="flex flex-wrap gap-2">
            @if (count($selected))
                @foreach ($selected as $m)
                    <div class="relative">
                        <img src="{{ $m['thumb'] }}" class="w-20 h-20 object-cover rounded-md border" alt="">
                    </div>
                @endforeach
            @else
                <div class="text-sm text-gray-500">
                    No {{ $multiple ? 'images' : 'image' }} selected.
                </div>
            @endif
        </div>

        {{-- Open modal --}}
        <x-filament::button type="button" x-on:click="open()">
            {{ $multiple ? 'Select images' : 'Select image' }}
        </x-filament::button>

        {{-- Modal --}}
        <x-filament::modal :id="$modalId" width="7xl">
            <x-slot name="heading">{{ $field->getModalHeading() }}</x-slot>

            <livewire:media-library-browser :state-path="$statePath" :multiple="$multiple" :max-items="$maxItems" :selected="(array) ($field->getState() ?? [])"
                :wire:key="$modalId . '-browser'" />

            <x-slot name="footer">
                <x-filament::button color="gray" type="button" x-on:click="close()">
                    Close
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
</x-dynamic-component>
