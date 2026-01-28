<section class="hero">
    <div class="cms-container hero__grid">
        <div class="hero__left">
            <h1 class="hero__headline">{{ $headline }}</h1>
            @if($subtext)
                <p class="hero__subtext">{{ $subtext }}</p>
            @endif
        </div>

        <div class="hero__right">
            <div class="hero__slider">
                {{-- Later we will add real slides (media IDs / variants) --}}
                <div class="hero__slide">SLIDE 1</div>
                <div class="hero__slide">SLIDE 2</div>
                <div class="hero__slide">SLIDE 3</div>
            </div>
        </div>
    </div>
</section>
