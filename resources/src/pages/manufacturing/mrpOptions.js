import {
    FileStack, Factory, Hammer, ShieldCheck, Calculator, Cog,
} from 'lucide-vue-next';
import { t, i18n } from '../../i18n';

/**
 * Shared vocabulary for the manufacturing module: the values the API stores,
 * the label each shows and the antd colour it wears. Every MRP page reads from
 * here, so an order cannot look "released" on one screen and "issued" on another.
 *
 * Lists are translated through tr() inside a computed(), so they re-render
 * when the language changes.
 */

/**
 * The order lifecycle, in order. `step` drives the progress indicator, so it is
 * the single place that knows draft comes before planned.
 */
export const ORDER_STATUSES = [
    { value: 'draft', key: 'Draft', label: 'Draft', color: 'default', step: 0 },
    { value: 'planned', key: 'Planned', label: 'Planned', color: 'blue', step: 1 },
    { value: 'released', key: 'Mfg_Released', label: 'Released', color: 'processing', step: 2 },
    { value: 'in_progress', key: 'Rental_Mt_In_Progress', label: 'In progress', color: 'warning', step: 3 },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success', step: 4 },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'error', step: -1 },
];

export const OPEN_ORDER_STATUSES = ['draft', 'planned', 'released', 'in_progress'];

export const PRIORITIES = [
    { value: 'low', key: 'Low', label: 'Low', color: 'default' },
    { value: 'normal', key: 'Rental_Pr_Normal', label: 'Normal', color: 'blue' },
    { value: 'high', key: 'Rental_Pr_High', label: 'High', color: 'warning' },
    { value: 'urgent', key: 'Rental_Pr_Urgent', label: 'Urgent', color: 'error' },
];

export const BOM_STATUSES = [
    { value: 'draft', key: 'Draft', label: 'Draft', color: 'default' },
    { value: 'active', key: 'Active', label: 'Active', color: 'success' },
    { value: 'archived', key: 'Mfg_Archived', label: 'Archived', color: 'default' },
];

export const WORK_ORDER_STATUSES = [
    { value: 'pending', key: 'Mfg_Waiting', label: 'Waiting', color: 'default' },
    { value: 'in_progress', key: 'Running', label: 'Running', color: 'processing' },
    { value: 'completed', key: 'Done', label: 'Done', color: 'success' },
    { value: 'skipped', key: 'Skipped', label: 'Skipped', color: 'warning' },
];

export const QC_STATUSES = [
    { value: 'pending', key: 'Pending', label: 'Pending', color: 'default' },
    { value: 'passed', key: 'Passed', label: 'Passed', color: 'success' },
    { value: 'partial', key: 'Partial', label: 'Partial', color: 'warning' },
    { value: 'failed', key: 'Failed', label: 'Failed', color: 'error' },
];

export const QC_TYPES = [
    { value: 'in_process', key: 'Mfg_In_Process', label: 'In process' },
    { value: 'final', key: 'Mfg_Final', label: 'Final' },
];

export const SUGGESTION_ACTIONS = [
    { value: 'make', key: 'Make', label: 'Make', color: 'purple' },
    { value: 'buy', key: 'Mfg_Buy', label: 'Buy', color: 'blue' },
];

export const SUGGESTION_STATUSES = [
    { value: 'pending', key: 'Pending', label: 'Pending', color: 'processing' },
    { value: 'accepted', key: 'Accepted', label: 'Accepted', color: 'success' },
    { value: 'dismissed', key: 'Mfg_Dismissed', label: 'Dismissed', color: 'default' },
];

/** Section icons, so each page reads as itself in the sidebar and headers. */
export const SECTION_ICONS = {
    boms: FileStack,
    production: Factory,
    workOrders: Hammer,
    quality: ShieldCheck,
    planning: Calculator,
    workCenters: Cog,
};

/** Translated copy of an option list (call inside a computed). */
export function tr(list) {
    return list.map(o => ({ ...o, label: t(o.key, o.label) }));
}

/**
 * Translate a message with {named} placeholders. Before the key is seeded the
 * English fallback is interpolated instead, so the UI never shows a raw key.
 */
export function tp(key, fallback, params = {}) {
    const out = i18n.global.t(key, params);
    if (out !== key) return out;
    return String(fallback).replace(/\{(\w+)\}/g, (m, k) => (params[k] !== undefined ? params[k] : m));
}

/** Look one up, falling back to the raw value so nothing renders blank. */
export function optionOf(list, value) {
    return list.find(o => o.value === value) || { value, key: '', label: value || '—', color: 'default' };
}

export function labelOf(list, value) {
    const o = optionOf(list, value);
    return o.key ? t(o.key, o.label) : o.label;
}

/** Which actions an order allows, given where it is in its life. */
export function canRelease(order) {
    return ['draft', 'planned'].includes(order?.status);
}

export function canComplete(order) {
    return ['released', 'in_progress'].includes(order?.status);
}

export function canCancel(order) {
    return OPEN_ORDER_STATUSES.includes(order?.status);
}

export function canEdit(order) {
    return OPEN_ORDER_STATUSES.includes(order?.status);
}

/**
 * Colour a variance. Under budget is good, a little over is a warning, and
 * badly over is a problem — the same scale whether it is money or minutes.
 */
export function varianceColor(pct) {
    if (pct === null || pct === undefined) return 'default';
    if (pct <= 0) return 'success';
    if (pct <= 10) return 'warning';
    return 'error';
}

/** Efficiency reads the other way round: over 100% means beating the standard. */
export function efficiencyColor(pct) {
    if (pct === null || pct === undefined) return 'default';
    if (pct >= 95) return 'success';
    if (pct >= 80) return 'warning';
    return 'error';
}

export function passRateColor(pct) {
    if (pct === null || pct === undefined) return 'default';
    if (pct >= 98) return 'success';
    if (pct >= 90) return 'warning';
    return 'error';
}

/** "+12.5%" / "−4%" — a variance as a signed string. */
export function signedPct(pct) {
    if (pct === null || pct === undefined) return '—';
    const sign = pct > 0 ? '+' : '';
    return `${sign}${pct}%`;
}
