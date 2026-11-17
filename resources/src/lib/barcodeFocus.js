/**
 * Automatic barcode field focus — shared helpers.
 * --------------------------------------------------------------------
 * Handheld scanners are keyboard wedges: they "type" the code into whatever
 * element has focus and finish with Enter. If focus has drifted to a button,
 * a cart row or nowhere, the scan is lost. These helpers decide WHEN it is
 * safe to put focus back on the scan field without stealing it from
 * something the user is working in.
 *
 * Controlled by pos_settings.auto_focus_barcode (store-wide, default ON).
 * Used by the POS mixin (pos-compat/posBarcodeFocus.js) and by the
 * back-office document forms (useBarcodeFocus composable below).
 */
import { watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import http from './http';

/** Missing value (older cached payloads, pre-migration rows) = default ON. */
export function autoFocusSettingOn(value) {
  if (value === undefined || value === null || value === '') return true;
  return value === true || value === 1 || value === '1' || value === 'true';
}

/**
 * Phones / tablets: focusing an input pops up the on-screen keyboard over
 * half the screen, so auto-focus is skipped on touch-first devices. Laptops
 * with a touch screen still report a fine primary pointer and keep it.
 */
export function isTouchOnlyDevice() {
  try {
    return !!(window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches);
  } catch (e) {
    return false;
  }
}

/** Elements that accept typed text — never take focus away from these. */
export function isTypingTarget(el) {
  if (!el || el === document.body) return false;
  const tag = (el.tagName || '').toUpperCase();
  if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
  if (tag === 'INPUT') {
    const type = String(el.type || 'text').toLowerCase();
    return !['button', 'submit', 'reset', 'checkbox', 'radio', 'range', 'color', 'file', 'image'].includes(type);
  }
  return !!el.isContentEditable;
}

function isVisible(el) {
  try {
    return window.getComputedStyle(el).display !== 'none';
  } catch (e) {
    return true;
  }
}

/**
 * True while a modal, drawer, popconfirm or dropdown owns the screen — the
 * scan field must stay put until it closes.
 */
export function overlayOpen() {
  try {
    if (document.body.classList.contains('modal-open')) return true;
    if (document.querySelector('[class*="drawer-backdrop"]')) return true;
    // Open Bootstrap-style dropdown (POS b-dropdown shim: sync, language…).
    if (document.querySelector('.dropdown-menu.show')) return true;
    const antd = document.querySelectorAll('.ant-modal-wrap, .ant-drawer-open, .ant-popover:not(.ant-popover-hidden), .ant-picker-dropdown:not(.ant-picker-dropdown-hidden)');
    for (const el of antd) {
      if (isVisible(el)) return true;
    }
  } catch (e) { /* ignore */ }
  return false;
}

/** The user is selecting text (e.g. copying a reference) — don't disturb. */
export function hasTextSelection() {
  try {
    const sel = window.getSelection && window.getSelection();
    return !!(sel && !sel.isCollapsed && String(sel).length);
  } catch (e) {
    return false;
  }
}

/**
 * Reads pos_settings.auto_focus_barcode for pages that don't already have
 * it. Fetched per mount (one small request) so a change made in POS
 * Settings applies the next time a form is opened, without a reload.
 */
export async function fetchAutoFocusSetting() {
  try {
    const data = await http.get('get_pos_Settings');
    return autoFocusSettingOn(data?.pos_settings?.auto_focus_barcode);
  } catch (e) {
    return true;
  }
}

/**
 * Back-office document forms (Sale, Purchase, Quotation, Transfer,
 * Adjustment, Damage). Deliberately gentle — these forms have many other
 * fields, so focus is never reclaimed on blur. It only:
 *   - focuses the product search once it becomes usable (warehouse chosen),
 *   - re-focuses it after a new line is added (covers flows that went
 *     through a batch/serial/variant modal and lost focus).
 *
 * @param {Ref} selectRef  ref to the ant-design-vue <a-select> (show-search)
 * @param {Function} ready getter → truthy once the search is enabled
 * @param {Function} count getter → number of lines on the document
 */
export function useBarcodeFocus(selectRef, { ready, count }) {
  let enabled = false;
  let timer = null;
  let alive = true;

  // Lets the select re-render as enabled first; while a batch/serial modal
  // is still running its leave transition it retries a few times.
  function focusSearch(attempts = 6) {
    if (!enabled || !alive) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      if (!alive || !ready()) return;
      const active = document.activeElement;
      const comp = selectRef.value;
      const root = comp && comp.$el;
      if (root && root.contains && active && root.contains(active)) return;
      // A closed antd select (e.g. the warehouse picker just used to enable
      // the search) keeps focus in its inner input but isn't being typed in.
      const closedSelect = active && active.closest
        && active.closest('.ant-select') && !active.closest('.ant-select-open');
      if (isTypingTarget(active) && !closedSelect) return;
      if (overlayOpen()) {
        if (attempts > 1) focusSearch(attempts - 1);
        return;
      }
      try { comp && comp.focus && comp.focus(); } catch (e) { /* ignore */ }
    }, 200);
  }

  onMounted(async () => {
    if (isTouchOnlyDevice()) return;
    enabled = await fetchAutoFocusSetting();
    if (enabled && ready()) nextTick(focusSearch);
  });

  onBeforeUnmount(() => {
    alive = false;
    clearTimeout(timer);
  });

  watch(() => !!ready(), (on, was) => {
    if (on && !was) nextTick(focusSearch);
  });

  watch(() => Number(count() || 0), (n, prev) => {
    if (n > prev) focusSearch();
  });

  return { focusSearch };
}
