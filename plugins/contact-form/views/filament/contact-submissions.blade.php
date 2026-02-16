<x-filament::page>
    <div class="space-y-4">
        <div class="text-sm text-gray-600">
            Pending submissions will be retried by your cron URL. You can also retry manually here.
        </div>

        {{ $this->table }}
    </div>
</x-filament::page>
