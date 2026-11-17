import { t } from '../../i18n';

/**
 * Project status vocabulary used by the legacy-style Projects / Tasks pages.
 * NOTE the short values: `progress`, `hold`. Labels are translated through
 * tr() (call it inside a computed) / projectStatusLabel().
 */
export const PROJECT_STATUSES = [
    { value: 'not_started', key: 'Not_Started', label: 'Not Started' },
    { value: 'progress', key: 'In_Progress', label: 'In Progress' },
    { value: 'hold', key: 'On_Hold', label: 'On Hold' },
    { value: 'completed', key: 'Completed', label: 'Completed' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled' },
];

/** Translated copy of an option list (call inside a computed). */
export function tr(list) {
    return list.map(o => ({ ...o, label: t(o.key, o.label) }));
}

export function projectStatusLabel(v) {
    const o = PROJECT_STATUSES.find(s => s.value === v);
    return o ? t(o.key, o.label) : v;
}

export function projectStatusColor(v) {
    switch (v) {
        case 'progress': return 'processing';
        case 'completed': return 'success';
        case 'hold': return 'warning';
        case 'cancelled': return 'error';
        default: return 'default';
    }
}
