@props([
    'recordId' => null,
    'links' => [],
])

<div x-data="{ open: false }" class="mt-2">
    <div class="flex gap-2">
        {{-- Generate button with loading --}}
        <button type="button" class="fi-btn fi-btn-color-primary" wire:click="generateMultipageLinks"
            wire:loading.attr="disabled" wire:target="generateMultipageLinks">
            <span wire:loading.remove wire:target="generateMultipageLinks">Generate</span>
            <span wire:loading wire:target="generateMultipageLinks">Generating...</span>
        </button>

        {{-- View List button --}}
        @if ($recordId)
            <button type="button" class="fi-btn fi-btn-color-gray" @click="open = true">
                View List
            </button>
        @else
            <button type="button" class="fi-btn fi-btn-color-gray" disabled>
                View List
            </button>
        @endif
    </div>

    {{-- Modal (only list, no iframe) --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60" @click="open = false"></div>

        <div class="relative bg-white rounded-xl shadow-xl w-[95vw] h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <div class="font-semibold">
                    Generated Links
                    @if (is_array($links) && count($links))
                        <span class="text-sm text-gray-500 font-normal">
                            ({{ count($links) }})
                        </span>
                    @endif
                </div>

                <button type="button" class="fi-btn fi-btn-color-gray" @click="open = false">
                    Close
                </button>
            </div>

            <div class="w-full h-[calc(90vh-56px)] overflow-auto p-4">
                @if (!$recordId)
                    <div class="text-sm text-gray-600">
                        Save this Multi Page first, then generate links.
                    </div>
                @elseif (!is_array($links) || count($links) === 0)
                    <div class="text-sm text-gray-600">
                        No generated links found. Click <strong>Generate</strong> first.
                    </div>
                @else
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($links as $path)
                            @php
                                $path = trim((string) $path);
                                if ($path === '') {
                                    continue;
                                }

                                // Helper: force trailing slash (safe with query/hash)
                                $forceTrailingSlash = function (string $u): string {
                                    $u = trim($u);
                                    if ($u === '') {
                                        return $u;
                                    }

                                    $parts = parse_url($u);
                                    if (!is_array($parts)) {
                                        return rtrim($u, '/') . '/';
                                    }

                                    $scheme = $parts['scheme'] ?? null;
                                    $host = $parts['host'] ?? null;

                                    // If parse_url doesn't detect scheme/host, treat as path
    if (!$scheme || !$host) {
        $p = $parts['path'] ?? $u;
        $p = $p === '/' ? '/' : rtrim($p, '/') . '/';
        return $p;
    }

    $user = $parts['user'] ?? null;
    $pass = $parts['pass'] ?? null;
    $port = $parts['port'] ?? null;

    $pathPart = $parts['path'] ?? '/';
    $pathPart = $pathPart === '/' ? '/' : rtrim($pathPart, '/') . '/';

    $query = $parts['query'] ?? null;
    $fragment = $parts['fragment'] ?? null;

    $auth = '';
    if ($user) {
        $auth = $user;
        if ($pass) {
            $auth .= ':' . $pass;
        }
        $auth .= '@';
    }

    $base = $scheme . '://' . $auth . $host;
    if ($port) {
        $base .= ':' . $port;
    }

    $out = $base . $pathPart;
    if ($query !== null && $query !== '') {
        $out .= '?' . $query;
    }
    if ($fragment !== null && $fragment !== '') {
        $out .= '#' . $fragment;
    }

    return $out;
};

// Build FULL URL + trailing slash for internal links
if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
    $href = $forceTrailingSlash($path);
} else {
    $relative = '/' . trim($path, '/') . '/';
                                    $href = $forceTrailingSlash(url($relative)); // url() may remove slash, so force it back
                                }

                                // Show full URL in UI
                                $label = $href;
                            @endphp

                            <li class="py-1">
                                <a target="_blank" rel="noopener noreferrer" href="{{ $href }}"
                                    style="color:#2563eb; text-decoration:none;"
                                    onmouseover="this.style.textDecoration='underline'"
                                    onmouseout="this.style.textDecoration='none'">
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
