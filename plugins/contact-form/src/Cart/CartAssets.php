<?php

namespace Plugins\ContactForm\Cart;

use App\Cms\Hooks\HookPoints;
use Illuminate\Support\Facades\Route;

class CartAssets
{
  public static function enqueue(): void
  {
    self::registerRoutesOnce();
    self::enqueueNow();
  }

  private static function enqueueNow(): void
  {
    $cssUrl = url('/_contact/cart.css');
    $jsUrl = url('/_contact/cart.js');

    if (function_exists('cms_enqueue_style')) {
      cms_enqueue_style('contact-form-cart', $cssUrl);
    }
    if (function_exists('cms_enqueue_script')) {
      cms_enqueue_script('contact-form-cart', $jsUrl);
    }

    if (function_exists('cms_assets')) {
      try {
        $assets = cms_assets();
        if ($assets) {
          if (method_exists($assets, 'enqueueStyle')) {
            $assets->enqueueStyle('contact-form-cart', $cssUrl);
          }
          if (method_exists($assets, 'enqueueScript')) {
            $assets->enqueueScript('contact-form-cart', $jsUrl, ['defer' => 'defer']);
          }
          return;
        }
      } catch (\Throwable $e) {
        // fall through
      }
    }

    self::fallbackInjectTags($cssUrl, $jsUrl);
  }

  private static bool $fallbackInjected = false;

  private static function fallbackInjectTags(string $cssUrl, string $jsUrl): void
  {
    if (self::$fallbackInjected)
      return;
    self::$fallbackInjected = true;

    if (function_exists('add_action')) {
      add_action('cms.head', function () use ($cssUrl) {
        echo '<link rel="stylesheet" href="' . e($cssUrl) . '">';
      }, 99);

      add_action('cms.footer', function () use ($jsUrl) {
        echo '<script src="' . e($jsUrl) . '" defer></script>';
      }, 99);
    }
  }

  public static function registerRoutes(): void
  {
    Route::get('/_contact/cart.js', function () {
      return response(self::js(), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'public, max-age=300',
      ]);
    })->name('contact-form.cart.js');

    Route::get('/_contact/cart.css', function () {
      return response(self::css(), 200, [
        'Content-Type' => 'text/css; charset=UTF-8',
        'Cache-Control' => 'public, max-age=300',
      ]);
    })->name('contact-form.cart.css');
  }

  private static bool $routesRegistered = false;

  private static function registerRoutesOnce(): void
  {
    if (self::$routesRegistered)
      return;
    self::$routesRegistered = true;

    if (function_exists('add_action')) {
      add_action(HookPoints::CMS_ROUTES, function () {
        self::registerRoutes();
      });
      return;
    }

    self::registerRoutes();
  }

  public static function js(): string
  {
    return <<<'JS'
(function () {
  "use strict";

  const STORAGE_KEY = "cf_cart_v1";
  const ADD_BTN_SELECTOR = ".cf-get-price";

  // --------------------------------
  // Helpers
  // --------------------------------
  function safeJsonParse(str, fallback) {
    try { const v = JSON.parse(str); return v ?? fallback; } catch { return fallback; }
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
    return items.some((x) => String(x.id) === String(id) && String(x.type) === String(type));
  }

  function addItem(item) {
    const items = loadCart();
    if (items.some((x) => String(x.id) === String(item.id) && String(x.type) === String(item.type))) {
      return { ok: false, reason: "exists" };
    }
    items.push(item);
    saveCart(items);
    return { ok: true };
  }

  function removeItem(id, type) {
    const items = loadCart().filter((x) => !(String(x.id) === String(id) && String(x.type) === String(type)));
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
      .replaceAll("\"", "&quot;")
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

  // --------------------------------
  // Read item payload from button
  // Supports BOTH:
  //   new: data-item-*
  //   old: data-title/data-url/data-image/data-type
  // --------------------------------
  function readItemFromButton(btn) {
    const id =
      btn.getAttribute("data-item-id")
      || btn.getAttribute("data-id")
      || "";

    const type =
      btn.getAttribute("data-item-type")
      || btn.getAttribute("data-type")
      || "post";

    const title =
      btn.getAttribute("data-item-title")
      || btn.getAttribute("data-title")
      || document.title
      || "Item";

    const url =
      btn.getAttribute("data-item-url")
      || btn.getAttribute("data-url")
      || window.location.href;

    const image =
      btn.getAttribute("data-item-image")
      || btn.getAttribute("data-image")
      || "";

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
      link.href = "/_contact/cart.css";
      document.head.appendChild(link);
    }
  }

  function findHeaderHost() {
    return (
      document.querySelector("[data-cms-header-actions]") ||
      document.querySelector("header .flex.items-center.justify-between") ||
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
        '</svg>' +
      '</span>' +
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
    '</svg>' +
  '</span>' +
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

  // ✅ Close when clicking backdrop OR the close button OR the X span inside it
  overlay.addEventListener("click", function (e) {
    const closeEl = e.target && e.target.closest ? e.target.closest('[data-close="1"]') : null;
    if (closeEl) closeCartModal();
  });

  document.body.appendChild(overlay);

  // ✅ Extra safety: bind close directly too
  overlay.querySelector(".cf-cart-modal__close")?.addEventListener("click", closeCartModal);

  overlay.querySelector("#cf-cart-clear").addEventListener("click", function () {
    clearCart();
    renderCartModal();
    updateButtonsState();
    toast("Cart cleared", true);
  });

  overlay.querySelector("#cf-cart-cancel").addEventListener("click", function () {
    closeCartModal();
  });

  overlay.querySelector("#cf-cart-form").addEventListener("submit", submitForm);

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
      document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
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
        "Accept": "application/json",
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
      '</svg>';

    const rows = items.map((it) => {
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
    }).join("");

    itemsHost.innerHTML = `<div class="cf-cart-list">${rows}</div>`;

    itemsHost.querySelectorAll('[data-remove="1"]').forEach((btn) => {
      btn.addEventListener("click", function () {
        removeItem(btn.getAttribute("data-id"), btn.getAttribute("data-type"));
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
        btn.textContent = "Added";
      } else {
        btn.classList.remove("cf-added");
        btn.removeAttribute("data-added");
        btn.textContent = "Custom Quote";
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
    updateButtonsState();
    bindAddButtons(document);

    // Safe MutationObserver (no infinite loops)
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

    obs.observe(document.documentElement || document.body, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();

})();
JS;
  }

  public static function css(): string
  {
    return <<<'CSS'
/* No rounded borders anywhere */
.cf-cart-icon-wrap{ margin-left: 0; }
.cf-cart-icon__svg svg { height: 21px; width: 21px; }

.cf-cart-icon{
  position: relative;
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 6px 10px;
  font-size: 18px;
  line-height: 1;
}

.cf-cart-badge {
	position: absolute;
	top: 0px;
	right: 2px;
	min-width: 18px;
	height: 18px;
	padding: 0 5px;
	background: #fb2429;
	color: #fff;
	font-size: 12px;
	line-height: 18px;
	text-align: center;
	border-radius: 50%;
}

/* Floating pill */
.cf-cart-pill{
  position: fixed;
  right: 18px;
  bottom: 18px;

  width: 56px;
  height: 56px;

  border: 0;
  cursor: pointer;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  background: #2f6fa3;
  color: #fff;

  box-shadow: 0 10px 25px rgba(0,0,0,.18);

  opacity: 0;
  pointer-events: none;
  transform: translateY(10px);
  transition: opacity .18s ease, transform .18s ease;
  z-index: 9998;

  /* circle */
  border-radius: 999px;
}

.cf-cart-pill.cf-show{
  opacity: 1;
  pointer-events: auto;
  transform: translateY(0);
}

.cf-cart-pill__badge{
  position: absolute;
  top: -6px;
  right: -6px;

  min-width: 22px;
  height: 22px;
  padding: 0 6px;

  background: #ec2529;
  color: #fff;

  font-size: 12px;
  line-height: 22px;
  text-align: center;

  border-radius: 999px;
}
.cf-cart-pill__icon{
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
/* Toast (bottom-right) */
.cf-cart-toast{
  position: fixed;
  right: 18px;
  bottom: 18px;

  background: #111;
  color: #fff;
  padding: 10px 14px;

  opacity: 0;
  pointer-events: none;
  transition: opacity .18s ease, transform .18s ease;
  z-index: 9999;

  /* slide-in from bottom */
  transform: translateY(6px);

  /* keep inside screen on mobile */
  max-width: calc(100vw - 36px);
}

.cf-cart-toast.cf-show{
  opacity: 1;
  transform: translateY(0);
}

.cf-cart-toast.cf-ok{ background: #111; }
.cf-cart-toast.cf-bad{ background: #7f1d1d; }

/* Cart Modal */
.cf-cart-modal{
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: none;
}
.cf-cart-modal.cf-open{ display: block; }

.cf-cart-modal__backdrop{
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.45);
}

.cf-cart-modal__panel{
  position: absolute;
  top: 50%;
  left: 50%;
  width: min(980px, calc(100vw - 28px));
  max-height: min(88vh, 860px);
  transform: translate(-50%, -50%);
  background: #fff;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.cf-cart-modal__head{
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-bottom: 1px solid #e5e7eb;
}
.cf-cart-modal__title{
  font-weight: 700;
  font-size: 16px;
}
.cf-cart-modal__close{
  margin-left: auto;
  border: 0;
  background: transparent;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  padding: 6px;
}

/* 2 columns layout like your sketch: Items | Form */
.cf-cart-modal__layout{
  display: grid;
  grid-template-columns: 1fr 1px 1fr;
  min-height: 0;
  flex: 1;
}

.cf-cart-modal__left,
.cf-cart-modal__right{
  min-height: 0;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
}

.cf-cart-modal__divider{
  background: #d1d5db;
}

/* Left: Items */
.cf-cart-left__title{
  font-weight: 700;
  margin-bottom: 10px;
}
.cf-cart-modal__items{
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid #e5e7eb;
  padding: 10px;
}
.cf-cart-left__actions{
  margin-top: 10px;
  display: flex;
  justify-content: flex-start;
}

.cf-cart-btn{
  border: 0;
  cursor: pointer;
  padding: 10px 14px;
  font-weight: 600;
}
.cf-cart-btn--ghost{ background: #f3f4f6; color: #111; }

/* Items list */
.cf-cart-list{
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.cf-cart-item{
  display: grid;
  grid-template-columns: 56px 1fr 32px;
  gap: 10px;
  align-items: center;
  padding: 8px;
  border: 1px solid #e5e7eb;
}
.cf-cart-item__media{ width: 56px; height: 56px; }
.cf-cart-item__img{
  width: 56px;
  height: 56px;
  object-fit: contain;
  background: #fff;
}
.cf-cart-item__img--ph{ background: #f3f4f6; }
.cf-cart-item__title{
  display: inline-block;
  font-weight: 700;
  color: #111;
  text-decoration: none;
  word-break: break-word;
}
.cf-cart-item__title:hover{ text-decoration: underline; }

.cf-cart-item__remove{
  border: 0;
  background: transparent;
  cursor: pointer;
  color: #dc2626;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.cf-cart-item__remove:hover{ color: #b91c1c; }

/* Empty */
.cf-cart-empty{ text-align: center; padding: 18px 10px; color: #6b7280; }
.cf-cart-empty__icon{ font-size: 28px; margin-bottom: 6px; }

/* Right: Form */
.cf-cart-right__title{
  font-weight: 700;
  margin-bottom: 10px;
}
.cf-form{
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid #e5e7eb;
  padding: 12px;
}
.cf-form__row{
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 30px;
}
.cf-form__row--single{
  grid-template-columns: 1fr;
}
.cf-form__row textarea,
.cf-form__row input{
  width: 100%;
  border: 1px solid #cbd5e1;
  padding: 10px 12px;
  font-size: 14px;
  outline: none;
}
.cf-cart-left__actions{
  margin-top: 10px;
}

.cf-form__actions{
  margin-top: auto;
}
.cf-form__actions{
  display: flex;
  gap: 10px;
  margin-top: 10px;
  flex-wrap: wrap;
}
.cf-form__send{
  background: #0b4a78;
  color: #fff;
  border: 0;
  padding: 10px 26px;
  cursor: pointer;
}
.cf-form__send:disabled{ opacity: .7; cursor: not-allowed; }
.cf-form__cancel{
  background: #e5e7eb;
  border: 0;
  padding: 10px 18px;
  cursor: pointer;
}

body.cf-cart-lock{ overflow: hidden; }
.cf-get-price.cf-added{ opacity: .9; }

/* Mobile responsive: stack Items then Form */
@media (max-width: 900px){
  /* switch to flex so we can reorder */
  .cf-cart-modal__layout{
    display: flex;
    flex-direction: column;
  }

  /* ✅ form first, items second */
  .cf-cart-modal__right{ order: 1; }
  .cf-cart-modal__left{ order: 2; }

  .cf-cart-modal__divider{
    display: none;
  }

  /* keep both boxes scrollable + balanced */
  .cf-form{
    max-height: 45vh;
    overflow: auto;
  }

  .cf-cart-modal__items{
    max-height: 40vh;
    overflow: auto;
  }
}

/* Very small screens */
@media (max-width: 520px){
  .cf-form__row{
    grid-template-columns: 1fr;
  }
  .cf-cart-item{
    grid-template-columns: 52px 1fr 32px;
  }
  .cf-cart-item__media,
  .cf-cart-item__img{
    width: 52px;
    height: 52px;
  }
}
CSS;
  }
}