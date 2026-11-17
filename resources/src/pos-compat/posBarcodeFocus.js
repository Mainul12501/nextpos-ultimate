/**
 * POS Automatic Barcode Focus Mixin
 * --------------------------------------------------------------------
 * Keeps the POS scan/search input (ref="barcodeSearchInput") focused so a
 * handheld scanner always types into it, even after the cashier clicked a
 * product card, a cart button or empty space.
 *
 * Controlled by pos_settings.auto_focus_barcode (store-wide, default ON);
 * skipped entirely on touch-first devices (the on-screen keyboard would
 * keep popping up).
 *
 * Focus is put back:
 *   - once the POS settings have loaded,
 *   - after a cart line is added/removed (scan, click, draft load, reset),
 *   - after any modal closes (body loses `modal-open`) or a drawer closes,
 *   - after a click that left focus on a non-text element,
 *   - when the window regains focus (back from the receipt print popup).
 * A printable key typed while nothing text-like has focus is redirected
 * into the input, so the first digit of a scan is never dropped.
 *
 * Focus is NEVER taken from another text field (qty, discount, tax,
 * shipping, promo code, drawer search…), from an open modal or drawer, or
 * while the cashier is selecting text.
 *
 * Host contract: data `search_input`, `details`, `pos_settings`, and the
 * input carrying ref="barcodeSearchInput".
 */
import {
  autoFocusSettingOn,
  isTouchOnlyDevice,
  isTypingTarget,
  overlayOpen,
  hasTextSelection,
} from "../lib/barcodeFocus";

export default {
  computed: {
    barcodeAutoFocusOn() {
      return autoFocusSettingOn(this.pos_settings && this.pos_settings.auto_focus_barcode);
    },
    // The default pos_settings in data() has no id; wait for the real
    // (server or offline-cached) settings before the first focus so a store
    // that turned the feature off never sees it fire on load.
    barcodeFocusReady() {
      return !!(this.pos_settings && this.pos_settings.id) && this.barcodeAutoFocusOn;
    },
  },

  watch: {
    barcodeFocusReady(on) {
      if (on) this.scheduleBarcodeFocus(150);
    },
    "details.length"(n, prev) {
      if (n !== prev) this.scheduleBarcodeFocus(150);
    },
  },

  methods: {
    barcodeInputEl() {
      const el = this.$refs && this.$refs.barcodeSearchInput;
      return el && typeof el.focus === "function" ? el : null;
    },

    /** Focus the scan input if — and only if — that can't disturb the user. */
    focusBarcodeInput() {
      if (!this._bfActive || !this.barcodeFocusReady) return false;
      const el = this.barcodeInputEl();
      // offsetParent null = hidden (e.g. mobile cart tab is showing).
      if (!el || el.disabled || el.offsetParent === null) return false;
      try {
        if (document.visibilityState === "hidden" || !document.hasFocus()) return false;
      } catch (e) { /* ignore */ }
      const active = document.activeElement;
      if (active === el) return true;
      if (isTypingTarget(active)) return false;
      if (overlayOpen() || hasTextSelection()) return false;
      try { el.focus({ preventScroll: true }); } catch (e) { try { el.focus(); } catch (e2) { /* ignore */ } }
      return document.activeElement === el;
    },

    /**
     * Debounced focus. While an overlay is still open (or mid leave
     * transition) it retries a few times, then gives up — closing a modal
     * later re-triggers it through the body-class observer.
     */
    scheduleBarcodeFocus(delay = 150, attempts = 8) {
      if (!this._bfActive) return;
      clearTimeout(this._bfTimer);
      this._bfTimer = setTimeout(() => {
        this._bfTimer = null;
        if (this.focusBarcodeInput()) return;
        if (attempts > 1 && overlayOpen()) this.scheduleBarcodeFocus(150, attempts - 1);
      }, delay);
    },
  },

  mounted() {
    this._bfActive = !isTouchOnlyDevice();
    this._bfTimer = null;
    if (!this._bfActive) return;

    // Click left focus on a button / card / empty space → take it back.
    // Delayed so the click handler (add product, open drawer…) runs first.
    this._bfOnPointerUp = () => this.scheduleBarcodeFocus(150);

    // Focus moved to nothing (clicked element removed, popup closed…). A
    // move to another element is left alone: mouse clicks are handled by
    // pointerup above, and keyboard Tab navigation must keep working.
    this._bfOnFocusOut = (e) => {
      if (e.relatedTarget) return;
      this.scheduleBarcodeFocus(200);
    };

    this._bfOnWindowFocus = () => this.scheduleBarcodeFocus(250);

    // Keys typed into nowhere go into the scan input.
    this._bfOnKeyDown = (e) => {
      if (e.key === "Escape") { this.scheduleBarcodeFocus(250); return; }
      if (!this.barcodeFocusReady || e.defaultPrevented) return;
      if (e.ctrlKey || e.metaKey || e.altKey || e.isComposing) return;
      // Space stays with buttons/page scrolling; codes never start with it.
      if (!e.key || e.key.length !== 1 || e.key === " ") return;
      const target = e.target;
      if (isTypingTarget(target) || overlayOpen()) return;
      const el = this.barcodeInputEl();
      if (!el || el.disabled || el.offsetParent === null) return;
      e.preventDefault();
      // Start a fresh code — same as F2, which selects the old text. The DOM
      // value is set directly too, so the scanner's next keystroke appends
      // to it even before Vue re-renders the v-model.
      this.search_input = e.key;
      el.value = e.key;
      try { el.focus({ preventScroll: true }); } catch (e2) { el.focus(); }
      try { el.setSelectionRange(el.value.length, el.value.length); } catch (e3) { /* ignore */ }
      // The matching keyup lands on the input and runs search() as usual.
    };

    // Modal closed (counted body lock released) → refocus.
    this._bfModalOpen = document.body.classList.contains("modal-open");
    try {
      this._bfObserver = new MutationObserver(() => {
        const open = document.body.classList.contains("modal-open");
        if (this._bfModalOpen && !open) this.scheduleBarcodeFocus(150);
        this._bfModalOpen = open;
      });
      this._bfObserver.observe(document.body, { attributes: true, attributeFilter: ["class"] });
    } catch (e) {
      this._bfObserver = null;
    }

    try {
      document.addEventListener("pointerup", this._bfOnPointerUp, true);
      document.addEventListener("focusout", this._bfOnFocusOut, true);
      window.addEventListener("focus", this._bfOnWindowFocus);
      window.addEventListener("keydown", this._bfOnKeyDown, true);
    } catch (e) { /* ignore */ }

    if (this.barcodeFocusReady) this.scheduleBarcodeFocus(150);
  },

  beforeUnmount() {
    clearTimeout(this._bfTimer);
    this._bfActive = false;
    try {
      if (this._bfObserver) this._bfObserver.disconnect();
      document.removeEventListener("pointerup", this._bfOnPointerUp, true);
      document.removeEventListener("focusout", this._bfOnFocusOut, true);
      window.removeEventListener("focus", this._bfOnWindowFocus);
      window.removeEventListener("keydown", this._bfOnKeyDown, true);
    } catch (e) { /* ignore */ }
  },
};
