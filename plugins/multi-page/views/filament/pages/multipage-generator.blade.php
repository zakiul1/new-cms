<x-filament-panels::page>
    @if ($lastMessage)
        <div class="rounded-xl border p-4 mb-4">
            {{ $lastMessage }}
        </div>
    @endif

    {{ $this->form }}
</x-filament-panels::page>
