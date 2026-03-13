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
@endphp

<a href="{{ e($link) }}" class="cta-button" target="_blank" rel="noopener noreferrer">
    <span>{{ $title }}</span>

    <svg class="cta-button-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path>
    </svg>
</a>

<style>
    .cta-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        max-width: 100%;
        padding: 0.875rem 1rem;
        background-color: #2f6fa3;
        color: #fff;
        border: 2px solid #2f6fa3;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        line-height: 1.3;
        text-align: center;
        transition: var(--transition);
        box-sizing: border-box;
        word-break: break-word;
    }

    .cta-button span {
        display: block;
        flex: 1 1 auto;
    }

    .cta-button-icon {
        width: 20px;
        height: 20px;
        flex: 0 0 20px;
    }

    .cta-button:hover {
        background-color: transparent;
        color: #2f6fa3;
    }

    @media (min-width: 640px) {
        .cta-button {
            width: auto;
            padding: 0.875rem 2rem;
        }
    }
</style>
