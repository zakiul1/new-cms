@php
    $colsMobile = (int) ($mcol ?? 2);
    $colsTablet = (int) ($tcol ?? 3);
    $colsDesktop = (int) ($col ?? 4);

    $colsMobile = max(1, min(6, $colsMobile));
    $colsTablet = max(1, min(12, $colsTablet));
    $colsDesktop = max(1, min(12, $colsDesktop));

    $target = !empty($newWindow) ? '_blank' : null;
    $rel = !empty($newWindow) ? 'noopener noreferrer' : null;

    $aClass = 'text-slate-700 hover:text-slate-900  underline-offset-4';
    if (!empty($singleLine)) {
        $aClass .= ' truncate block';
    }

    // Heading mode: when row="9", first item is heading and next 8 are links
    $isHeadingMode = (int) ($row ?? 0) === 9;

    // unique id for toggle container
    $uid = 'lb_' . substr(md5(json_encode($columns ?? []) . microtime(true)), 0, 10);

    /**
     * Build URL:
     * - Keep external URLs as-is
     * - Internal paths use cms_slug_url() (adds trailing slash)
     */
    $lbUrl = function ($value): string {
        $value = trim((string) $value);

        if ($value === '') {
            return url('/');
        }

        // External absolute URLs stay untouched
        if (preg_match('~^https?://~i', $value)) {
            return $value;
        }

        // Internal paths/slugs: enforce trailing slash via CMS helper
        if (function_exists('cms_slug_url')) {
            return cms_slug_url($value);
        }

        // Fallback: ensure trailing slash manually
        return url('/' . trim($value, '/') . '/');
    };
@endphp

{{-- Minimal CSS for dynamic columns (Tailwind-friendly) --}}
<style>
    .linksblock-grid {
        grid-template-columns: repeat(var(--lb-cols-mobile, 2), minmax(0, 1fr));
    }

    @media (min-width: 768px) {
        .linksblock-grid {
            grid-template-columns: repeat(var(--lb-cols-md, 3), minmax(0, 1fr));
        }
    }

    @media (min-width: 1024px) {
        .linksblock-grid {
            grid-template-columns: repeat(var(--lb-cols-lg, 4), minmax(0, 1fr));
        }
    }
</style>

<div class="page-container my-6">
    @if (!empty($hide))
        {{-- ICON ONLY (arrow down/up) --}}
        <button type="button" class="inline-flex items-center justify-center w-10 h-10 cursor-pointer border-slate-200"
            aria-controls="{{ $uid }}" aria-expanded="false"
            onclick="
                const el = document.getElementById('{{ $uid }}');
                if (!el) return;

                const isHidden = el.classList.contains('hidden');
                el.classList.toggle('hidden', !isHidden);

                // toggle icons
                const down = this.querySelector('[data-icon=down]');
                const up = this.querySelector('[data-icon=up]');
                if (down && up) {
                    down.classList.toggle('hidden', isHidden); // when opened -> hide down
                    up.classList.toggle('hidden', !isHidden);  // when opened -> show up
                }

                this.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            ">
            {{-- Arrow DOWN (default shown when closed) --}}
            <svg data-icon="down" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-700" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
            </svg>

            {{-- Arrow UP (hidden by default, shown when open) --}}
            <svg data-icon="up" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-700 hidden"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 15l-6-6-6 6" />
            </svg>
        </button>

        {{-- Hidden content (shows only after click) --}}
        <div id="{{ $uid }}" class="hidden mt-4">
            <div class="grid gap-6 linksblock-grid"
                style="--lb-cols-mobile: {{ $colsMobile }}; --lb-cols-md: {{ $colsTablet }}; --lb-cols-lg: {{ $colsDesktop }};">
                @foreach ($columns ?? [] as $list)
                    @php
                        $heading = $isHeadingMode ? $list[0] ?? null : null;
                        $items = $isHeadingMode ? array_slice($list, 1) : $list;
                    @endphp

                    <div>
                        @if ($isHeadingMode && $heading)
                            @php
                                $hHref = $lbUrl($heading);
                                $hTitle = \Plugins\LinksBlock\Support\LinksBlockShortcode::titleFromUrl(
                                    (string) $heading,
                                );
                            @endphp

                            {{-- Heading --}}
                            <a href="{{ $hHref }}"
                                class="block font-bold leading-relaxed whitespace-normal text-slate-900  underline-offset-4"
                                style="margin-bottom: 10px; white-space: normal;"
                                @if ($target) target="{{ $target }}" @endif
                                @if ($rel) rel="{{ $rel }}" @endif>
                                {{ $hTitle }}
                            </a>
                        @endif

                        {{-- Items --}}
                        <ul class="space-y-2">
                            @foreach ($items as $u)
                                @php
                                    $href = $lbUrl($u);
                                    $title = \Plugins\LinksBlock\Support\LinksBlockShortcode::titleFromUrl((string) $u);
                                @endphp
                                <li>
                                    <a href="{{ $href }}" class="{{ $aClass }}"
                                        @if ($target) target="{{ $target }}" @endif
                                        @if ($rel) rel="{{ $rel }}" @endif>
                                        {{ $title }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- Normal (not hidden) --}}
        <div class="grid gap-6 linksblock-grid"
            style="--lb-cols-mobile: {{ $colsMobile }}; --lb-cols-md: {{ $colsTablet }}; --lb-cols-lg: {{ $colsDesktop }};">
            @foreach ($columns ?? [] as $list)
                @php
                    $heading = $isHeadingMode ? $list[0] ?? null : null;
                    $items = $isHeadingMode ? array_slice($list, 1) : $list;
                @endphp

                <div>
                    @if ($isHeadingMode && $heading)
                        @php
                            $hHref = $lbUrl($heading);
                            $hTitle = \Plugins\LinksBlock\Support\LinksBlockShortcode::titleFromUrl((string) $heading);
                        @endphp

                        {{-- Heading --}}
                        <a href="{{ $hHref }}"
                            class="block font-bold leading-relaxed whitespace-normal text-slate-900 hover:underline underline-offset-4"
                            style="margin-bottom: 10px; white-space: normal;"
                            @if ($target) target="{{ $target }}" @endif
                            @if ($rel) rel="{{ $rel }}" @endif>
                            {{ $hTitle }}
                        </a>
                    @endif

                    {{-- Items --}}
                    <ul class="space-y-2">
                        @foreach ($items as $u)
                            @php
                                $href = $lbUrl($u);
                                $title = \Plugins\LinksBlock\Support\LinksBlockShortcode::titleFromUrl((string) $u);
                            @endphp
                            <li>
                                <a href="{{ $href }}" class="{{ $aClass }}"
                                    @if ($target) target="{{ $target }}" @endif
                                    @if ($rel) rel="{{ $rel }}" @endif>
                                    {{ $title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
