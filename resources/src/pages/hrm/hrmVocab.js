import { t } from '../../i18n';

/**
 * HRM shared vocabulary. Employment types, genders, marital statuses and leave
 * statuses are backend values; each carries an i18n key + English fallback.
 * Lists are translated through tr() inside a computed(), so they re-render
 * when the language changes.
 */

export const EMPLOYMENT_TYPES = [
    { value: 'full_time', key: 'Hrm_Full_Time', label: 'Full-time' },
    { value: 'part_time', key: 'Hrm_Part_Time', label: 'Part-time' },
    { value: 'self_employed', key: 'Hrm_Self_Employed', label: 'Self-employed' },
    { value: 'freelance', key: 'Hrm_Freelance', label: 'Freelance' },
    { value: 'contract', key: 'Contract', label: 'Contract' },
    { value: 'internship', key: 'Internship', label: 'Internship' },
    { value: 'apprenticeship', key: 'Hrm_Apprenticeship', label: 'Apprenticeship' },
    { value: 'seasonal', key: 'Hrm_Seasonal', label: 'Seasonal' },
];

export const GENDERS = [
    { value: 'male', key: 'Male', label: 'Male' },
    { value: 'female', key: 'Female', label: 'Female' },
];

export const MARITAL_STATUSES = [
    { value: 'married', key: 'Hrm_Married', label: 'Married' },
    { value: 'single', key: 'Hrm_Single', label: 'Single' },
    { value: 'divorced', key: 'Hrm_Divorced', label: 'Divorced' },
];

export const LEAVE_STATUSES = [
    { value: 'approved', key: 'Approved', label: 'Approved' },
    { value: 'pending', key: 'Pending', label: 'Pending' },
    { value: 'rejected', key: 'Rejected', label: 'Rejected' },
];

const LEAVE_STATUS_COLORS = { approved: 'green', pending: 'orange', rejected: 'red' };

/** Translate a whole option list (call inside computed()). */
export function tr(list) {
    return list.map(o => ({ ...o, label: t(o.key, o.label) }));
}

export function leaveStatusColor(status) {
    return LEAVE_STATUS_COLORS[status] || 'default';
}

export function leaveStatusLabel(status) {
    const found = LEAVE_STATUSES.find(s => s.value === status);
    return found ? t(found.key, found.label) : (status || '—');
}
