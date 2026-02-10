@php
    $state = value($data) ?? [];

    $includePosts = (bool) ($state['include_posts'] ?? true);
    $includePages = (bool) ($state['include_pages'] ?? true);
    $includeMedia = (bool) ($state['include_media'] ?? false);

    $freqOptions = [
        'always' => 'Always',
        'hourly' => 'Hourly',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'never' => 'Never',
    ];

    $priorityOptions = ['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1'];
@endphp

<div class="space-y-4">
    @if ($includePosts)
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium">Posts Changefreq</label>
                <select wire:model.live="data.posts_changefreq" class="fi-select-input w-full">
                    @foreach ($freqOptions as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium">Posts Priority</label>
                <select wire:model.live="data.posts_priority" class="fi-select-input w-full">
                    @foreach ($priorityOptions as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if ($includePages)
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium">Pages Changefreq</label>
                <select wire:model.live="data.pages_changefreq" class="fi-select-input w-full">
                    @foreach ($freqOptions as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium">Pages Priority</label>
                <select wire:model.live="data.pages_priority" class="fi-select-input w-full">
                    @foreach ($priorityOptions as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if ($includeMedia)
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium">Media Changefreq</label>
                <select wire:model.live="data.media_changefreq" class="fi-select-input w-full">
                    @foreach ($freqOptions as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium">Media Priority</label>
                <select wire:model.live="data.media_priority" class="fi-select-input w-full">
                    @foreach ($priorityOptions as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if (!$includePosts && !$includePages && !$includeMedia)
        <div class="text-sm text-gray-500">
            Nothing selected. Enable Posts/Pages/Media.
        </div>
    @endif
</div>
