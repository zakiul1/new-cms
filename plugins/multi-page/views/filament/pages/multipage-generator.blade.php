<x-filament-panels::page>
    @if ($lastMessage)
        <div class="rounded-xl border p-4 mb-4">
            {{ $lastMessage }}
        </div>
    @endif

    {{-- ✅ Inline "Generate Sitemap" button inside Sitemap Settings section needs a Livewire method --}}
    {{-- If you use the updated SettingsMultiPages.php I gave you, this will work:
         wire:click="generateSitemap"
    --}}
    {{ $this->form }}
</x-filament-panels::page>
