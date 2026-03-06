(function () {
    "use strict";

    const STORAGE_KEY = "cf_cart_v1";
    const ADD_BTN_SELECTOR = ".cf-get-price";

    // --------------------------------
    // Helpers
    // --------------------------------
    function safeJsonParse(str, fallback) {
        try {
            const v = JSON.parse(str);
            return v ?? fallback;
        } catch {
            return fallback;
        }
    }

    function loadCart() {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const cart = safeJsonParse(raw || "[]", []);
        return Array.isArray(cart) ? cart : [];
    }

    function saveCart(items) {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items || []));
        updateBadges();
    }

    function cartHas(id, type) {
        const items = loadCart();
        return items.some(
            (x) =>
                String(x.id) === String(id) && String(x.type) === String(type),
        );
    }

    function addItem(item) {
        const items = loadCart();
        if (
            items.some(
                (x) =>
                    String(x.id) === String(item.id) &&
                    String(x.type) === String(item.type),
            )
        ) {
            return { ok: false, reason: "exists" };
        }
        items.push(item);
        saveCart(items);
        return { ok: true };
    }

    function removeItem(id, type) {
        const items = loadCart().filter(
            (x) =>
                !(
                    String(x.id) === String(id) &&
                    String(x.type) === String(type)
                ),
        );
        saveCart(items);
    }

    function clearCart() {
        saveCart([]);
    }

    function escapeHtml(str) {
        const s = String(str ?? "");
        return s
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function toast(msg, ok) {
        let el = document.getElementById("cf-cart-toast");
        if (!el) {
            el = document.createElement("div");
            el.id = "cf-cart-toast";
            el.className = "cf-cart-toast";
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.remove("cf-show", "cf-ok", "cf-bad");
        el.classList.add(ok ? "cf-ok" : "cf-bad");
        void el.offsetHeight;
        el.classList.add("cf-show");
        setTimeout(() => el.classList.remove("cf-show"), 1800);
    }

    function getButtonDefaultLabel(btn) {
        const saved = btn.getAttribute("data-default-label");
        if (saved && saved.trim() !== "") {
            return saved;
        }

        const current = (btn.innerHTML || "").trim();
        if (current !== "") {
            btn.setAttribute("data-default-label", current);
            return current;
        }

        return btn.dataset.defaultLabel || "Custom Quote";
    }

    function setButtonDefaultLabel(btn) {
        btn.innerHTML = getButtonDefaultLabel(btn);
    }

    function setButtonAddedLabel(btn) {
        btn.textContent = "Added";
    }

    // --------------------------------
    // Read item payload from button
    // Supports BOTH:
    //   new: data-item-*
    //   old: data-title/data-url/data-image/data-type
    // --------------------------------
    function readItemFromButton(btn) {
        const id =
            btn.getAttribute("data-item-id") ||
            btn.getAttribute("data-id") ||
            "";

        const type =
            btn.getAttribute("data-item-type") ||
            btn.getAttribute("data-type") ||
            "post";

        const title =
            btn.getAttribute("data-item-title") ||
            btn.getAttribute("data-title") ||
            document.title ||
            "Item";

        const url =
            btn.getAttribute("data-item-url") ||
            btn.getAttribute("data-url") ||
            window.location.href;

        const image =
            btn.getAttribute("data-item-image") ||
            btn.getAttribute("data-image") ||
            "";

        return { id, type, title, url, image };
    }

    // --------------------------------
    // UI: Cart icon (header) + floating pill + ONE modal (items + form)
    // --------------------------------
    function ensureAssetsLoaded() {
        const cssId = "cf-cart-css";
        if (!document.getElementById(cssId)) {
            const link = document.createElement("link");
            link.id = cssId;
            link.rel = "stylesheet";

            link.href =
                (window.ContactFormCart && window.ContactFormCart.cssUrl) ||
                "/_contact/cart.css";

            document.head.appendChild(link);
        }
    }

    function findHeaderHost() {
        return (
            document.querySelector("[data-cms-header-actions]") ||
            document.querySelector(
                "header .flex.items-center.justify-between",
            ) ||
            document.querySelector("header .container") ||
            document.querySelector("header") ||
            document.body
        );
    }

    function ensureCartIcon() {
        const host = findHeaderHost();

        let iconWrap = document.getElementById("cf-cart-icon-wrap");
        if (iconWrap) return iconWrap;

        iconWrap = document.createElement("div");
        iconWrap.id = "cf-cart-icon-wrap";
        iconWrap.className = "cf-cart-icon-wrap";

        const icon = document.createElement("button");
        icon.type = "button";
        icon.id = "cf-cart-icon";
        icon.className = "cf-cart-icon";
        icon.innerHTML =
            '<span class="cf-cart-icon__svg" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
            'stroke-linecap="round" stroke-linejoin="round" width="18" height="18">' +
            '<circle cx="9" cy="21" r="1"></circle>' +
            '<circle cx="20" cy="21" r="1"></circle>' +
            '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>' +
            "</svg>" +
            "</span>" +
            '<span class="cf-cart-badge" id="cf-cart-badge">0</span>';

        icon.setAttribute("aria-label", "Open cart");
        icon.addEventListener("click", openCartModal);

        iconWrap.appendChild(icon);
        host.appendChild(iconWrap);

        return iconWrap;
    }

    function ensureFloatingPill() {
        let pill = document.getElementById("cf-cart-pill");
        if (pill) return pill;

        pill = document.createElement("button");
        pill.type = "button";
        pill.id = "cf-cart-pill";
        pill.className = "cf-cart-pill";
        pill.innerHTML =
            '<span class="cf-cart-pill__icon" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
            'stroke-linecap="round" stroke-linejoin="round" width="18" height="18">' +
            '<circle cx="9" cy="21" r="1"></circle>' +
            '<circle cx="20" cy="21" r="1"></circle>' +
            '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>' +
            "</svg>" +
            "</span>" +
            '<span class="cf-cart-pill__badge" id="cf-cart-pill-badge">0</span>';

        pill.setAttribute("aria-label", "Open cart");
        pill.addEventListener("click", openCartModal);

        document.body.appendChild(pill);
        return pill;
    }

    function ensureCartModal() {
        let overlay = document.getElementById("cf-cart-modal");
        if (overlay) return overlay;

        overlay = document.createElement("div");
        overlay.id = "cf-cart-modal";
        overlay.className = "cf-cart-modal";

        overlay.innerHTML = `
      <div class="cf-cart-modal__backdrop" data-close="1"></div>
      <div class="cf-cart-modal__panel" role="dialog" aria-modal="true" aria-label="Cart">
        <div class="cf-cart-modal__head">
          <div class="cf-cart-modal__title">
            Selected Items (<span id="cf-cart-count">0</span>)
          </div>
          <button type="button" class="cf-cart-modal__close" data-close="1" aria-label="Close">
            <span aria-hidden="true">✕</span>
          </button>
        </div>

        <div class="cf-cart-modal__layout">
          <div class="cf-cart-modal__left">
            <div class="cf-cart-left__title">Items</div>
            <div class="cf-cart-modal__items" id="cf-cart-modal-items"></div>

            <div class="cf-cart-left__actions">
              <button type="button" class="cf-cart-btn cf-cart-btn--ghost" id="cf-cart-clear">Clear</button>
            </div>
          </div>

          <div class="cf-cart-modal__divider" aria-hidden="true"></div>

          <div class="cf-cart-modal__right">
            <div class="cf-cart-right__title">Form</div>

            <form class="cf-form" id="cf-cart-form">
              <div class="cf-form__row">
                <input type="text" name="name" placeholder="Name" required>
                <input type="text" name="whatsapp" placeholder="WhatsApp">
              </div>

              <div class="cf-form__row cf-form__row--single">
                <input type="email" name="email" placeholder="Email" required>
              </div>

              <div class="cf-form__row cf-form__row--single">
                <textarea name="message" placeholder="Write your Message Here" rows="7" required></textarea>
              </div>

              <input type="hidden" name="subject" value="Custom Quote Request">
              <input type="hidden" name="cart_items" id="cf-cart-items-hidden" value="[]">

              <div class="cf-form__actions">
                <button type="submit" class="cf-form__send" id="cf-cart-send">SEND</button>
                <button type="button" class="cf-form__cancel" id="cf-cart-cancel">Cancel</button>
              </div>
            </form>

            <div class="cf-cart-form__hint" id="cf-cart-form-hint"></div>
          </div>
        </div>
      </div>
    `;

        overlay.addEventListener("click", function (e) {
            const closeEl =
                e.target && e.target.closest
                    ? e.target.closest('[data-close="1"]')
                    : null;
            if (closeEl) closeCartModal();
        });

        document.body.appendChild(overlay);

        overlay
            .querySelector(".cf-cart-modal__close")
            ?.addEventListener("click", closeCartModal);

        overlay
            .querySelector("#cf-cart-clear")
            .addEventListener("click", function () {
                clearCart();
                renderCartModal();
                updateButtonsState();
                toast("Cart cleared", true);
            });

        overlay
            .querySelector("#cf-cart-cancel")
            .addEventListener("click", function () {
                closeCartModal();
            });

        overlay
            .querySelector("#cf-cart-form")
            .addEventListener("submit", submitForm);

        return overlay;
    }

    function submitForm(e) {
        e.preventDefault();

        const items = loadCart();
        if (!items.length) {
            toast("Your cart is empty", false);
            return;
        }

        const form = e.target;
        const fd = new FormData(form);
        fd.set("cart_items", JSON.stringify(items));

        const token =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") ||
            document.querySelector('input[name="_token"]')?.value ||
            "";

        const sendBtn = document.getElementById("cf-cart-send");
        if (sendBtn) sendBtn.disabled = true;

        fetch("/_contact/submit", {
            method: "POST",
            body: fd,
            credentials: "same-origin",
            headers: {
                "X-CSRF-TOKEN": token,
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    toast("Send failed. Please try again.", false);
                    return;
                }
                toast("Message sent successfully", true);
                clearCart();
                renderCartModal();
                updateButtonsState();
                closeCartModal();
            })
            .catch(() => {
                toast("Network error. Please try again.", false);
            })
            .finally(() => {
                if (sendBtn) sendBtn.disabled = false;
            });
    }

    function openCartModal() {
        ensureCartModal();
        renderCartModal();
        document.getElementById("cf-cart-modal").classList.add("cf-open");
        document.body.classList.add("cf-cart-lock");
    }

    function closeCartModal() {
        const overlay = document.getElementById("cf-cart-modal");
        if (overlay) overlay.classList.remove("cf-open");
        document.body.classList.remove("cf-cart-lock");
    }

    function renderCartModal() {
        const itemsHost = document.getElementById("cf-cart-modal-items");
        const countEl = document.getElementById("cf-cart-count");
        const hidden = document.getElementById("cf-cart-items-hidden");

        if (!itemsHost) return;

        const items = loadCart();
        if (countEl) countEl.textContent = String(items.length);
        if (hidden) hidden.value = JSON.stringify(items);

        if (!items.length) {
            itemsHost.innerHTML = `
        <div class="cf-cart-empty">
          <div class="cf-cart-empty__icon">🛒</div>
          <div class="cf-cart-empty__text">Your cart is empty.</div>
        </div>
      `;
            return;
        }

        const removeSvg =
            '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">' +
            '<path d="M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
            '<path d="M6 6L18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
            "</svg>";

        const rows = items
            .map((it) => {
                const img = it.image
                    ? `<img src="${escapeHtml(it.image)}" alt="" class="cf-cart-item__img">`
                    : `<div class="cf-cart-item__img cf-cart-item__img--ph"></div>`;

                const title = escapeHtml(it.title || "Item");
                const url = escapeHtml(it.url || "#");
                const id = escapeHtml(it.id);
                const type = escapeHtml(it.type);

                return `
        <div class="cf-cart-item">
          <div class="cf-cart-item__media">${img}</div>
          <div class="cf-cart-item__info">
            <a class="cf-cart-item__title" href="${url}" target="_blank" rel="noopener">${title}</a>
          </div>
          <button type="button"
            class="cf-cart-item__remove"
            title="Remove"
            aria-label="Remove"
            data-remove="1"
            data-id="${id}"
            data-type="${type}">${removeSvg}</button>
        </div>
      `;
            })
            .join("");

        itemsHost.innerHTML = `<div class="cf-cart-list">${rows}</div>`;

        itemsHost.querySelectorAll('[data-remove="1"]').forEach((btn) => {
            btn.addEventListener("click", function () {
                removeItem(
                    btn.getAttribute("data-id"),
                    btn.getAttribute("data-type"),
                );
                renderCartModal();
                updateButtonsState();
                toast("Removed", true);
            });
        });
    }

    function updateBadges() {
        const count = loadCart().length;

        const badge = document.getElementById("cf-cart-badge");
        if (badge) badge.textContent = String(count);

        const pillBadge = document.getElementById("cf-cart-pill-badge");
        if (pillBadge) pillBadge.textContent = String(count);

        const pill = document.getElementById("cf-cart-pill");
        if (pill) {
            if (count > 0) pill.classList.add("cf-show");
            else pill.classList.remove("cf-show");
        }
    }

    function updateButtonsState() {
        document.querySelectorAll(ADD_BTN_SELECTOR).forEach((btn) => {
            const item = readItemFromButton(btn);
            if (!item.id) return;

            const inCart = cartHas(item.id, item.type);

            if (inCart) {
                btn.classList.add("cf-added");
                btn.setAttribute("data-added", "1");
                setButtonAddedLabel(btn);
            } else {
                btn.classList.remove("cf-added");
                btn.removeAttribute("data-added");
                setButtonDefaultLabel(btn);
            }
        });
    }

    function bindAddButtons(root) {
        const scope = root || document;
        const buttons = Array.from(scope.querySelectorAll(ADD_BTN_SELECTOR));
        if (!buttons.length) return;

        buttons.forEach((btn) => {
            if (btn.getAttribute("data-cf-bound") === "1") return;
            btn.setAttribute("data-cf-bound", "1");

            // store original Blade-rendered label once
            if (!btn.getAttribute("data-default-label")) {
                const initialLabel = (btn.innerHTML || "").trim();
                if (initialLabel !== "") {
                    btn.setAttribute("data-default-label", initialLabel);
                }
            }

            btn.addEventListener("click", function (e) {
                e.preventDefault();

                const item = readItemFromButton(btn);
                if (!item.id) {
                    toast("Item id missing (check blade data-item-id)", false);
                    return;
                }

                const res = addItem(item);
                if (!res.ok && res.reason === "exists") {
                    toast("Already added", false);
                    updateButtonsState();
                    return;
                }

                toast("Added to cart", true);
                updateButtonsState();
                updateBadges();
            });
        });
    }

    function init() {
        ensureAssetsLoaded();
        ensureCartIcon();
        ensureFloatingPill();
        updateBadges();
        bindAddButtons(document);
        updateButtonsState();

        let syncing = false;
        let scheduled = false;

        function scheduleSync() {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                syncing = true;
                try {
                    updateButtonsState();
                    updateBadges();
                } finally {
                    syncing = false;
                }
            });
        }

        const obs = new MutationObserver(function (mutations) {
            if (syncing) return;

            let hasNewNodes = false;
            for (const m of mutations) {
                for (const n of m.addedNodes || []) {
                    if (n && n.querySelectorAll) {
                        bindAddButtons(n);
                        hasNewNodes = true;
                    }
                }
            }
            if (hasNewNodes) scheduleSync();
        });

        obs.observe(document.documentElement || document.body, {
            childList: true,
            subtree: true,
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
