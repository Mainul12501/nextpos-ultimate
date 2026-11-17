import { useUiStore } from '../stores/ui';

/**
 * The customizer's primary colour as CSS custom properties, so hand-written
 * styles follow it the same way Ant components follow `colorPrimary`.
 *
 * Page styles use them with the stock purple as fallback, e.g.
 *   color: var(--app-primary, #6d28d9);
 *   background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.08);
 * The shade names map the old hardcoded Tailwind violet scale:
 *   -1 (#f5f3ff/#ede9fe) · -2 (#ddd6fe) · -3 (#c4b5fd) · -lighter (#a78bfa/#b794f6)
 *   -light (#8b5cf6) · -hover (#7c3aed) · base (#6d28d9) · -dark (#5b21b6) · -darker (#4c1d95)
 */

const FALLBACK = '#6d28d9';

function parseHex(color) {
    let h = String(color || '').trim().replace(/^#/, '');
    if (/^[0-9a-f]{3}$/i.test(h)) h = h.split('').map(c => c + c).join('');
    if (!/^[0-9a-f]{6}$/i.test(h)) return null;
    return [0, 2, 4].map(i => parseInt(h.slice(i, i + 2), 16));
}

// Blend toward white (amount > 0) or black (amount < 0).
function shade(rgb, amount) {
    const target = amount > 0 ? 255 : 0;
    const a = Math.abs(amount);
    return rgb.map(c => Math.round(c + (target - c) * a));
}

const toHex = rgb => '#' + rgb.map(c => c.toString(16).padStart(2, '0')).join('');
const toTriplet = rgb => rgb.join(', ');

/** Every CSS variable for `color` (invalid input falls back to the stock purple). */
export function primaryVars(color) {
    const base = parseHex(color) || parseHex(FALLBACK);
    const hover = shade(base, 0.12);
    const light = shade(base, 0.22);
    return {
        '--app-primary': toHex(base),
        '--app-primary-rgb': toTriplet(base),
        '--app-primary-hover': toHex(hover),
        '--app-primary-hover-rgb': toTriplet(hover),
        '--app-primary-light': toHex(light),
        '--app-primary-light-rgb': toTriplet(light),
        '--app-primary-lighter': toHex(shade(base, 0.42)),
        '--app-primary-3': toHex(shade(base, 0.62)),
        '--app-primary-2': toHex(shade(base, 0.8)),
        '--app-primary-1': toHex(shade(base, 0.92)),
        '--app-primary-dark': toHex(shade(base, -0.16)),
        '--app-primary-darker': toHex(shade(base, -0.32)),
    };
}

/** Writes the variables onto <html>; App.vue calls this whenever the colour changes. */
export function applyPrimaryVars(color) {
    if (typeof document === 'undefined') return;
    const style = document.documentElement.style;
    for (const [name, value] of Object.entries(primaryVars(color))) style.setProperty(name, value);
}

/** Current primary as a hex string, for places CSS variables can't reach
 *  (canvas charts, SVG attributes). Read it inside a computed to stay live. */
export function primaryHex() {
    return primaryVars(useUiStore().primaryColor)['--app-primary'];
}

/** Current primary as rgba() with the given alpha, for the same places. */
export function primaryRgba(alpha) {
    return `rgba(${primaryVars(useUiStore().primaryColor)['--app-primary-rgb']}, ${alpha})`;
}

/** Light → dark ramp of the current primary (sequential chart scales). */
export function primaryRamp() {
    const v = primaryVars(useUiStore().primaryColor);
    return [v['--app-primary-2'], v['--app-primary-3'], v['--app-primary-lighter'], v['--app-primary-light'], v['--app-primary']];
}
