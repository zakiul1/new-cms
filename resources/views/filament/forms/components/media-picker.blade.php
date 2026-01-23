@php
    /** @var \App\Filament\Forms\Components\MediaPicker $field */

    use App\Models\Media;

    $id = $field->getId();
    $statePath = $field->getStatePath();

    $multiple = $field->isMultiple();
    $maxItems = $field->getMaxItems();
    $heading = $field->getModalHeading();

    $selected = $field->getSelectedMedia(); // [{id,title,thumb,url}, ...]

    // v1: load latest 200 (fast + safe). We can upgrade to paginated Livewire browser later.
    $library = Media::query()
        ->orderByDesc('id')
        ->limit(200)
        ->get();
@endphp

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            open: false,
            search: '',

            multiple: @js($multiple),
            maxItems: @js($maxItems),

            // entangled filament state:
            state: $wire.entangle(@js($statePath)).live,

            // local selection used in the modal (so cancel won't change saved state):
            temp: [],

            init() {
                this.syncTempFromState();
            },

            syncTempFromState() {
                if (this.multiple) {
                    this.temp = Array.isArray(this.state) ? [...this.state] : [];
                } else {
                    this.temp = this.state ? [this.state] : [];
                }
            },

            openModal() {
                this.syncTempFromState();
                this.open = true;
            },

            closeModal() {
                this.open = false;
            },

            isSelected(id) {
                return this.temp.includes(id);
            },

            toggle(id) {
                id = Number(id);

                if (!this.multiple) {
                    this.temp = [id];
                    return;
                }

                if (this.isSelected(id)) {
                    this.temp = this.temp.filter(x => x !== id);
                    return;
                }

                if (this.maxItems && this.temp.length >= this.maxItems) {
                    return; // silently ignore (we can toast later)
                }

                this.temp.push(id);
            },

            removeSelected(id) {
                id = Number(id);

                if (this.multiple) {
                    this.state = (Array.isArray(this.state) ? this.state : []).filter(x => Number(x) !== id);
                } else {
                    if (Number(this.state) === id) this.state = null;
                }

                this.syncTempFromState();
            },

            apply() {
                if (this.multiple) {
                    // keep order + unique
                    const seen = new Set();
                    const ordered = [];
                    for (const v of this.temp) {
                        const n = Number(v);
                        if (!n || seen.has(n)) continue;
                        seen.add(n);
                        ordered.push(n);
                    }
                    this.state = ordered;
                } else {
                    this.state = this.temp.length ? Number(this.temp[0]) : null;
                }

                this.open = false;
            }
        }"
        class="space-y-3"
    >
        {{-- Selected previews --}}
        <div class="flex flex-wrap gap-2">
            @if (count($selected))
                @foreach ($selected as $m)
                    <div class="relative group">
                        <img
                            src="{{ $m['thumb'] }}"
                            alt=""
                            class="w-20 h-20 object-cover rounded-md border"
                        />

                        <button
                            type="button"
                            class="absolute -top-2 -right-2 hidden group-hover:block bg-white border rounded-full w-6 h-6 text-xs"
                            x-on:click="removeSelected({{ (int) $m['id'] }})"
                            title="Remove"
                        >
                            ✕
                        </button>
                    </div>
                @endforeach
            @else
                <div class="text-sm text-gray-500">
                    No {{ $multiple ? 'images' : 'image' }} selected.
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2">
            <x-filament::button type="button" x-on:click="openModal()">
                {{ $multiple ? 'Select images' : 'Select image' }}
            </x-filament::button>

            @if ($multiple)
                <div class="text-xs text-gray-500">
                    {{ count($selected) }} selected
                    @if ($maxItems) / max {{ (int) $maxItems }} @endif
                </div>
            @endif
        </div>

        {{-- Modal --}}
        <x-filament::modal
            width="6xl"
            x-show="open"
            x-on:keydown.escape.window="if (open) closeModal()"
            :close-by-clicking-away="true"
        >
            <x-slot name="heading">{{ $heading }}</x-slot>

            <div class="space-y-4">
                {{-- Search --}}
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="search"
                            placeholder="Search media..."
                            x-model="search"
                        />
                    </x-filament::input.wrapper>
                </div>

                {{-- Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-[60vh] overflow-auto pr-1">
                    @foreach ($library as $item)
                        @php
                            $label = $item->title ?: ($item->original_filename ?: ('Media #' . $item->id));
                            $thumb = $item->thumbUrl() ?: $item->url();
                        @endphp

                        <button
                            type="button"
                            class="text-left border rounded-lg overflow-hidden hover:ring-2 focus:outline-none"
                            x-on:click="toggle({{ (int) $item->id }})"
                            x-show="!search || @js(strtolower($label))?.includes(search.toLowerCase())"
                        >
                            <div class="relative">
                                <img src="{{ $thumb }}" alt="" class="w-full h-28 object-cover" />

                                <div
                                    class="absolute top-2 right-2 w-5 h-5 rounded-full border bg-white flex items-center justify-center text-xs"
                                    :class="isSelected({{ (int) $item->id }}) ? 'bg-primary-600 text-white border-primary-600' : ''"
                                >
                                    <span x-text="isSelected({{ (int) $item->id }}) ? '✓' : ''"></span>
                                </div>
                            </div>

                            <div class="p-2 text-xs text-gray-700 truncate" title="{{ $label }}">
                                {{ $label }}
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-2">
                    <x-filament::button color="gray" type="button" x-on:click="closeModal()">
                        Cancel
                    </x-filament::button>

                    <x-filament::button type="button" x-on:click="apply()">
                        Use selected
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-dynamic-component>
