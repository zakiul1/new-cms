(() => {
    console.log("✅ Siatex slider JS loaded");

    function clamp(min, val, max) {
        return Math.max(min, Math.min(val, max));
    }

    function numAttr(el, name, fallback) {
        const v = el.getAttribute(name);
        if (v === null || v === undefined || v === "") return fallback;
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : fallback;
    }

    function intAttr(el, name, fallback) {
        const v = el.getAttribute(name);
        if (v === null || v === undefined || v === "") return fallback;
        const n = parseInt(v, 10);
        return Number.isFinite(n) ? n : fallback;
    }

    // ---------------------------
    // Cinematic Pro (Swiper + GSAP)
    // ---------------------------
    function initCinematicPro(root) {
        const el = root.querySelector("[data-pro-swiper]");
        if (!el) return;

        // Require Swiper + GSAP
        if (typeof Swiper === "undefined" || typeof gsap === "undefined") {
            console.warn(
                "⚠️ Cinematic Pro requires Swiper + GSAP. Not loaded.",
            );
            return;
        }

        const delay = Math.max(1000, intAttr(root, "data-delay", 6500));
        const fadeMs = Math.max(100, intAttr(root, "data-fade-ms", 900));

        const motionDuration = Math.max(
            2,
            numAttr(root, "data-motion-duration", 6.5),
        );
        const zoomMin = Math.max(1, numAttr(root, "data-zoom-min", 1.06));
        const zoomMax = Math.max(zoomMin, numAttr(root, "data-zoom-max", 1.18));
        const pan = Math.max(0, numAttr(root, "data-pan", 2.0)); // %
        const rotate = Math.max(0, numAttr(root, "data-rotate", 0.6)); // deg

        const prev = root.querySelector("[data-pro-prev]");
        const next = root.querySelector("[data-pro-next]");
        const dots = root.querySelector("[data-pro-dots]");

        // ✅ Swiper-safe config:
        // Always provide pagination/navigation objects, but disable them if elements are missing.
        const swiperConfig = {
            effect: "fade",
            fadeEffect: { crossFade: true },
            speed: fadeMs,
            loop: true,
            allowTouchMove: true,
            autoplay: { delay, disableOnInteraction: false },

            pagination: {
                el: dots || undefined, // undefined makes Swiper skip attaching
                clickable: true,
                enabled: !!dots,
            },

            navigation: {
                prevEl: prev || undefined,
                nextEl: next || undefined,
                enabled: !!(prev && next),
            },
        };

        const swiper = new Swiper(el, swiperConfig);

        let activeTween = null;

        function rand(a, b) {
            return a + Math.random() * (b - a);
        }

        function killTween() {
            if (activeTween) {
                activeTween.kill();
                activeTween = null;
            }
        }

        function animateActive() {
            killTween();

            const slide = el.querySelector(".swiper-slide-active");
            if (!slide) return;

            const img = slide.querySelector(".si-pro__img");
            if (!img) return;

            const startScale = rand(zoomMin, zoomMax);
            const endScale = rand(zoomMin, zoomMax);

            const startX = rand(-pan, pan);
            const startY = rand(-pan, pan);
            const endX = rand(-pan, pan);
            const endY = rand(-pan, pan);

            const startR = rand(-rotate, rotate);
            const endR = rand(-rotate, rotate);

            gsap.set(img, {
                scale: startScale,
                xPercent: startX,
                yPercent: startY,
                rotation: startR,
                transformOrigin: "center center",
                willChange: "transform",
                filter: "contrast(1.02) saturate(1.03)",
            });

            activeTween = gsap.to(img, {
                duration: motionDuration,
                scale: endScale,
                xPercent: endX,
                yPercent: endY,
                rotation: endR,
                ease: "power2.inOut",
            });
        }

        swiper.on("slideChangeTransitionStart", killTween);
        swiper.on("slideChangeTransitionEnd", animateActive);

        // Pause on hover
        root.addEventListener("mouseenter", () => swiper.autoplay?.stop());
        root.addEventListener("mouseleave", () => swiper.autoplay?.start());

        // Kick first animation
        animateActive();
    }

    // ---------------------------
    // Default mode (translate track slider)
    // ---------------------------
    function initTranslate(root) {
        const track = root.querySelector("[data-slider-track]");
        if (!track) return;

        const slides = Array.from(
            track.querySelectorAll(":scope > [data-slide]"),
        );
        const prevBtn = root.querySelector("[data-prev]");
        const nextBtn = root.querySelector("[data-next]");
        const dotsWrap = root.querySelector("[data-indicators]");
        const dots = dotsWrap
            ? Array.from(dotsWrap.querySelectorAll("[data-dot]"))
            : [];

        const count = slides.length;
        if (count <= 1) {
            if (dots[0]) dots[0].classList.add("is-active");
            return;
        }

        let index = 0;
        let timer = null;

        const delay = Math.max(1000, intAttr(root, "data-delay", 5000));

        function setActiveDot(i) {
            if (!dots.length) return;

            dots.forEach((btn, idx) => {
                btn.classList.toggle("is-active", idx === i);

                if (idx === i) {
                    btn.classList.add("bg-gray-600/60");
                    btn.classList.remove("bg-gray-400/40");
                } else {
                    btn.classList.remove("bg-gray-600/60");
                    btn.classList.add("bg-gray-400/40");
                }
            });
        }

        function goTo(i) {
            index = (i + count) % count;
            track.style.transform = `translateX(-${index * 100}%)`;
            setActiveDot(index);
        }

        function next() {
            goTo(index + 1);
        }
        function prev() {
            goTo(index - 1);
        }

        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function start() {
            stop();
            timer = setInterval(next, delay);
        }

        if (nextBtn) {
            nextBtn.addEventListener("click", (e) => {
                e.preventDefault();
                next();
                start();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener("click", (e) => {
                e.preventDefault();
                prev();
                start();
            });
        }

        dots.forEach((btn) => {
            btn.addEventListener("click", (e) => {
                e.preventDefault();
                const i = intAttr(btn, "data-dot", 0);
                goTo(clamp(0, i, count - 1));
                start();
            });
        });

        root.addEventListener("mouseenter", stop);
        root.addEventListener("mouseleave", start);

        const touchTarget = root.querySelector("[data-image-area]") || root;

        let startX = 0;
        let moved = false;

        touchTarget.addEventListener(
            "touchstart",
            (e) => {
                if (!e.touches || !e.touches.length) return;
                startX = e.touches[0].clientX;
                moved = false;
                stop();
            },
            { passive: true },
        );

        touchTarget.addEventListener(
            "touchmove",
            () => {
                moved = true;
            },
            { passive: true },
        );

        touchTarget.addEventListener(
            "touchend",
            (e) => {
                if (!moved) {
                    start();
                    return;
                }
                if (!e.changedTouches || !e.changedTouches.length) {
                    start();
                    return;
                }

                const endX = e.changedTouches[0].clientX;
                const diff = endX - startX;

                if (Math.abs(diff) > 40) {
                    if (diff < 0) next();
                    else prev();
                }
                start();
            },
            { passive: true },
        );

        goTo(0);
        start();
    }

    function initSlider(root) {
        const mode = (root.getAttribute("data-mode") || "").toLowerCase();

        if (mode === "cinematic-pro") {
            initCinematicPro(root);
            return;
        }

        // default
        initTranslate(root);
    }

    function boot() {
        document.querySelectorAll("[data-slider]").forEach(initSlider);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }

    console.log("✅ Siatex Slider initialized");
})();
