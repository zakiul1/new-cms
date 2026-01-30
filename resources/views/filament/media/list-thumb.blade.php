@php
    /** @var \App\Models\Media $record */
    $record = $getRecord();

    $src = $record->thumbUrl('jpeg')
        ?: $record->thumbUrl()
        ?: $record->url();
@endphp

<div style="width:44px;height:44px;border-radius:10px;overflow:hidden;background:#f1f5f9;border:1px solid rgba(15,23,42,.12);display:flex;align-items:center;justify-content:center;">
    @if ($record->isImage() && $src)
        <img src="{{ $src }}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    @endif
</div>
