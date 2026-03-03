<div class="flex items-center justify-end gap-2">
    <x-filament::button type="button" color="warning" icon="heroicon-o-check" wire:click="saveCompanyInfo"
        wire:loading.attr="disabled" wire:target="saveCompanyInfo">
        <span wire:loading.remove wire:target="saveCompanyInfo">Save Company Info</span>
        <span wire:loading wire:target="saveCompanyInfo">Saving...</span>
    </x-filament::button>
</div>
