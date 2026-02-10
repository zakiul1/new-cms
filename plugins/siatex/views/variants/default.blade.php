@php
    $layout = (string) ($settings['layout'] ?? 'image-left');
    $height = (string) ($settings['height'] ?? '560px');

    $showNav = (bool) ($settings['show_navigation'] ?? true);
    $showDots = (bool) ($settings['show_indicators'] ?? true);
    $indicatorStyle = (string) ($settings['indicator_style'] ?? 'flat'); // keep flat like screenshot

    $delay = (int) ($settings['delay'] ?? 5000);
    $bgColor = (string) ($settings['bg_color'] ?? '#f5f5f5');

    $title = (string) ($settings['title'] ?? '');
    $subtitle = (string) ($settings['subtitle'] ?? '');

    $slideCount = $slides->count();

    // Desktop order only (mobile ALWAYS image top)
    $imageOrderDesktop = $layout === 'image-left' ? 'lg:order-1' : 'lg:order-2';
    $contentOrderDesktop = $layout === 'image-left' ? 'lg:order-2' : 'lg:order-1';
@endphp

<div class="w-full overflow-hidden" data-slider data-delay="{{ $delay }}">
    {{-- ✅ Background + padding only inside cms-container --}}
    <div class="cms-container mx-auto px-6 sm:px-8 lg:px-10 py-10 lg:py-14" style="background: {{ $bgColor }};">
        {{-- ✅ Height applies to BOTH sections --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 items-center px-8 py-10"
            style="min-height: {{ $height }};">
            {{-- ✅ IMAGE FIRST ALWAYS on mobile --}}
            <div class="order-1 {{ $imageOrderDesktop }}" data-image-area>
                <div class="w-full">
                    {{-- Image box uses the available height but won’t overflow --}}
                    <div class="relative w-full overflow-hidden bg-white" style="height: clamp(260px, 42vw, 420px);">
                        {{-- Track --}}
                        <div class="h-full w-full flex transition-transform duration-500 ease-out will-change-transform"
                            data-slider-track style="transform: translateX(0%);">
                            @foreach ($slides as $slide)
                                @php
                                    $imgUrl = $slide->media?->variantUrl('large') ?: $slide->media?->url();
                                @endphp

                                <div class="min-w-full h-full flex items-center justify-center" data-slide>
                                    @if ($imgUrl)
                                        <img src="{{ $imgUrl }}" alt=""
                                            class="w-full h-full object-cover select-none" draggable="false"
                                            loading="lazy" />
                                    @else
                                        <div class="text-sm text-gray-500">No image</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Nav buttons --}}
                        @if ($showNav && $slideCount > 1)
                            <button type="button"
                                class="absolute left-1 top-1/2 -translate-y-1/2 h-9 w-9 sm:h-10 sm:w-10 flex items-center justify-center rounded-full bg-white/50 hover:bg-white/80 shadow-md"
                                data-prev aria-label="Previous slide">
                                {{-- ✅ SVG Left Arrow --}}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                    class="h-6 w-6 text-gray-800" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 18l-6-6 6-6" />
                                </svg>
                            </button>

                            <button type="button"
                                class="absolute right-1 top-1/2 -translate-y-1/2 h-9 w-9 sm:h-10 sm:w-10  flex items-center justify-center rounded-full bg-white/50 hover:bg-white/80 shadow-md"
                                data-next aria-label="Next slide">
                                {{-- ✅ SVG Right Arrow --}}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                    class="h-6 w-6 text-gray-800" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 6l6 6-6 6" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    {{-- ✅ Indicators UNDER image (like screenshot) --}}
                    @if ($showDots && $slideCount > 1)
                        <div class="mt-5 flex items-center justify-center gap-2.5" data-indicators>
                            @foreach ($slides as $i => $s)
                                @if ($indicatorStyle === 'flat')
                                    <button type="button"
                                        class="h-[3px] w-6 rounded-full bg-gray-400/40 hover:bg-gray-400/70 transition"
                                        data-dot="{{ $i }}"
                                        aria-label="Go to slide {{ $i + 1 }}"></button>
                                @else
                                    <button type="button"
                                        class="h-[7px] w-[7px] rounded-full bg-gray-400/40 hover:bg-gray-400/70 transition"
                                        data-dot="{{ $i }}"
                                        aria-label="Go to slide {{ $i + 1 }}"></button>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ✅ CONTENT SECOND on mobile ALWAYS --}}
            <div class="order-2 {{ $contentOrderDesktop }}">
                <div class="text-center lg:text-left">
                    @if ($title !== '')
                        <h2
                            class="text-[34px] sm:text-[42px] md:text-[54px] leading-[1.08] font-semibold tracking-tight text-gray-600">
                            {!! nl2br(e($title)) !!}
                        </h2>
                    @endif

                    @if ($subtitle !== '')
                        <p
                            class="mt-6 text-[14px] sm:text-[16px] md:text-[18px] text-gray-600 italic font-medium leading-relaxed">
                            {!! nl2br(e($subtitle)) !!}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
