(function () {
    function initSlider(root) {
        const slides = Array.from(root.querySelectorAll("[data-cms-slide]"));
        const dots = Array.from(root.querySelectorAll("[data-cms-dot]"));
        const prev = root.querySelector("[data-cms-prev]");
        const next = root.querySelector("[data-cms-next]");

        if (!slides.length) return;

        let index = 0;

        const autoplay = root.getAttribute("data-autoplay") === "1";
        const delay = parseInt(root.getAttribute("data-delay") || "6000", 10);

        function show(i) {
            index = (i + slides.length) % slides.length;

            // slides
            slides.forEach((el, idx) => {
                const active = idx === index;
                el.classList.toggle("hidden", !active);
                el.setAttribute("aria-hidden", active ? "false" : "true");
            });

            // indicators (optional)
            if (dots.length) {
                dots.forEach((d, idx) => {
                    const activeClass =
                        d.getAttribute("data-active-class") || "bg-gray-900";
                    const inactiveClass =
                        d.getAttribute("data-inactive-class") || "bg-gray-300";

                    d.classList.toggle(activeClass, idx === index);
                    d.classList.toggle(inactiveClass, idx !== index);

                    // accessibility
                    d.setAttribute(
                        "aria-current",
                        idx === index ? "true" : "false",
                    );
                });
            }
        }

        // navigation (optional)
        if (prev) prev.addEventListener("click", () => show(index - 1));
        if (next) next.addEventListener("click", () => show(index + 1));

        // indicators click (optional)
        if (dots.length) {
            dots.forEach((d) => {
                d.addEventListener("click", () => {
                    const i = parseInt(
                        d.getAttribute("data-cms-dot") || "0",
                        10,
                    );
                    show(i);
                });
            });
        }

        let timer = null;

        function start() {
            if (!autoplay) return;
            stop();
            timer = setInterval(() => show(index + 1), delay);
        }

        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        // pause on hover/focus
        root.addEventListener("mouseenter", stop);
        root.addEventListener("mouseleave", start);
        root.addEventListener("focusin", stop);
        root.addEventListener("focusout", start);

        show(0);
        start();
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("[data-cms-slider]").forEach(initSlider);
    });
})();
