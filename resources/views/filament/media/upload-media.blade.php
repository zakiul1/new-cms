{{-- resources/views/filament/media/upload-media.blade.php --}}

<div class="mx-auto w-full max-w-[1100px] px-4 py-6">
    <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Media Library</h1>
            <p class="mt-1 text-sm text-slate-500">
                Upload like WordPress: drag & drop, auto upload, assign category (default Uncategorized).
            </p>
        </div>

        <a href="{{ \App\Filament\Resources\MediaResource::getUrl('index') }}"
            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
            Back to Media
        </a>
    </div>

    <livewire:cms.media.wp-media-uploader />
</div>
