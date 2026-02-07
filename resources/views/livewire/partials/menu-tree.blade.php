<ul class="space-y-2 list-none p-0 m-0" data-menu-ul>
    @include('livewire.partials.menu-tree-items', [
        'nodes' => $nodes,
        'collapsed' => $collapsed,
        'items' => $items,
        'savedAt' => $savedAt,
    ])
</ul>
