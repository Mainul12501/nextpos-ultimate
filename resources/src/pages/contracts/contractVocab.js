import { t, i18n } from '../../i18n';

/**
 * Contract vocabularies — the enum values the API stores plus the i18n key and
 * English fallback each one shows. Party types: customer | employee.
 *
 * Lists are translated through tr() inside a computed(), so they re-render
 * when the language changes.
 */
export const CONTRACT_TYPES = [
    { value: 'service', key: 'Service', label: 'Service' },
    { value: 'lease', key: 'Contract_Type_Lease', label: 'Lease' },
    { value: 'sales', key: 'Sales', label: 'Sales' },
    { value: 'nda', key: 'Contract_Type_NDA', label: 'NDA' },
    { value: 'employment', key: 'Contract_Type_Employment', label: 'Employment' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const CONTRACT_STATUSES = [
    { value: 'draft', key: 'Draft', label: 'Draft' },
    { value: 'active', key: 'Active', label: 'Active' },
    { value: 'expired', key: 'Expired', label: 'Expired' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled' },
];

/** Contract task statuses (contract_tasks.status). */
export const TASK_STATUSES = [
    { value: 'pending', key: 'Pending', label: 'Pending' },
    { value: 'in_progress', key: 'In_Progress', label: 'In Progress' },
    { value: 'completed', key: 'Completed', label: 'Completed' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled' },
];

/** Translated copy of an option list (call inside a computed). */
export function tr(list) {
    return list.map(o => ({ ...o, label: t(o.key, o.label) }));
}

/** One value's translated label, falling back to the raw value. */
export function labelOf(list, value) {
    const o = list.find(x => x.value === value);
    return o ? t(o.key, o.label) : value;
}

/**
 * Parameterised translation with an English fallback: `fallback` uses the
 * same {name} placeholders and is used until the key is seeded.
 */
export function tf(key, fallback, params = {}) {
    const out = i18n.global.t(key, params);
    if (out !== key) return out;
    return String(fallback).replace(/\{(\w+)\}/g, (m, k) => (params[k] !== undefined ? params[k] : m));
}

export const contractTypeLabel = v => labelOf(CONTRACT_TYPES, v);
export const contractStatusLabel = v => labelOf(CONTRACT_STATUSES, v);
export const taskStatusLabel = v => labelOf(TASK_STATUSES, v);

export function contractStatusColor(v) {
    switch (v) {
        case 'active': return 'success';
        case 'draft': return 'default';
        case 'expired': return 'warning';
        case 'cancelled': return 'error';
        default: return 'default';
    }
}
