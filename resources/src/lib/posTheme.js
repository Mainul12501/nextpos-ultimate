/**
 * POS Theme — shared by the POS screen (PosPage.vue), the in-POS gear menu
 * and System Settings → POS Theme.
 *
 * A theme is a LAYOUT (classic | touch | compact | split | cards) plus a
 * COLOUR MODE (light | dark | auto). Themes only switch CSS classes on the
 * POS root (`pos-theme-<layout>` + `pos-mode-<light|dark>`); they never touch
 * sale logic, so switching keeps the cart, drafts and offline queue as-is.
 *
 * Resolution order (first hit wins):
 *   1. `?preview_theme=` / `?preview_mode=` on the POS URL — one tab only,
 *      never saved ("Open POS with this theme" from System Settings).
 *   2. This till's own pick (localStorage) — only when the store allows
 *      per-till themes (pos_settings.pos_theme_device_override).
 *   3. The store default (pos_settings.pos_theme / pos_color_mode).
 *   4. Classic Light (also what existing installs get after upgrade).
 */

export const POS_THEMES = [
  { key: 'classic', label: 'PosTheme_Classic', desc: 'PosTheme_Classic_Desc' },
  { key: 'touch', label: 'PosTheme_Touch', desc: 'PosTheme_Touch_Desc' },
  { key: 'compact', label: 'PosTheme_Compact', desc: 'PosTheme_Compact_Desc' },
  { key: 'split', label: 'PosTheme_Split', desc: 'PosTheme_Split_Desc' },
  { key: 'cards', label: 'PosTheme_Cards', desc: 'PosTheme_Cards_Desc' },
];

export const POS_COLOR_MODES = [
  { key: 'light', label: 'PosTheme_Mode_Light' },
  { key: 'dark', label: 'PosTheme_Mode_Dark' },
  { key: 'auto', label: 'PosTheme_Mode_Auto' },
];

export const DEFAULT_POS_THEME = 'classic';
export const DEFAULT_POS_COLOR_MODE = 'light';
export const POS_THEME_DEVICE_KEY = 'pos_ui_theme';

const THEME_KEYS = POS_THEMES.map(t => t.key);
const MODE_KEYS = POS_COLOR_MODES.map(m => m.key);

export function normalizePosTheme(value) {
  return THEME_KEYS.includes(value) ? value : null;
}

export function normalizePosColorMode(value) {
  return MODE_KEYS.includes(value) ? value : null;
}

/** This till's own pick: { theme, mode } (either may be null). */
export function getDevicePosTheme() {
  try {
    const raw = localStorage.getItem(POS_THEME_DEVICE_KEY);
    if (!raw) return { theme: null, mode: null };
    const v = JSON.parse(raw) || {};
    return { theme: normalizePosTheme(v.theme), mode: normalizePosColorMode(v.mode) };
  } catch (e) {
    return { theme: null, mode: null };
  }
}

export function setDevicePosTheme({ theme, mode }) {
  try {
    localStorage.setItem(POS_THEME_DEVICE_KEY, JSON.stringify({
      theme: normalizePosTheme(theme),
      mode: normalizePosColorMode(mode),
    }));
  } catch (e) { /* storage blocked — the store default still applies */ }
}

export function clearDevicePosTheme() {
  try { localStorage.removeItem(POS_THEME_DEVICE_KEY); } catch (e) { /* ignore */ }
}

/** One-tab preview from the URL query (never persisted). */
export function getPreviewPosTheme(query) {
  const q = query || {};
  return {
    theme: normalizePosTheme(q.preview_theme),
    mode: normalizePosColorMode(q.preview_mode),
  };
}

/**
 * Resolve the effective { theme, mode, source } for the POS.
 * `posSettings` is the pos_settings row (may be partial / cached offline).
 */
export function resolvePosTheme(posSettings, { query, device } = {}) {
  const ps = posSettings || {};
  const storeTheme = normalizePosTheme(ps.pos_theme) || DEFAULT_POS_THEME;
  const storeMode = normalizePosColorMode(ps.pos_color_mode) || DEFAULT_POS_COLOR_MODE;

  const preview = getPreviewPosTheme(query);
  if (preview.theme || preview.mode) {
    return { theme: preview.theme || storeTheme, mode: preview.mode || storeMode, source: 'preview' };
  }

  if (isDeviceOverrideAllowed(ps)) {
    const own = device || getDevicePosTheme();
    if (own.theme || own.mode) {
      return { theme: own.theme || storeTheme, mode: own.mode || storeMode, source: 'device' };
    }
  }

  return { theme: storeTheme, mode: storeMode, source: 'store' };
}

export function isDeviceOverrideAllowed(posSettings) {
  const v = posSettings && posSettings.pos_theme_device_override;
  return v === true || v === 1 || v === '1' || v === 'true';
}

const darkMql = typeof window !== 'undefined' && window.matchMedia
  ? window.matchMedia('(prefers-color-scheme: dark)')
  : null;

export function posModeIsDark(mode) {
  if (mode === 'auto') return !!(darkMql && darkMql.matches);
  return mode === 'dark';
}

/** Subscribe to OS light/dark changes (for `auto`). Returns an unsubscribe fn. */
export function onSystemColorSchemeChange(cb) {
  if (!darkMql) return () => {};
  const handler = () => cb(darkMql.matches);
  if (darkMql.addEventListener) darkMql.addEventListener('change', handler);
  else if (darkMql.addListener) darkMql.addListener(handler);
  return () => {
    if (darkMql.removeEventListener) darkMql.removeEventListener('change', handler);
    else if (darkMql.removeListener) darkMql.removeListener(handler);
  };
}

/** Root classes for the POS (and the System Settings preview). */
export function posThemeClasses(theme, dark) {
  return [
    'pos-theme-' + (normalizePosTheme(theme) || DEFAULT_POS_THEME),
    dark ? 'pos-mode-dark' : 'pos-mode-light',
  ];
}
