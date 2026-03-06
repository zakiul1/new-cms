<x-filament::page>
    <div class="fi-footer-builder space-y-6">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                Footer Builder
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Organize footer columns and blocks in a cleaner, easier way.
                            </p>
                        </div>

                        <div
                            class="rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            Tip: Use short titles, keep columns balanced, and group related links together.
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    {{ $this->form }}
                </div>
            </div>
        </div>
    </div>
</x-filament::page>
