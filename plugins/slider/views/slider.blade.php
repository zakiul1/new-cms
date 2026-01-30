@php
    /** @var \App\Models\Slider $slider */
    /** @var \Illuminate\Support\Collection $slides */
    /** @var array $opts */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $mediaMap */

    $id = 'cms-slider-' . (int) $slider->id;

    $settings = is_array($slider->settings_json ?? null) ? $slider->settings_json : [];

    $autoplay = data_get($opts, 'autoplay', true) ? '1' : '0';
    $delay = (int) data_get($opts, 'delay', 6000);
    $height = (string) data_get($opts, 'height', '');

    $heroKicker = (string) data_get($settings, 'hero_kicker', '');
    $heroTitle = (string) data_get($settings, 'hero_title', '');
    $heroSubtitle = (string) data_get($settings, 'hero_subtitle', '');
    $heroButtons = is_array(data_get($settings, 'hero_buttons')) ? (array) data_get($settings, 'hero_buttons') : [];

    $showIndicators = (bool) data_get($settings, 'show_indicators', true);
    $indicatorStyle = in_array(data_get($settings, 'indicator_style', 'dots'), ['dots', 'lines'], true)
        ? (string) data_get($settings, 'indicator_style', 'dots')
        : 'dots';

    $showNav = (bool) data_get($settings, 'show_navigation', true);
@endphp

<section id="{{ $id }}" class="relative w-full overflow-hidden pt-[30px]" data-cms-slider
    data-autoplay="{{ $autoplay }}" data-delay="{{ $delay }}">
    <div class="relative mx-auto max-w-6xl px-10 py-10 lg:py-16 bg-gray-100">
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div class="max-w-xl">
                @if ($heroKicker !== '')
                    <div class="text-sm font-semibold tracking-wide text-slate-600">
                        {{ $heroKicker }}
                    </div>
                @endif

                @if ($heroTitle !== '')
                    <h2 class="mt-2 text-4xl font-bold leading-tight text-slate-700 lg:text-5xl">
                        {!! nl2br(e($heroTitle)) !!}
                    </h2>
                @endif

                @if ($heroSubtitle !== '')
                    <p class="mt-6 text-base font-semibold italic text-slate-700">
                        {!! nl2br(e($heroSubtitle)) !!}
                    </p>
                @endif

                @if (!empty($heroButtons))
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @foreach ($heroButtons as $btn)
                            @php
                                $text = trim((string) data_get($btn, 'text', ''));
                                $url = trim((string) data_get($btn, 'url', ''));
                                $newTab = (bool) data_get($btn, 'new_tab', false);
                                $style = (string) data_get($btn, 'style', 'primary');

                                if ($text === '' || $url === '') {
                                    continue;
                                }

                                $cls = match ($style) {
                                    'secondary' => 'bg-slate-700 hover:bg-slate-800 text-white',
                                    'outline' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
                                    default => 'bg-blue-700 hover:bg-blue-800 text-white',
                                };
                            @endphp

                            <a href="{{ $url }}"
                                class="inline-flex items-center justify-center rounded-md px-5 py-3 text-sm font-semibold {{ $cls }}"
                                @if ($newTab) target="_blank" rel="noopener" @endif>
                                {{ $text }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="relative">
                <div class="relative w-full">
                    @foreach ($slides as $i => $s)
                        @php
                            $media = $s->media_id ? $mediaMap[$s->media_id] ?? null : null;
                            $isActive = $i === 0;
                            $imgStyle = $height !== '' ? 'style="height:' . e($height) . ';"' : '';
                        @endphp

                        <div class="cms-slide {{ $isActive ? '' : 'hidden' }}" data-cms-slide
                            aria-hidden="{{ $isActive ? 'false' : 'true' }}">
                            <div class="w-full overflow-hidden rounded-md bg-white" {!! $imgStyle !!}>
                                <div class="aspect-[16/9] w-full bg-white p-0" {!! $imgStyle !!}>
                                    @if ($media && method_exists($media, 'isImage') && $media->isImage())
                                        {!! cms_picture(
                                            $media,
                                            [
                                                'alt' => e((string) ($media->alt ?? ($media->title ?? ''))),
                                                'class' => 'h-full w-full object-contain',
                                                'sizes' => '(max-width: 1024px) 100vw, 720px',
                                                'loading' => $i === 0 ? 'eager' : 'lazy',
                                                'decoding' => 'async',
                                            ],
                                            'large',
                                            ['thumb', 'medium', 'large'],
                                        ) !!}
                                    @else
                                        <div class="h-full w-full bg-slate-100"></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($showNav)
                    <button type="button"
                        class="absolute -left-6 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-xl shadow hover:bg-white"
                        data-cms-prev aria-label="Previous slide">‹</button>

                    <button type="button"
                        class="absolute -right-6 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-xl shadow hover:bg-white"
                        data-cms-next aria-label="Next slide">›</button>
                @endif

                @if ($showIndicators)
                    @php
                        $isDots = $indicatorStyle === 'dots';
                        $base = $isDots ? 'h-2.5 w-2.5 rounded-full' : 'h-1 w-10 rounded-full';
                        $activeClass = 'bg-slate-700';
                        $inactiveClass = 'bg-slate-300';
                    @endphp

                    <div class="mt-4 flex justify-center gap-2" aria-label="Slider indicators">
                        @foreach ($slides as $i => $s)
                            <button type="button"
                                class="{{ $base }} {{ $i === 0 ? $activeClass : $inactiveClass }}"
                                data-cms-dot="{{ $i }}" data-active-class="{{ $activeClass }}"
                                data-inactive-class="{{ $inactiveClass }}"
                                aria-label="Go to slide {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
