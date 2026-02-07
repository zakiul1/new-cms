<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                WP-like backups: content tables + uploaded media (storage/app/public) are stored into a ZIP.
                Scheduled backups can run daily via Laravel scheduler.
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2">#</th>
                            <th>Created</th>
                            <th>Label</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($this->backups as $b)
                            <tr>
                                <td class="py-2">{{ $b->id }}</td>
                                <td>{{ $b->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $b->label ?: '—' }}</td>
                                <td>{{ number_format(($b->size_bytes ?? 0) / 1024 / 1024, 2) }} MB</td>
                                <td class="py-2 flex gap-2">
                                    <x-filament::button size="sm" color="gray"
                                        wire:click="download({{ $b->id }})">
                                        Download
                                    </x-filament::button>

                                    <x-filament::button size="sm" color="danger"
                                        x-on:click="if(confirm('Restore this backup? This will TRUNCATE current CMS tables.')) $wire.restore({{ $b->id }})">
                                        Restore
                                    </x-filament::button>
                                    <x-filament::button color="danger" size="sm"
                                        x-on:click="if(confirm('Delete this backup file permanently?')) $wire.delete({{ $b->id }})">
                                        Delete
                                    </x-filament::button>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
