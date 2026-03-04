<div class="col-span-full">
    <x-filament::button type="button" color="primary" wire:click="generateSitemap" wire:loading.attr="disabled">
        <span wire:loading.remove>Generate Sitemap</span>
        <span wire:loading>Generating...</span>
    </x-filament::button>
</div>
