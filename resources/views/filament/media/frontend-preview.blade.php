@php
    $title = (string) ($title ?? '');
    $description = (string) ($description ?? '');
    $url = (string) ($url ?? '');
    $imageUrl = $imageUrl ?? null;
@endphp

<div class="rounded-lg border bg-white p-4 space-y-3">
    <div class="text-xs text-slate-500">
        Frontend preview (approx)
    </div>

    @if ($url !== '')
        <div class="text-xs">
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                class="text-primary-600 hover:underline">
                Open preview in new tab →
            </a>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="min-w-0">
            <div class="h-1 w-14 bg-red-500"></div>

            <div class="mt-3 text-sm font-semibold text-slate-700">
                Your Tech-pack, Our production
            </div>

            <div class="mt-2 text-2xl font-extrabold leading-tight tracking-tight text-slate-900 break-words">
                {{ $title }}
            </div>

            @if ($description !== '')
                <div class="mt-3 text-sm leading-6 text-slate-700 whitespace-pre-line">
                    {{ $description }}
                </div>
            @endif

            <div class="mt-4 inline-flex items-center rounded bg-[#1f5f99] px-4 py-2 text-sm font-semibold text-white">
                Get Price
            </div>
        </div>

        <div class="rounded bg-slate-50 p-3">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="" class="w-full object-contain rounded">
            @else
                <div class="h-48 w-full rounded bg-slate-100"></div>
            @endif
        </div>
    </div>

    <div class="text-xs text-slate-500">
        Tip: this preview uses current form values. Real public page updates after Save.
    </div>
</div>
