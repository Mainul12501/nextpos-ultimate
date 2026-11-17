import { t, i18n } from '../../i18n';

/**
 * Shared vocabulary for the Projects Management workspace.
 *
 * Project and task statuses are the values the EXISTING projects/tasks tables
 * already store — they are re-declared here (not invented) so the new pages
 * label them identically to the old ones.
 *
 * Each entry carries an i18n `key` + English `label`; lists are translated
 * through tr() inside a computed() so they re-render on a language switch.
 */

export const PROJECT_STATUSES = [
    { value: 'not_started', key: 'Not_Started', label: 'Not Started', color: 'default' },
    { value: 'progress', key: 'In_Progress', label: 'In Progress', color: 'processing' },
    { value: 'on_hold', key: 'On_Hold', label: 'On Hold', color: 'warning' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'error' },
];

export const TASK_STATUSES = [
    { value: 'not_started', key: 'Not_Started', label: 'Not Started', color: 'default' },
    { value: 'progress', key: 'In_Progress', label: 'In Progress', color: 'processing' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'error' },
];

export const TASK_PRIORITIES = [
    { value: 'low', key: 'Low', label: 'Low', color: 'default' },
    { value: 'medium', key: 'Medium', label: 'Medium', color: 'processing' },
    { value: 'high', key: 'Rental_Pr_High', label: 'High', color: 'warning' },
    { value: 'urgent', key: 'Rental_Pr_Urgent', label: 'Urgent', color: 'error' },
];

export const MILESTONE_STATUSES = [
    { value: 'pending', key: 'Pending', label: 'Pending', color: 'default' },
    { value: 'in_progress', key: 'In_Progress', label: 'In Progress', color: 'processing' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
    { value: 'delayed', key: 'Proj_Delayed', label: 'Delayed', color: 'error' },
];

/** Board column order — left to right is the natural flow of work. */
export const BOARD_COLUMNS = [
    { key: 'not_started', i18nKey: 'Not_Started', label: 'Not Started', color: '#8c8c8c' },
    { key: 'progress', i18nKey: 'In_Progress', label: 'In Progress', color: '#1677ff' },
    { key: 'completed', i18nKey: 'Completed', label: 'Completed', color: '#16a34a' },
    { key: 'cancelled', i18nKey: 'Cancelled', label: 'Cancelled', color: '#ff4d4f' },
];

/** Translated copy of an option list (call inside a computed). */
export function tr(list) {
    return list.map(o => ({ ...o, label: t(o.i18nKey || o.key, o.label) }));
}

/** Parameterised translation with an English fallback while the key is missing. */
export function tp(key, params, fallback) {
    const out = i18n.global.t(key, params);
    return out === key ? fallback : out;
}

export function optionOf(list, value) {
    return list.find(o => o.value === value) || { value, key: '', label: value || '—', color: 'default' };
}

export function labelOf(list, value) {
    const o = optionOf(list, value);
    return o.key ? t(o.key, o.label) : o.label;
}

/** Progress bar colour: green when done, amber mid-way, grey at the start. */
export function progressColor(percent) {
    if (percent >= 100) return '#16a34a';
    if (percent >= 50) return '#6d28d9';
    if (percent > 0) return '#faad14';
    return '#d9d9d9';
}

/** "in 3 days" / "5 days late" — null when there is no deadline to speak of. */
export function dueLabel(days) {
    if (days === null || days === undefined) return null;
    if (days < 0) {
        const n = Math.abs(days);
        return { text: tp('Proj_Days_Late', { n }, `${n}d late`), color: 'error' };
    }
    if (days === 0) return { text: t('Crm_Due_Today', 'Due today'), color: 'warning' };
    const text = tp('Proj_In_Days', { n: days }, `in ${days}d`);
    return { text, color: days <= 7 ? 'warning' : 'default' };
}
