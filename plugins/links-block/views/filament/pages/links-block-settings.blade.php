<div class="space-y-6">
    <div class="text-2xl font-semibold text-gray-900">
        Links Block Shortcode Instructions
    </div>

    <div class="text-sm text-gray-600">
        Use the shortcode <code class="px-2 py-1 bg-gray-100 rounded">[linksblock]</code> with the following attributes:
    </div>

    {{-- Table UI --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="text-left font-semibold px-4 py-3 w-40">Attribute</th>
                        <th class="text-left font-semibold px-4 py-3 w-64">Build</th>
                        <th class="text-left font-semibold px-4 py-3">Description</th>
                        <th class="text-left font-semibold px-4 py-3 w-28">Default</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    {{-- col --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">col</span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" min="1" max="12"
                                   class="w-28 border-gray-300 rounded-lg"
                                   wire:model.live="data.col" />
                        </td>
                        <td class="px-4 py-3 text-gray-600">Number of columns</td>
                        <td class="px-4 py-3 text-gray-600">3</td>
                    </tr>

                    {{-- hide --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">hide</span>
                        </td>
                        <td class="px-4 py-3">
                            <select class="w-28 border-gray-300 rounded-lg"
                                    wire:model.live="data.hide">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-gray-600">Initial visible status of all blocks (yes/no)</td>
                        <td class="px-4 py-3 text-gray-600">no</td>
                    </tr>

                    {{-- new-window --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">new-window</span>
                        </td>
                        <td class="px-4 py-3">
                            <select class="w-28 border-gray-300 rounded-lg"
                                    wire:model.live="data.new_window">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-gray-600">Open links in a new window (yes/no)</td>
                        <td class="px-4 py-3 text-gray-600">yes</td>
                    </tr>

                    {{-- row --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">row</span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" min="0" max="200"
                                   class="w-28 border-gray-300 rounded-lg"
                                   wire:model.live="data.row" />
                        </td>
                        <td class="px-4 py-3 text-gray-600">Number of rows</td>
                        <td class="px-4 py-3 text-gray-600">2</td>
                    </tr>

                    {{-- rand --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">rand</span>
                        </td>
                        <td class="px-4 py-3">
                            <select class="w-28 border-gray-300 rounded-lg"
                                    wire:model.live="data.rand">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-gray-600">Shuffle links (yes/no)</td>
                        <td class="px-4 py-3 text-gray-600">yes</td>
                    </tr>

                    {{-- n --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">n</span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" min="1" max="5000"
                                   class="w-28 border-gray-300 rounded-lg"
                                   wire:model.live="data.n" />
                        </td>
                        <td class="px-4 py-3 text-gray-600">Total number of links</td>
                        <td class="px-4 py-3 text-gray-600">60</td>
                    </tr>

                    {{-- mcol --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">mcol</span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" min="1" max="6"
                                   class="w-28 border-gray-300 rounded-lg"
                                   wire:model.live="data.mcol" />
                        </td>
                        <td class="px-4 py-3 text-gray-600">Maximum columns on small devices</td>
                        <td class="px-4 py-3 text-gray-600">1</td>
                    </tr>

                    {{-- tcol --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">tcol</span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" min="1" max="12"
                                   class="w-28 border-gray-300 rounded-lg"
                                   wire:model.live="data.tcol" />
                        </td>
                        <td class="px-4 py-3 text-gray-600">Maximum columns on tablets</td>
                        <td class="px-4 py-3 text-gray-600">2</td>
                    </tr>

                    {{-- single-line --}}
                    <tr>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-gray-100 rounded font-mono">single-line</span>
                        </td>
                        <td class="px-4 py-3">
                            <select class="w-28 border-gray-300 rounded-lg"
                                    wire:model.live="data.single_line">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-gray-600">Display links in a single line (yes/no)</td>
                        <td class="px-4 py-3 text-gray-600">yes</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Generated shortcode + copy --}}
    <div class="space-y-2">
        <div class="text-lg font-semibold text-gray-900">Generated Shortcode</div>

        <div class="flex gap-2 items-start">
            <textarea id="lb_shortcode"
                      class="w-full min-h-[60px] border border-gray-300 rounded-xl p-3 font-mono text-sm"
                      readonly>{{ $this->shortcode }}</textarea>

          <button
    type="button"
    class="px-4 py-2 rounded-xl bg-gray-900 text-white hover:bg-gray-800"
    x-data
    x-on:click="
        (async () => {
            const el = document.getElementById('lb_shortcode');
            const text = el ? el.value : '';

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // fallback for non-https / older browsers
                    el.focus();
                    el.select();
                    document.execCommand('copy');
                }

                // ✅ Filament notification (bottom-right)
                $dispatch('notify', { message: 'Shortcode copied!', status: 'success' });

            } catch (e) {
                $dispatch('notify', { message: 'Copy failed. Please copy manually.', status: 'danger' });
            }
        })();
    "
>
    Copy
</button>
        </div>
    </div>

    {{-- Preview --}}
    <div class="space-y-2">
        <div class="text-lg font-semibold text-gray-900">Preview</div>

        <div class="bg-white border border-gray-200 rounded-xl p-4">
            {!! $this->previewHtml() !!}
        </div>
    </div>
</div>