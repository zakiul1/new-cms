<div class="space-y-4">
    {{-- Folder select (optional) --}}
    <div class="flex items-center gap-3">
        <div class="w-full max-w-sm">
            <label class="mb-1 block text-xs font-medium text-gray-700">Upload into folder (optional)</label>
            <select wire:model="termId" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                <option value="">—</option>
                @foreach (\App\Models\Term::query()->where('taxonomy_id', \App\Models\Taxonomy::query()->where('key', 'media_folder')->value('id'))->orderBy('name')->get() as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Dropzone --}}
    <div x-data="{ isDropping: false }" x-on:dragover.prevent="isDropping=true" x-on:dragleave.prevent="isDropping=false"
        x-on:drop.prevent="isDropping=false" class="rounded-xl border border-dashed border-gray-300 bg-white p-4"
        :class="isDropping ? 'border-gray-400 bg-gray-50' : ''">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-sm font-semibold text-gray-900">Drag & drop files here</div>
                <div class="text-xs text-gray-500">Or click to choose files. Images will generate variants
                    automatically.</div>
            </div>

            <label
                class="cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-800">
                Choose files
                <input type="file" class="hidden" multiple wire:model="files" />
            </label>
        </div>

        @error('files.*')
            <div class="mt-2 text-xs text-red-600">{{ $message }}</div>
        @enderror
    </div>

    {{-- Grid preview (same style vibe as your media grid) --}}
    @if (count($files))
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($files as $i => $file)
                @php
                    $name = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'file';
                    $sizeKb = method_exists($file, 'getSize')
                        ? number_format(((int) $file->getSize()) / 1024, 1)
                        : null;
                @endphp

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="relative aspect-square bg-gray-100">
                        {{-- preview --}}
                        @if (method_exists($file, 'temporaryUrl'))
                            <img src="{{ $file->temporaryUrl() }}"
                                class="absolute inset-0 h-full w-full object-contain p-3" alt="" />
                        @else
                            <div class="flex h-full items-center justify-center text-xs text-gray-400">
                                Preview
                            </div>
                        @endif

                        {{-- remove --}}
                        <button type="button" wire:click="removeFile({{ $i }})"
                            class="absolute right-2 top-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-800">
                            Remove
                        </button>
                    </div>

                    <div class="px-2 py-2">
                        <div class="truncate text-xs font-semibold text-gray-900" title="{{ $name }}">
                            {{ \Illuminate\Support\Str::limit($name, 24) }}
                        </div>
                        <div class="mt-0.5 flex items-center justify-between text-[11px] text-gray-500">
                            <span class="truncate">Ready</span>
                            @if ($sizeKb)
                                <span class="shrink-0 whitespace-nowrap">{{ $sizeKb }} KB</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end gap-2">
            <button type="button" wire:click="upload" wire:loading.attr="disabled"
                class="rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-60">
                <span wire:loading.remove>Upload</span>
                <span wire:loading>Uploading...</span>
            </button>
        </div>
    @endif
</div>
