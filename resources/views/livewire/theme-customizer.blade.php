@php
    $themeTitle = ucfirst(str_replace('-', ' ', $theme));
@endphp

<div class="h-screen overflow-hidden bg-[#dcdcdd]" x-data="{
    notice: @js(session('customizer_notice')),
    controlsHidden: false,
    setNotice(message) {
        this.notice = message;
        setTimeout(() => { this.notice = null }, 2500);
    }
}" x-init="if (notice) { setTimeout(() => notice = null, 2500) }"
    x-on:customizer-notice.window="if ($event.detail && $event.detail.message) setNotice($event.detail.message)">

    <div class="grid h-full transition-all duration-200"
        :class="controlsHidden ? 'grid-cols-[0px_minmax(0,1fr)]' : 'grid-cols-[240px_minmax(0,1fr)]'">

        {{-- LEFT SIDEBAR --}}
        <aside x-show="!controlsHidden" x-transition
            class="flex h-full min-h-0 flex-col overflow-hidden border-r border-black/10 bg-[#f0f0f1] text-[13px] text-[#1d2327]">

            {{-- top bar --}}
            <div class="flex shrink-0 items-center justify-between border-b border-black/10 px-3 py-2">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                    wire:click="goRoot">
                    <svg class="h-5 w-5 text-[#50575e]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>

                <button type="button" wire:click="publish"
                    class="rounded border border-[#2271b1] bg-[#2271b1] px-3 py-1.5 text-[13px] font-medium text-white hover:bg-[#135e96]">
                    Publish
                </button>
            </div>

            {{-- scrollable content --}}
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-if="notice">
                    <div class="border-b border-black/10 bg-[#edfaef] px-3 py-2 text-[13px] text-[#1d2327]">
                        <span x-text="notice"></span>
                    </div>
                </template>

                {{-- ROOT --}}
                @if ($screen === 'root')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="text-[13px] text-[#50575e]">You are customizing</div>
                        <div class="mt-1 text-[16px] font-normal leading-6 text-[#50575e]">
                            {{ $themeTitle }}
                        </div>
                    </div>

                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="text-[13px] text-[#50575e]">Active theme</div>

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-[14px] font-semibold text-[#1d2327]">
                                    {{ $themeTitle }}
                                </div>
                            </div>

                            <button type="button" wire:click="openThemeBrowser"
                                class="shrink-0 rounded border border-[#2271b1] bg-white px-3 py-2 text-[13px] text-[#2271b1]">
                                Change
                            </button>
                        </div>
                    </div>

                    <div>
                        <button type="button" wire:click="openSection('typography')"
                            class="block w-full border-b border-black/10 px-3 py-4 text-left text-[16px] text-[#50575e] hover:bg-white/40">
                            <span class="flex items-center justify-between">
                                <span>Typography</span>
                                <span class="text-[20px] leading-none">›</span>
                            </span>
                        </button>

                        <button type="button" wire:click="openSection('site_identity')"
                            class="block w-full border-b border-black/10 px-3 py-4 text-left text-[16px] text-[#50575e] hover:bg-white/40">
                            <span class="flex items-center justify-between">
                                <span>Site Identity</span>
                                <span class="text-[20px] leading-none">›</span>
                            </span>
                        </button>

                        <button type="button" wire:click="openSection('menus')"
                            class="block w-full border-b border-black/10 px-3 py-4 text-left text-[16px] text-[#50575e] hover:bg-white/40">
                            <span class="flex items-center justify-between">
                                <span>Menus</span>
                                <span class="text-[20px] leading-none">›</span>
                            </span>
                        </button>

                        <button type="button" wire:click="openSection('homepage')"
                            class="block w-full border-b border-black/10 px-3 py-4 text-left text-[16px] text-[#50575e] hover:bg-white/40">
                            <span class="flex items-center justify-between">
                                <span>Homepage Settings</span>
                                <span class="text-[20px] leading-none">›</span>
                            </span>
                        </button>

                        <button type="button" wire:click="openSection('footer')"
                            class="block w-full border-b border-black/10 px-3 py-4 text-left text-[16px] text-[#50575e] hover:bg-white/40">
                            <span class="flex items-center justify-between">
                                <span>Footer</span>
                                <span class="text-[20px] leading-none">›</span>
                            </span>
                        </button>

                        <button type="button" wire:click="openSection('additional_css')"
                            class="block w-full border-b border-black/10 px-3 py-4 text-left text-[16px] text-[#50575e] hover:bg-white/40">
                            <span class="flex items-center justify-between">
                                <span>Additional CSS</span>
                                <span class="text-[20px] leading-none">›</span>
                            </span>
                        </button>
                    </div>
                @endif

                {{-- TYPOGRAPHY --}}
                @if ($screen === 'section' && $section === 'typography')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>

                            <div>
                                <div class="text-[13px] text-[#50575e]">You are customizing</div>
                                <div class="mt-1 text-[16px] leading-6 text-[#50575e]">Typography</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        @foreach (['headings' => 'Headings', 'strong' => 'Strong', 'paragraph' => 'Paragraph', 'list' => 'List', 'anchor' => 'Anchor'] as $key => $label)
                            <button type="button" wire:click="openSubsection('typography', '{{ $key }}')"
                                class="block w-full border-b border-black/10 px-3 py-4 text-left text-[15px] text-[#50575e] hover:bg-white/40">
                                <span class="flex items-center justify-between">
                                    <span>{{ $label }}</span>
                                    <span class="text-[20px] leading-none">›</span>
                                </span>
                            </button>
                        @endforeach

                        <div class="border-b border-black/10 px-3 py-4">
                            <div class="flex items-center justify-between text-[15px] text-[#50575e]">
                                <span>Import/Export</span>
                                <span class="text-[20px] leading-none">›</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TYPOGRAPHY SUBSECTIONS --}}
                @if ($screen === 'subsection' && $section === 'typography')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="openSection('typography')">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">{{ ucfirst($subsection) }}</div>
                        </div>
                    </div>

                    <div class="space-y-4 p-3">
                        @if (in_array($subsection, ['headings', 'strong', 'paragraph', 'list', 'anchor'], true))
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Font Family</label>
                                <select wire:model.live="data.typography.{{ $subsection }}.font_family"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                                    @foreach ($fontFamilyOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if (in_array($subsection, ['headings', 'paragraph'], true))
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Font Size</label>
                                <input type="text" wire:model.live="data.typography.{{ $subsection }}.font_size"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                            </div>
                        @endif

                        @if (in_array($subsection, ['headings', 'strong', 'paragraph'], true))
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Font Weight</label>
                                <input type="text"
                                    wire:model.live="data.typography.{{ $subsection }}.font_weight"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                            </div>
                        @endif

                        @if (in_array($subsection, ['headings', 'paragraph', 'list'], true))
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Line Height</label>
                                <input type="text"
                                    wire:model.live="data.typography.{{ $subsection }}.line_height"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                            </div>
                        @endif

                        @if ($subsection === 'headings')
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Text
                                    Transform</label>
                                <select wire:model.live="data.typography.headings.text_transform"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                                    <option value="none">None</option>
                                    <option value="uppercase">Uppercase</option>
                                    <option value="lowercase">Lowercase</option>
                                    <option value="capitalize">Capitalize</option>
                                </select>
                            </div>
                        @endif

                        @if ($subsection === 'anchor')
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Link Color</label>
                                <input type="color" wire:model.live="data.typography.anchor.color"
                                    class="h-10 w-full rounded border border-[#8c8f94] bg-white px-2">
                            </div>

                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Hover Color</label>
                                <input type="color" wire:model.live="data.typography.anchor.hover_color"
                                    class="h-10 w-full rounded border border-[#8c8f94] bg-white px-2">
                            </div>

                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Text
                                    Decoration</label>
                                <select wire:model.live="data.typography.anchor.text_decoration"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                                    <option value="none">None</option>
                                    <option value="underline">Underline</option>
                                    <option value="overline">Overline</option>
                                    <option value="line-through">Line Through</option>
                                </select>
                            </div>
                        @else
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Color</label>
                                <input type="color" wire:model.live="data.typography.{{ $subsection }}.color"
                                    class="h-10 w-full rounded border border-[#8c8f94] bg-white px-2">
                            </div>
                        @endif
                    </div>
                @endif

                {{-- SITE IDENTITY --}}
                @if ($screen === 'section' && $section === 'site_identity')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">Site Identity</div>
                        </div>
                    </div>

                    <div class="space-y-4 p-3">
                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Site Title</label>
                            <input type="text" wire:model.live="data.site_identity.site_title"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Tagline</label>
                            <input type="text" wire:model.live="data.site_identity.tagline"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                        </div>

                        <div>
                            <label class="mb-2 block text-[12px] font-semibold text-[#50575e]">Fav Icon</label>

                            <button type="button"
                                wire:click="openMediaBrowser('site_identity.site_icon_media_id', 'image')"
                                class="w-full rounded border border-[#2271b1] bg-white px-3 py-2 text-[13px] text-[#2271b1]">
                                Select Fav Icon
                            </button>

                            @if ($siteIconUrl)
                                <div class="mt-3 flex items-center gap-3">
                                    <img src="{{ $siteIconUrl }}" class="h-12 w-12 rounded border object-cover"
                                        alt="Fav icon">

                                    <button type="button" wire:click="clearMedia('site_identity.site_icon_media_id')"
                                        class="text-[12px] text-red-600">
                                        Remove
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="mb-2 block text-[12px] font-semibold text-[#50575e]">Logo</label>

                            <button type="button"
                                wire:click="openMediaBrowser('site_identity.logo_media_id', 'image')"
                                class="w-full rounded border border-[#2271b1] bg-white px-3 py-2 text-[13px] text-[#2271b1]">
                                Select Logo
                            </button>

                            @if ($logoUrl)
                                <div class="mt-3 space-y-3">
                                    <img src="{{ $logoUrl }}" class="max-h-16 rounded border object-contain"
                                        alt="Logo">

                                    <div class="flex items-center gap-3">
                                        <button type="button" wire:click="clearMedia('site_identity.logo_media_id')"
                                            class="text-[12px] text-red-600">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Logo Width</label>
                            <input type="number" min="20" max="600"
                                wire:model.live="data.site_identity.logo_width"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                        </div>
                    </div>
                @endif

                {{-- MENUS --}}
                @if ($screen === 'section' && $section === 'menus')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">Menus</div>
                        </div>
                    </div>

                    <div class="p-3">
                        @livewire('menu-builder', [], key('customizer-menu-builder'))
                    </div>
                @endif

                {{-- HOMEPAGE SETTINGS --}}
                @if ($screen === 'section' && $section === 'homepage')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">Homepage Settings</div>
                        </div>
                    </div>

                    <div class="space-y-4 p-3">
                        <div class="text-[13px] leading-6 text-[#50575e]">
                            You can choose what’s displayed on the homepage of your site.
                        </div>

                        <div class="space-y-3 text-[13px]">
                            <label class="flex items-center gap-3">
                                <input type="radio" value="latest_posts" wire:model.live="data.homepage.mode">
                                <span>Your latest posts</span>
                            </label>

                            <label class="flex items-center gap-3">
                                <input type="radio" value="static_page" wire:model.live="data.homepage.mode">
                                <span>A static page</span>
                            </label>
                        </div>

                        @if (data_get($data, 'homepage.mode') === 'static_page')
                            <div>
                                <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Homepage</label>
                                <select wire:model.live="data.homepage.page_id"
                                    class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                                    <option value="">Select page</option>
                                    @foreach ($pageOptions as $page)
                                        <option value="{{ $page['id'] }}">{{ $page['title'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- FOOTER --}}
                @if ($screen === 'section' && $section === 'footer')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">Footer</div>
                        </div>
                    </div>

                    <div class="space-y-4 p-3">
                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Background color</label>
                            <input type="color" wire:model.live="data.footer.background_color"
                                class="h-10 w-full rounded border border-[#8c8f94] bg-white px-2">
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Text Color</label>
                            <input type="color" wire:model.live="data.footer.text_color"
                                class="h-10 w-full rounded border border-[#8c8f94] bg-white px-2">
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Before
                                Copyright</label>
                            <textarea wire:model.live.debounce.300ms="data.footer.before_copyright" rows="4"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]"></textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Copyright Area</label>
                            <div class="mb-2 text-[11px] italic text-[#50575e]">
                                [name] - Site Name<br>
                                [Y] - Year<br>
                                [sitemap] - Sitemap Link
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="data.footer.copyright_area"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Second line</label>
                            <input type="text" wire:model.live.debounce.300ms="data.footer.second_line"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                        </div>

                        <div>
                            <label class="mb-1 block text-[12px] font-semibold text-[#50575e]">Copyright Text
                                Alignment</label>
                            <select wire:model.live="data.footer.text_alignment"
                                class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 text-[13px]">
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                                <option value="justify">Justify</option>
                            </select>
                        </div>
                    </div>
                @endif

                {{-- ADDITIONAL CSS --}}
                @if ($screen === 'section' && $section === 'additional_css')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">Additional CSS</div>
                        </div>
                    </div>

                    <div class="space-y-4 p-3">
                        <div class="text-[13px] leading-6 text-[#50575e]">
                            Add your own CSS code here to customize the appearance and layout of your site.
                        </div>

                        <textarea wire:model.live.debounce.500ms="data.additional_css" rows="14"
                            class="w-full rounded border border-[#8c8f94] bg-white px-3 py-2 font-mono text-[12px]"></textarea>
                    </div>
                @endif

                {{-- THEME BROWSER --}}
                @if ($screen === 'themes')
                    <div class="border-b border-black/10 px-3 py-4">
                        <div class="flex items-center gap-3">
                            <button type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-black/5"
                                wire:click="goRoot">
                                <svg class="h-5 w-5 text-[#2271b1]" viewBox="0 0 24 24" fill="none">
                                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <div class="text-[16px] leading-6 text-[#50575e]">Themes</div>
                        </div>
                    </div>

                    <div class="space-y-3 p-3">
                        @foreach ($themes as $slug => $t)
                            @php
                                $manifest = $t['manifest'] ?? [];
                                $name = $manifest['name'] ?? $slug;
                                $isActive = $theme === $slug;
                            @endphp

                            <div class="border border-black/10 bg-white">
                                <div class="p-3">
                                    <div class="font-semibold text-[#1d2327]">{{ $name }}</div>
                                    <div class="mt-1 text-[11px] text-[#50575e]">{{ $slug }}</div>

                                    <div class="mt-4 flex justify-end">
                                        @if ($isActive)
                                            <button type="button"
                                                class="rounded border border-[#2271b1] bg-[#2271b1] px-3 py-2 text-[13px] text-white">
                                                Customize
                                            </button>
                                        @else
                                            <button type="button" wire:click="activateTheme('{{ $slug }}')"
                                                class="rounded border border-[#2271b1] bg-white px-3 py-2 text-[13px] text-[#2271b1]">
                                                Activate
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- bottom controls --}}
            <div class="shrink-0 border-t border-black/10 px-3 py-3 text-[#50575e]">
                <div class="flex items-center justify-between">
                    <button type="button" class="inline-flex items-center gap-2 text-[13px] hover:text-[#1d2327]"
                        x-on:click="controlsHidden = true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        Hide Controls
                    </button>

                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="$set('device','desktop')" class="text-[16px]"
                            title="Desktop">🖥️</button>
                        <button type="button" wire:click="$set('device','tablet')" class="text-[16px]"
                            title="Tablet">📱</button>
                        <button type="button" wire:click="$set('device','mobile')" class="text-[16px]"
                            title="Mobile">📲</button>
                    </div>
                </div>
            </div>
        </aside>

        {{-- RIGHT PREVIEW --}}
        <main class="relative h-full overflow-auto bg-[#dcdcdd] p-4">
            <button x-show="controlsHidden" x-transition type="button"
                class="absolute left-4 top-4 z-20 inline-flex items-center gap-2 rounded border border-black/10 bg-white px-3 py-2 text-[13px] text-[#50575e] shadow"
                x-on:click="controlsHidden = false">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
                Show Controls
            </button>

            <div class="mx-auto {{ $this->iframeWidthClass }} h-full bg-white shadow">
                <iframe id="customizerPreview" src="{{ $this->previewUrl }}"
                    class="h-[calc(100vh-2rem)] w-full border-0"></iframe>
            </div>
        </main>
    </div>

    {{-- Shared media browser --}}
    <div class="hidden">
        @livewire(
            'media-browser',
            [
                'multiple' => false,
                'eventName' => 'media-picker-selected',
                'source' => 'theme-customizer',
                'type' => 'image',
            ],
            key('customizer-media-browser')
        )
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('customizer-refresh', () => {
                const iframe = document.getElementById('customizerPreview');
                if (!iframe) return;
                const url = new URL(iframe.src);
                url.searchParams.set('_t', Date.now());
                iframe.src = url.toString();
            });

            Livewire.on('notify', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                if (!data || !data.message) return;
                window.dispatchEvent(new CustomEvent('customizer-notice', {
                    detail: data
                }));
            });
        });
    </script>
</div>
