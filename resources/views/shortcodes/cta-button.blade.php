@php
    $title = trim((string) ($title ?? ''));
    $link = trim((string) ($link ?? ''));

    if ($title === '') {
        $title = 'write something';
    }

    if ($link === '') {
        $link = '#';
    } else {
        $lower = strtolower($link);

        $hasScheme =
            str_starts_with($lower, 'http://') ||
            str_starts_with($lower, 'https://') ||
            str_starts_with($lower, 'mailto:') ||
            str_starts_with($lower, 'tel:');

        $isRelative = str_starts_with($link, '/') || str_starts_with($link, '#') || str_starts_with($link, '?');

        if (!$hasScheme && !$isRelative) {
            $link = 'https://' . ltrim($link, '/');
        }
    }

    $isExternal = str_starts_with(strtolower($link), 'http://') || str_starts_with(strtolower($link), 'https://');

    $target = $isExternal ? '_blank' : null;
    $rel = $isExternal ? 'noopener noreferrer' : null;
@endphp

<a href="{{ e($link) }}" class="cta-button" @if ($target) target="{{ $target }}" @endif
    @if ($rel) rel="{{ $rel }}" @endif>
    <span>{{ $title }}</span>

    <svg class="cta-button-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
        <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path>
    </svg>
</a>
