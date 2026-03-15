<div>
    @php
        $navigation = filament()->getNavigation();
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();

        $groupIcons = [
            'Media' => 'heroicon-o-photo',
            'Tags' => 'heroicon-o-tag',
            'Blog Posts' => 'heroicon-o-pencil-square',
            'Static Posts' => 'heroicon-o-document-duplicate',
            'Mega Post' => 'heroicon-o-squares-2x2',
            'Appearance' => 'heroicon-o-paint-brush',
            'CMS' => 'heroicon-o-cog-6-tooth',
            'SEO' => 'heroicon-o-magnifying-glass',
            'Tools' => 'heroicon-o-wrench-screwdriver',
        ];

        $flattenNavigationItem = function ($item, int $depth = 0) use (&$flattenNavigationItem): array {
            $entries = [[
                'label' => $item->getLabel(),
                'url' => $item->getUrl(),
                'active' => $item->isActive(),
                'depth' => $depth,
                'open_in_new_tab' => $item->shouldOpenUrlInNewTab(),
            ]];

            foreach (collect($item->getChildItems()) as $childItem) {
                $entries = [
                    ...$entries,
                    ...$flattenNavigationItem($childItem, $depth + 1),
                ];
            }

            return $entries;
        };

        $manualChildren = function (string $label): array {
            return match ($label) {
                'Posts' => [
                    [
                        'label' => 'All Posts',
                        'url' => \App\Filament\Resources\Posts\PostResource::getUrl(),
                        'active' => request()->url() === \App\Filament\Resources\Posts\PostResource::getUrl(),
                        'depth' => 0,
                        'open_in_new_tab' => false,
                    ],
                    [
                        'label' => 'Add New',
                        'url' => \App\Filament\Resources\Posts\PostResource::getUrl('create'),
                        'active' => request()->url() === \App\Filament\Resources\Posts\PostResource::getUrl('create'),
                        'depth' => 0,
                        'open_in_new_tab' => false,
                    ],
                ],
                'Pages' => [
                    [
                        'label' => 'All Pages',
                        'url' => \App\Filament\Resources\Pages\PageResource::getUrl(),
                        'active' => request()->url() === \App\Filament\Resources\Pages\PageResource::getUrl(),
                        'depth' => 0,
                        'open_in_new_tab' => false,
                    ],
                    [
                        'label' => 'Add New',
                        'url' => \App\Filament\Resources\Pages\PageResource::getUrl('create'),
                        'active' => request()->url() === \App\Filament\Resources\Pages\PageResource::getUrl('create'),
                        'depth' => 0,
                        'open_in_new_tab' => false,
                    ],
                ],
                default => [],
            };
        };

        $menuItems = collect();

        foreach ($navigation as $group) {
            $groupItems = collect($group->getItems())
                ->filter(fn ($item) => $item->isVisible() && (! $item->isHidden()))
                ->values();

            if (blank($group->getLabel())) {
                foreach ($groupItems as $item) {
                    $submenu = $flattenNavigationItem($item);
                    $manualSubmenu = $manualChildren($item->getLabel());

                    if (! empty($manualSubmenu)) {
                        $submenu = $manualSubmenu;
                    } elseif (count($submenu) === 1) {
                        $submenu = [];
                    }

                    $isMenuActive = $item->isActive()
                        || $item->isChildItemsActive()
                        || collect($submenu)->contains(fn (array $subItem): bool => $subItem['active']);

                    $menuItems->push([
                        'label' => $item->getLabel(),
                        'icon' => $isMenuActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon(),
                        'url' => $item->getUrl(),
                        'active' => $isMenuActive,
                        'open_in_new_tab' => $item->shouldOpenUrlInNewTab(),
                        'submenu' => $submenu,
                    ]);
                }

                continue;
            }

            if ($groupItems->isEmpty()) {
                continue;
            }

            $submenu = [];

            foreach ($groupItems as $item) {
                $submenu = [
                    ...$submenu,
                    ...$flattenNavigationItem($item),
                ];
            }

            $isMenuActive = $group->isActive()
                || collect($submenu)->contains(fn (array $subItem): bool => $subItem['active']);

            $menuItems->push([
                'label' => $group->getLabel(),
                'icon' => $group->getIcon() ?: ($groupIcons[$group->getLabel()] ?? null),
                'url' => $groupItems->first()?->getUrl(),
                'active' => $isMenuActive,
                'open_in_new_tab' => $groupItems->first()?->shouldOpenUrlInNewTab() ?? false,
                'submenu' => $submenu,
            ]);
        }
    @endphp

    <aside
        x-data="{
            flyoutOpen: false,
            flyoutTop: 0,
            flyoutLeft: 0,
            flyoutLabel: '',
            flyoutItems: [],
            flyoutHideTimer: null,

            init() {
                window.wpAdminSidebarFlyout = this
            },

            isDesktop() {
                return window.innerWidth >= 1024
            },

            closeFlyout() {
                this.flyoutOpen = false
                this.flyoutItems = []
                this.flyoutLabel = ''
            },

            queueCloseFlyout() {
                clearTimeout(this.flyoutHideTimer)
                this.flyoutHideTimer = setTimeout(() => this.closeFlyout(), 250)
            },

            cancelCloseFlyout() {
                clearTimeout(this.flyoutHideTimer)
            },

            openFlyout(trigger, item) {
                if (! this.isDesktop()) {
                    return
                }

                if (! trigger || ! item) {
                    return
                }

                this.cancelCloseFlyout()

                const rect = trigger.getBoundingClientRect()
                const topGap = 8
                const bottomGap = 12
                const leftGap = 8
                const rightGap = 8

                this.flyoutLabel = item.label ?? ''
                this.flyoutItems = Array.isArray(item.submenu) ? item.submenu : []

                if (! this.flyoutItems.length) {
                    this.closeFlyout()
                    return
                }

                this.flyoutOpen = true
                this.flyoutTop = Math.round(rect.top - 16)
                this.flyoutLeft = Math.round(rect.right - 2)

                this.$nextTick(() => {
                    const flyout = this.$refs.flyoutPanel

                    if (! flyout) {
                        return
                    }

                    const panelRect = flyout.getBoundingClientRect()

                    let nextTop = rect.top - 30
                    
                    const maxTop = window.innerHeight - panelRect.height - bottomGap
                    nextTop = Math.min(nextTop, maxTop)
                    nextTop = Math.max(topGap, nextTop)

                    let nextLeft = rect.right - 2

                    if ((nextLeft + panelRect.width + rightGap) > window.innerWidth) {
                        nextLeft = rect.left - panelRect.width + 4
                    }

                    nextLeft = Math.max(leftGap, nextLeft)

                    this.flyoutTop = Math.round(nextTop)
                    this.flyoutLeft = Math.round(nextLeft)
                })
            }
        }"
        x-init="init()"
        @keydown.escape.window="closeFlyout()"
        @scroll.window="closeFlyout()"
        @resize.window="closeFlyout()"
        @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
            x-cloak
        @else
            x-cloak="-lg"
        @endif
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        class="fi-sidebar fi-main-sidebar fi-wp-admin-sidebar"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_START) }}

        <nav class="fi-sidebar-nav fi-wp-admin-nav-ctn">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

            <ul class="fi-wp-admin-nav">
                @foreach ($menuItems as $menuItem)
                    @php
                        $hasSubmenu = count($menuItem['submenu']) > 0;
                    @endphp

                    <li
                        x-data="{
                            isDesktop() {
                                return window.innerWidth >= 1024
                            },
                            mobileOpen: @js($menuItem['active']),
                            toggleMobile(event) {
                                if (this.isDesktop() || ! @js($hasSubmenu)) {
                                    return
                                }

                                event.preventDefault()
                                this.mobileOpen = ! this.mobileOpen
                            }
                        }"
                        @mouseenter="
                            if (window.wpAdminSidebarFlyout) {
                                window.wpAdminSidebarFlyout.cancelCloseFlyout()

                                @if ($hasSubmenu && ! $menuItem['active'])
                                    window.wpAdminSidebarFlyout.openFlyout($el, {
                                        label: @js($menuItem['label']),
                                        submenu: @js($menuItem['submenu']),
                                    })
                                @else
                                    window.wpAdminSidebarFlyout.closeFlyout()
                                @endif
                            }
                        "
                        @mouseleave="
                            if (window.wpAdminSidebarFlyout) {
                                window.wpAdminSidebarFlyout.queueCloseFlyout()
                            }
                        "
                        @class([
                            'fi-wp-admin-item',
                            'is-current' => $menuItem['active'],
                            'has-submenu' => $hasSubmenu,
                        ])
                    >
                        <a
                            {{ \Filament\Support\generate_href_html($menuItem['url'] ?: '#', $menuItem['open_in_new_tab']) }}
                            @click="toggleMobile($event)"
                            class="fi-wp-admin-link"
                        >
                            @if (filled($menuItem['icon']))
                                {{ \Filament\Support\generate_icon_html(
                                    $menuItem['icon'],
                                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-wp-admin-link-icon']),
                                    size: \Filament\Support\Enums\IconSize::Large,
                                ) }}
                            @else
                                <span class="fi-wp-admin-link-icon fi-wp-admin-link-icon--empty"></span>
                            @endif

                            <span class="fi-wp-admin-link-label">{{ $menuItem['label'] }}</span>

                            @if ($hasSubmenu)
                                {{ \Filament\Support\generate_icon_html(
                                    \Filament\Support\Icons\Heroicon::ChevronRight,
                                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-wp-admin-link-arrow']),
                                ) }}
                            @endif
                        </a>

                        @if ($hasSubmenu)
                            @if ($menuItem['active'])
                                <div class="fi-wp-admin-inline-submenu">
                                    <ul class="fi-wp-admin-submenu fi-wp-admin-submenu--inline">
                                        @foreach ($menuItem['submenu'] as $subItem)
                                            <li
                                                @class([
                                                    'fi-wp-admin-submenu-item',
                                                    'is-current' => $subItem['active'],
                                                ])
                                                style="--wp-menu-depth: {{ $subItem['depth'] }};"
                                            >
                                                <a
                                                    {{ \Filament\Support\generate_href_html($subItem['url'] ?: '#', $subItem['open_in_new_tab'] ?? false) }}
                                                    class="fi-wp-admin-submenu-link"
                                                >
                                                    <span class="fi-wp-admin-submenu-label">{{ $subItem['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <div
                                    x-cloak
                                    x-show="! isDesktop() && mobileOpen"
                                    x-transition.opacity.duration.120ms
                                    class="fi-wp-admin-inline-submenu"
                                >
                                    <ul class="fi-wp-admin-submenu fi-wp-admin-submenu--inline">
                                        @foreach ($menuItem['submenu'] as $subItem)
                                            <li
                                                @class([
                                                    'fi-wp-admin-submenu-item',
                                                    'is-current' => $subItem['active'],
                                                ])
                                                style="--wp-menu-depth: {{ $subItem['depth'] }};"
                                            >
                                                <a
                                                    {{ \Filament\Support\generate_href_html($subItem['url'] ?: '#', $subItem['open_in_new_tab'] ?? false) }}
                                                    class="fi-wp-admin-submenu-link"
                                                >
                                                    <span class="fi-wp-admin-submenu-label">{{ $subItem['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>

            <div
                x-cloak
                x-ref="flyoutPanel"
                x-show="flyoutOpen && isDesktop() && flyoutItems.length > 0"
                x-transition.opacity.duration.100ms
                @mouseenter="cancelCloseFlyout()"
                @mouseleave="queueCloseFlyout()"
                class="fi-wp-admin-flyout"
                x-bind:class="{ 'is-open': flyoutOpen && isDesktop() && flyoutItems.length > 0 }"
                x-bind:style="`top:${flyoutTop}px; left:${flyoutLeft}px;`"
            >
                <div class="fi-wp-admin-flyout-title" x-text="flyoutLabel"></div>

                <ul class="fi-wp-admin-submenu">
                    <template x-for="(subItem, index) in flyoutItems" :key="`${subItem.label}-${index}`">
                        <li
                            class="fi-wp-admin-submenu-item"
                            :class="{ 'is-current': !! subItem.active }"
                            :style="`--wp-menu-depth: ${subItem.depth ?? 0};`"
                        >
                            <a
                                class="fi-wp-admin-submenu-link"
                                :href="subItem.url || '#'"
                                :target="subItem.open_in_new_tab ? '_blank' : null"
                                :rel="subItem.open_in_new_tab ? 'noopener noreferrer' : null"
                            >
                                <span class="fi-wp-admin-submenu-label" x-text="subItem.label"></span>
                            </a>
                        </li>
                    </template>
                </ul>
            </div>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
        </nav>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}
    </aside>

    <x-filament-actions::modals />
</div>