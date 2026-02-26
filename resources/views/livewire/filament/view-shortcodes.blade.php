{{-- resources/views/livewire/filament/view-shortcodes.blade.php --}}

@once
    @push('scripts')
        <script>
            (function() {
                function ensureToast() {
                    let toast = document.getElementById('cmsCopyToast');
                    if (toast) return toast;

                    toast = document.createElement('div');
                    toast.id = 'cmsCopyToast';
                    toast.style.position = 'fixed';
                    toast.style.right = '20px';
                    toast.style.bottom = '20px';
                    toast.style.zIndex = '100000';
                    toast.style.background = '#2f6fa3';
                    toast.style.color = '#fff';
                    toast.style.padding = '10px 14px';
                    toast.style.borderRadius = '10px';
                    toast.style.fontSize = '13px';
                    toast.style.boxShadow = '0 10px 25px rgba(0,0,0,.25)';
                    toast.style.display = 'none';

                    const span = document.createElement('span');
                    span.id = 'cmsCopyToastText';
                    span.textContent = 'Copied!';
                    toast.appendChild(span);

                    document.body.appendChild(toast);
                    return toast;
                }

                function showToast(message) {
                    const toast = ensureToast();
                    const text = document.getElementById('cmsCopyToastText');
                    if (text) text.textContent = message || 'Copied!';

                    toast.style.display = 'block';
                    clearTimeout(window.__cmsCopyToastTimer);
                    window.__cmsCopyToastTimer = setTimeout(() => {
                        toast.style.display = 'none';
                    }, 1400);
                }

                function copyViaTextarea(text) {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    ta.style.top = '-9999px';
                    document.body.appendChild(ta);

                    ta.focus();
                    ta.select();

                    let ok = false;
                    try {
                        ok = document.execCommand('copy');
                    } catch (e) {
                        ok = false;
                    }

                    document.body.removeChild(ta);
                    return ok;
                }

                window.cmsCopyText = async function(text) {
                    try {
                        // Works on HTTP + Firefox
                        const ok = copyViaTextarea(text);

                        // If clipboard API is available, try it too (ignore failures)
                        if (navigator.clipboard) {
                            try {
                                await navigator.clipboard.writeText(text);
                            } catch (e) {}
                        }

                        if (ok || navigator.clipboard) {
                            showToast('Copied!');
                        } else {
                            showToast('Copy failed');
                        }
                    } catch (e) {
                        console.error(e);
                        showToast('Copy failed');
                    }
                };
            })
            ();
        </script>
    @endpush
@endonce

<div class="flex items-center gap-2">
    <button type="button" wire:click="openModal" class="fi-btn fi-btn-size-sm fi-btn-color-gray">
        <span style="font-weight:600;">View Shortcodes</span>
    </button>

    @if ($open)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" wire:click="closeModal"></div>

            <div class="relative w-[95%] max-w-6xl  bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div class="text-lg font-semibold">Shortcodes</div>
                    <button class="fi-btn fi-btn-size-sm fi-btn-color-gray" wire:click="closeModal">Close</button>
                </div>

                <div class="p-5">
                    <div class="mb-4 flex items-center gap-3">
                        <input type="text" wire:model.live="search" class="w-full rounded-lg border px-3 py-2"
                            placeholder="Search shortcode, description, params..." />
                    </div>

                    <div class="max-h-[70vh] overflow-auto rounded-lg border">
                        <table class="w-full text-sm table-fixed">
                            {{-- 2 / 3 / 7 layout --}}
                            <colgroup>
                                <col style="width:16.6667%">
                                <col style="width:25%">
                                <col style="width:58.3333%">
                            </colgroup>

                            <thead class="sticky top-0 bg-gray-50">
                                <tr class="text-left">
                                    <th class="p-3">Shortcode</th>
                                    <th class="p-3">Full Shortcode</th>
                                    <th class="p-3">Details</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($this->shortcodes as $sc)
                                    @php
                                        $tag = $sc['tag'];
                                        $desc = $sc['description'] ?? '';
                                        $params = $sc['params'] ?? [];

                                        $paramParts = [];
                                        foreach ($params as $p) {
                                            $name = $p['name'] ?? '';
                                            if ($name === '') {
                                                continue;
                                            }

                                            $type = strtolower((string) ($p['type'] ?? ''));
                                            if ($type === 'flag') {
                                                $paramParts[] = $name;
                                                continue;
                                            }

                                            $default = $p['default'] ?? '';
                                            if (is_bool($default)) {
                                                $default = $default ? 'true' : 'false';
                                            }

                                            $paramParts[] = $name . '="' . e((string) $default) . '"';
                                        }

                                        $base = '[' . $tag . ']';
                                        $full =
                                            '[' .
                                            $tag .
                                            (count($paramParts) ? ' ' . implode(' ', $paramParts) : '') .
                                            ']';
                                    @endphp

                                    <tr class="border-t align-top">
                                        <td class="p-3">
                                            <div class="font-mono break-all">{{ $base }}</div>
                                            <div class="mt-2">
                                                <button type="button"
                                                    class="fi-btn fi-btn-size-sm fi-btn-color-primary"
                                                    onclick="window.cmsCopyText && window.cmsCopyText(@js($base))">
                                                    Copy
                                                </button>
                                            </div>
                                        </td>

                                        <td class="p-3">
                                            <div class="font-mono text-xs break-all">{{ $full }}</div>
                                            <div class="mt-2">
                                                <button type="button" class="fi-btn fi-btn-size-sm fi-btn-color-gray"
                                                    onclick="window.cmsCopyText && window.cmsCopyText(@js($full))">
                                                    Copy
                                                </button>
                                            </div>
                                        </td>

                                        <td class="p-3">
                                            @if ($desc !== '')
                                                <div class="font-medium">{{ $desc }}</div>
                                            @endif

                                            @if (!empty($params))
                                                <div class="mt-3 rounded-md border bg-gray-50">
                                                    <div class="px-3 py-2 text-xs font-semibold text-gray-700">
                                                        Parameters
                                                    </div>

                                                    <div class="overflow-auto">
                                                        <table class="w-full text-xs">
                                                            <thead class="bg-gray-100">
                                                                <tr>
                                                                    <th class="p-2 text-left w-36">Name</th>
                                                                    <th class="p-2 text-left w-28">Type</th>
                                                                    <th class="p-2 text-left w-28">Default</th>
                                                                    <th class="p-2 text-left">Description</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($params as $p)
                                                                    <tr class="border-t">
                                                                        <td class="p-2 font-mono">{{ $p['name'] ?? '' }}
                                                                        </td>
                                                                        <td class="p-2">{{ $p['type'] ?? '' }}</td>
                                                                        <td class="p-2">
                                                                            {{ is_bool($p['default'] ?? null) ? ($p['default'] ?? false ? 'true' : 'false') : $p['default'] ?? '' }}
                                                                        </td>
                                                                        <td class="p-2">{{ $p['desc'] ?? '' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="mt-2 text-xs text-gray-500">
                                                    No parameter documentation for this shortcode yet.
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-6 text-center text-gray-500">
                                            No shortcodes found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    @endif
</div>
