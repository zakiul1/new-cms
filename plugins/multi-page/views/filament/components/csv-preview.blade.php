@props([
    'selected' => null,
    'rows' => [],
])

@if (!$selected)
    <div class="text-sm text-gray-500">Select a CSV file to preview.</div>
@elseif (!is_array($rows) || !count($rows))
    <div class="text-sm text-gray-500">
        No preview available for: <strong>{{ $selected }}</strong>
    </div>
@else
    <div class="text-sm text-gray-600 mb-2">
        Showing first {{ count($rows) }} rows of <strong>{{ $selected }}</strong>
    </div>

    <div class="overflow-auto max-h-[65vh] border rounded-lg">
        <table class="min-w-full text-sm">
            @foreach ($rows as $rIndex => $row)
                <tr class="{{ $rIndex === 0 ? 'font-semibold bg-gray-50' : '' }}">
                    @foreach ($row as $cell)
                        <td class="px-2 py-1 border-b border-gray-100 whitespace-nowrap">
                            {{ $cell }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
@endif
