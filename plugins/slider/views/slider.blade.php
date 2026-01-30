@php
    /** @var \App\Models\Slider $slider */
    /** @var \Illuminate\Support\Collection $slides */
    /** @var array $opts */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $mediaMap */

    $id = 'cms-slider-' . (int) $slider->id;

    $autoplay = data_get($opts, 'autoplay', true) ? '1' : '0';
    $delay = (int) data_get($opts, 'delay', 6000);
@endphp

<section
    id="{{ $id }}"
    class="relative w-full overflow-hidden pt-[30px]"
    data-cms-slider
    data-autoplay="{{ $autoplay }}"
    data-delay="{{ $delay }}"
>
    <div class="relative mx-auto max-w-6xl px-10 py-10 lg:py-16 bg-gray-100">
        {{-- 50/50 layout --}}
        <div class="grid items-center gap-10 lg:grid-cols-2">
            {{-- LEFT: hard-coded content (same as screenshot) --}}
            <div class="max-w-xl">
                <div class="text-sm font-semibold tracking-wide text-slate-600">
                 
                </div>

                <h2 class="mt-2 text-4xl font-bold leading-tight text-slate-700 lg:text-5xl">
                       YOUR
                    RELIABLE<br>
                    PARTNER IN<br>
                    GARMENT<br>
                    MANUFACTURING
                </h2>

                <p class="mt-6 text-base font-semibold italic text-slate-700">
                    Expertise in samples, production, and quality control —
                    delivering excellence worldwide.
                </p>

                {{-- Optional: keep button dynamic per-slide (only if first slide has button) --}}
                @php
                    $first = $slides->first();
                @endphp

                @if ($first && !empty($first->button_text) && !empty($first->button_url))
                    <a href="{{ $first->button_url }}"
                       class="mt-8 inline-flex items-center justify-center rounded-md bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800"
                       @if(!empty($first->button_new_tab)) target="_blank" rel="noopener" @endif
                    >
                        {{ $first->button_text }}
                    </a>
                @endif
            </div>

            {{-- RIGHT: slider image area --}}
            <div class="relative">
                {{-- Slides --}}
                <div class="relative w-full">
                    @foreach ($slides as $i => $s)
                        @php
                            $media = $s->media_id ? ($mediaMap[$s->media_id] ?? null) : null;
                            $isActive = $i === 0;
                        @endphp

                        <div
                            class="cms-slide {{ $isActive ? '' : 'hidden' }}"
                            data-cms-slide
                            aria-hidden="{{ $isActive ? 'false' : 'true' }}"
                        >
                            {{-- Fixed “premium” frame: no crop (contain) --}}
                            <div class="w-full overflow-hidden rounded-md bg-white">
                                <div class="aspect-[16/9] w-full bg-white p-0">
                                    @if ($media && method_exists($media, 'isImage') && $media->isImage())
                                        {!! cms_picture($media, [
                                            'alt' => e((string) ($s->title ?? '')),
                                            'class' => 'h-full w-full object-contain',  // ✅ no crop
                                            'sizes' => '(max-width: 1024px) 100vw, 720px',
                                            'loading' => $i === 0 ? 'eager' : 'lazy',
                                            'decoding' => 'async',
                                        ], 'large', ['thumb','medium','large']) !!}
                                    @else
                                        <div class="h-full w-full bg-slate-100"></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Arrows (on image side, centered, slightly outside) --}}
                <button
                    type="button"
                    class="absolute -left-6 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-xl shadow hover:bg-white"
                    data-cms-prev
                    aria-label="Previous slide"
                >
                    ‹
                </button>

                <button
                    type="button"
                    class="absolute -right-6 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-xl shadow hover:bg-white"
                    data-cms-next
                    aria-label="Next slide"
                >
                    ›
                </button>

                {{-- Dots --}}
                <div class="mt-4 flex justify-center gap-2" aria-label="Slider indicators">
                    @foreach ($slides as $i => $s)
                        <button
                            type="button"
                            class="h-2.5 w-2.5 rounded-full {{ $i === 0 ? 'bg-slate-700' : 'bg-slate-300' }}"
                            data-cms-dot="{{ $i }}"
                            aria-label="Go to slide {{ $i + 1 }}"
                        ></button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
