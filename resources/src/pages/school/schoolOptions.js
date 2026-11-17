import { t, i18n } from '../../i18n';

/**
 * Shared vocabulary for the school module: the enum values the API stores, the
 * i18n key + English fallback each one shows and the antd tag colour it wears.
 * Every school page reads these, so a status can never look one way in a list
 * and another in a report.
 *
 * Lists are translated through tr() inside a computed(), so they re-render
 * when the language changes.
 */

export const GENDERS = [
    { value: 'male', key: 'Male', label: 'Male' },
    { value: 'female', key: 'Female', label: 'Female' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const STUDENT_STATUSES = [
    { value: 'active', key: 'Active', label: 'Active', color: 'success' },
    { value: 'inactive', key: 'Inactive', label: 'Inactive', color: 'default' },
    { value: 'graduated', key: 'School_Status_Graduated', label: 'Graduated', color: 'processing' },
    { value: 'transferred', key: 'School_Status_Transferred', label: 'Transferred', color: 'warning' },
    { value: 'expelled', key: 'School_Status_Expelled', label: 'Expelled', color: 'error' },
];

export const ENROLLMENT_STATUSES = [
    { value: 'active', key: 'Active', label: 'Active', color: 'success' },
    { value: 'promoted', key: 'School_Status_Promoted', label: 'Promoted', color: 'processing' },
    { value: 'repeated', key: 'School_Status_Repeated', label: 'Repeated', color: 'warning' },
    { value: 'transferred', key: 'School_Status_Transferred', label: 'Transferred', color: 'default' },
    { value: 'left', key: 'School_Status_Left', label: 'Left', color: 'error' },
];

export const ATTENDANCE_STATUSES = [
    { value: 'present', key: 'Present', label: 'Present', color: 'success', shortKey: 'School_Att_Short_Present', short: 'P' },
    { value: 'absent', key: 'Absent', label: 'Absent', color: 'error', shortKey: 'School_Att_Short_Absent', short: 'A' },
    { value: 'late', key: 'Late', label: 'Late', color: 'warning', shortKey: 'School_Att_Short_Late', short: 'L' },
    { value: 'excused', key: 'Excused', label: 'Excused', color: 'processing', shortKey: 'School_Att_Short_Excused', short: 'E' },
    { value: 'half_day', key: 'School_Half_Day', label: 'Half day', color: 'default', shortKey: 'School_Att_Short_Half_Day', short: 'H' },
];

export const SUBJECT_TYPES = [
    { value: 'core', key: 'School_Subject_Core', label: 'Core', color: 'blue' },
    { value: 'elective', key: 'School_Subject_Elective', label: 'Elective', color: 'purple' },
    { value: 'optional', key: 'Optional', label: 'Optional', color: 'default' },
];

export const EXAM_TERMS = [
    { value: 'term_1', key: 'School_Term_1', label: 'Term 1' },
    { value: 'term_2', key: 'School_Term_2', label: 'Term 2' },
    { value: 'term_3', key: 'School_Term_3', label: 'Term 3' },
    { value: 'final', key: 'School_Term_Final', label: 'Final' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const EXAM_STATUSES = [
    { value: 'draft', key: 'Draft', label: 'Draft', color: 'default' },
    { value: 'scheduled', key: 'Scheduled', label: 'Scheduled', color: 'processing' },
    { value: 'ongoing', key: 'Ongoing', label: 'Ongoing', color: 'warning' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'cyan' },
    { value: 'published', key: 'Published', label: 'Published', color: 'success' },
];

export const FEE_FREQUENCIES = [
    { value: 'once', key: 'School_Freq_Once', label: 'One-off' },
    { value: 'monthly', key: 'Monthly', label: 'Monthly' },
    { value: 'termly', key: 'School_Freq_Termly', label: 'Termly' },
    { value: 'yearly', key: 'Rental_Period_Yearly', label: 'Yearly' },
];

export const INVOICE_STATUSES = [
    { value: 'draft', key: 'Draft', label: 'Draft', color: 'default' },
    { value: 'unpaid', key: 'Unpaid', label: 'Unpaid', color: 'error' },
    { value: 'partial', key: 'Rental_Inv_Partial', label: 'Partly paid', color: 'warning' },
    { value: 'paid', key: 'Paid', label: 'Paid', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'default' },
];

export const PAYMENT_METHODS = [
    { value: 'cash', key: 'School_Pay_Cash', label: 'Cash' },
    { value: 'card', key: 'Card', label: 'Card' },
    { value: 'bank_transfer', key: 'School_Pay_Bank_Transfer', label: 'Bank transfer' },
    { value: 'cheque', key: 'School_Pay_Cheque', label: 'Cheque' },
    { value: 'mobile_money', key: 'School_Pay_Mobile_Money', label: 'Mobile money' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const WEEK_DAYS = [
    { value: 'mon', key: 'School_Day_Monday', label: 'Monday', shortKey: 'Mon', short: 'Mon' },
    { value: 'tue', key: 'School_Day_Tuesday', label: 'Tuesday', shortKey: 'Tue', short: 'Tue' },
    { value: 'wed', key: 'School_Day_Wednesday', label: 'Wednesday', shortKey: 'Wed', short: 'Wed' },
    { value: 'thu', key: 'School_Day_Thursday', label: 'Thursday', shortKey: 'Thu', short: 'Thu' },
    { value: 'fri', key: 'School_Day_Friday', label: 'Friday', shortKey: 'Fri', short: 'Fri' },
    { value: 'sat', key: 'School_Day_Saturday', label: 'Saturday', shortKey: 'Sat', short: 'Sat' },
    { value: 'sun', key: 'School_Day_Sunday', label: 'Sunday', shortKey: 'Sun', short: 'Sun' },
];

/** Translated copy of an option list (call inside a computed). */
export function tr(list) {
    return list.map(o => ({
        ...o,
        label: t(o.key, o.label),
        ...(o.shortKey ? { short: t(o.shortKey, o.short) } : {}),
    }));
}

/** Look one up, falling back to the raw value so nothing renders blank. */
export function optionOf(list, value) {
    return list.find(o => o.value === value) || { value, key: '', label: value || '—', color: 'default' };
}

export function labelOf(list, value) {
    const o = optionOf(list, value);
    return o.key ? t(o.key, o.label) : o.label;
}

/**
 * Translate a message with {named} placeholders; before the seeder has run the
 * key is missing, so fill the English fallback instead.
 */
export function tp(key, fallback, params = {}) {
    const out = i18n.global.t(key, params);
    if (out !== key) return out;
    return Object.entries(params).reduce((s, [k, v]) => s.split(`{${k}}`).join(String(v)), fallback);
}

/** Translated short label (e.g. "Mon"), falling back to the full label. */
export function shortOf(list, value) {
    const o = optionOf(list, value);
    if (o.shortKey) return t(o.shortKey, o.short);
    return o.short || labelOf(list, value);
}

/**
 * Colour for a grade, matching the backend's default scale in
 * ExamResult::gradeFor(). If a school changes that scale, change this too —
 * they are two halves of one decision.
 */
export function gradeColor(grade) {
    if (!grade) return 'default';
    if (grade === 'AB') return 'default';
    if (grade.startsWith('A')) return 'success';
    if (grade.startsWith('B')) return 'cyan';
    if (grade === 'C') return 'processing';
    if (grade === 'D') return 'warning';
    return 'error';
}

/** Attendance / collection rates share one traffic-light reading. */
export function rateColor(rate, { good = 90, fair = 75 } = {}) {
    if (rate === null || rate === undefined) return 'default';
    if (rate >= good) return 'success';
    if (rate >= fair) return 'warning';
    return 'error';
}
