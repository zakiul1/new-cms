@php
    $files = value($files) ?? [];
@endphp

<div class="space-y-3">
    @forelse ($files as $file)
        @php $url = (string) ($file['url'] ?? ''); @endphp

        <div class="rounded-xl border bg-white p-3 space-y-1" x-data="{
            text: @js($url),
            async copy() {
                if (!this.text) return;
        
                try {
                    // Prefer modern clipboard API (HTTPS / localhost)
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(this.text);
                    } else {
                        // Fallback for http:// domains like cms.test
                        const el = document.createElement('textarea');
                        el.value = this.text;
                        el.setAttribute('readonly', '');
                        el.style.position = 'fixed';
                        el.style.left = '-9999px';
                        document.body.appendChild(el);
                        el.select();
                        document.execCommand('copy');
                        document.body.removeChild(el);
                    }
        
                    // Optional: quick feedback
                    // You can remove this alert if you want silent copy
                    window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Copied!' } }));
                } catch (e) {
                    alert('Copy failed. Please copy manually:\n' + this.text);
                }
            }
        }">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ $file['name'] ?? '' }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $url }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $file['size'] ?? '—' }} • {{ $file['modified'] ?? '—' }}
                    </div>
                </div>

                <div class="flex shrink-0 gap-2">
                    <a href="{{ $url ?: '#' }}" target="_blank"
                        class="fi-btn fi-btn-color-primary fi-color-primary fi-size-sm">
                        <span class="fi-btn-label">View</span>
                    </a>

                    <button type="button" class="fi-btn fi-btn-color-gray fi-color-gray fi-size-sm"
                        x-on:click="copy()">
                        Copy
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-500">
            No sitemap files found yet. Click <b>Generate All</b>.
        </div>
    @endforelse
</div>

<script>
    // Simple toast fallback (optional)
    window.addEventListener('notify', (e) => {
        // if Filament notification JS is available, you can hook it here.
        // For now, keep it silent or use alert:
        // alert(e.detail.message);

    });
</script>
