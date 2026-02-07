{{-- resources/views/filament/media/wp-uploader.blade.php --}}
@php
    use App\Models\Taxonomy;
    use App\Models\Term;

    // Optional: your ListMedia page can pass these in via ->viewData([...])
    $folderTaxId = $folderTaxId ?? Taxonomy::query()->where('key', 'media_folder')->value('id');
    $catTaxId = $catTaxId ?? Taxonomy::query()->where('key', 'media_category')->value('id');

    $folderOptions =
        $folderOptions ??
        ($folderTaxId
            ? Term::query()->where('taxonomy_id', $folderTaxId)->orderBy('name')->pluck('name', 'id')->all()
            : []);

    $categoryOptions =
        $categoryOptions ??
        ($catTaxId ? Term::query()->where('taxonomy_id', $catTaxId)->orderBy('name')->pluck('name', 'id')->all() : []);

    // Livewire component that renders this view (recommended):
    // - public array $wpUploadFiles = [];
    // - public ?int $wpFolderId = null;
    // - public array $wpCategoryIds = [];

@endphp

<div class="rounded-xl border bg-white p-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="space-y-0.5">
            <div class="text-base font-semibold text-slate-900">Upload Media</div>
            <div class="text-sm text-slate-500">
                Drag & drop files or click “Select files”. Choose folder/category to auto-assign.
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Folder --}}
            <div class="min-w-[180px]">
                <label class="mb-1 block text-xs font-medium text-slate-600">Folder</label>
                <select
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500"
                    wire:model.defer="wpFolderId">
                    <option value="">No folder</option>
                    @foreach ($folderOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <div class="mt-1 text-[11px] text-slate-500">
                    Create folders from “Folders” menu (or add create UI here if you want).
                </div>
            </div>

            {{-- Categories --}}
            <div class="min-w-[220px]">
                <label class="mb-1 block text-xs font-medium text-slate-600">Categories</label>
                <select
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500"
                    wire:model.defer="wpCategoryIds" multiple size="1">
                    @foreach ($categoryOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <div class="mt-1 text-[11px] text-slate-500">
                    If none selected → uploaded items become Uncategorized.
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{-- Dropzone --}}
        <label class="block">
            <input type="file" multiple class="hidden" wire:model="wpUploadFiles" />

            <div
                class="flex min-h-[120px] cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-6 text-center hover:bg-slate-100">
                <div class="space-y-2">
                    <div class="text-sm font-semibold text-slate-800">
                        Drop files to upload
                    </div>
                    <div class="text-sm text-slate-500">
                        or click to select files (up to 50 at a time)
                    </div>
                </div>
            </div>
        </label>

        {{-- Selected files preview --}}
        @if (!empty($wpUploadFiles))
            <div class="mt-3 rounded-lg border bg-white p-3">
                <div class="mb-2 text-sm font-semibold text-slate-800">
                    Selected files ({{ is_countable($wpUploadFiles) ? count($wpUploadFiles) : 0 }})
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($wpUploadFiles as $file)
                        <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-2">
                            <div class="h-10 w-10 overflow-hidden rounded-lg bg-slate-100">
                                @if (method_exists($file, 'temporaryUrl'))
                                    <img src="{{ $file->temporaryUrl() }}" alt=""
                                        class="h-full w-full object-cover" />
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-slate-800">
                                    {{ $file->getClientOriginalName() }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ number_format(($file->getSize() ?? 0) / 1024, 1) }} KB
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                        wire:click="uploadWpMedia" wire:loading.attr="disabled">
                        Upload
                    </button>

                    <button type="button"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                        wire:click="$set('wpUploadFiles', [])" wire:loading.attr="disabled">
                        Clear
                    </button>

                    <div class="text-xs text-slate-500" wire:loading>
                        Uploading…
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
