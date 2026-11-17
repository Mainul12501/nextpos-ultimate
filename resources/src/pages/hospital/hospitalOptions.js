/**
 * Shared vocabulary for the hospital module: the enum values the API stores,
 * the label each shows and the antd tag colour it wears. Every hospital page
 * reads these, so a status can never look one way in a list and another in a
 * report.
 *
 * Colour choices are clinical, not decorative: anything meaning "needs
 * attention now" is red, "in progress" is amber, "settled" is green.
 *
 * Each entry carries the i18n `key` + English `label`; translate lists with
 * tr() inside a computed() and single values with labelOf().
 */
import { i18n, t } from '../../i18n';

export const GENDERS = [
    { value: 'male', key: 'Male', label: 'Male' },
    { value: 'female', key: 'Female', label: 'Female' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
    .map(g => ({ value: g, label: g }));

export const PATIENT_STATUSES = [
    { value: 'active', key: 'Active', label: 'Active', color: 'success' },
    { value: 'inactive', key: 'Inactive', label: 'Inactive', color: 'default' },
    { value: 'deceased', key: 'Hosp_Deceased', label: 'Deceased', color: 'error' },
];

export const APPOINTMENT_TYPES = [
    { value: 'consultation', key: 'Hosp_Consultation', label: 'Consultation', color: 'blue' },
    { value: 'follow_up', key: 'Hosp_Follow_Up', label: 'Follow-up', color: 'cyan' },
    { value: 'procedure', key: 'Hosp_Procedure', label: 'Procedure', color: 'purple' },
    { value: 'emergency', key: 'Hosp_Emergency', label: 'Emergency', color: 'red' },
    { value: 'teleconsult', key: 'Hosp_Teleconsult', label: 'Teleconsult', color: 'geekblue' },
];

export const APPOINTMENT_STATUSES = [
    { value: 'scheduled', key: 'Scheduled', label: 'Scheduled', color: 'default' },
    { value: 'confirmed', key: 'Confirmed', label: 'Confirmed', color: 'processing' },
    { value: 'arrived', key: 'Hosp_Arrived', label: 'Arrived', color: 'warning' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'default' },
    { value: 'no_show', key: 'Hosp_No_Show', label: 'No show', color: 'error' },
];

export const VISIT_TYPES = [
    { value: 'opd', key: 'Hosp_OPD', label: 'OPD', color: 'blue' },
    { value: 'emergency', key: 'Hosp_Emergency', label: 'Emergency', color: 'red' },
    { value: 'follow_up', key: 'Hosp_Follow_Up', label: 'Follow-up', color: 'cyan' },
    { value: 'teleconsult', key: 'Hosp_Teleconsult', label: 'Teleconsult', color: 'geekblue' },
];

export const VISIT_STATUSES = [
    { value: 'open', key: 'Open', label: 'Open', color: 'processing' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
];

export const WARD_TYPES = [
    { value: 'general', key: 'General', label: 'General' },
    { value: 'private', key: 'Hosp_Ward_Private', label: 'Private' },
    { value: 'semi_private', key: 'Hosp_Ward_Semi_Private', label: 'Semi-private' },
    { value: 'icu', key: 'Hosp_Ward_ICU', label: 'ICU' },
    { value: 'nicu', key: 'Hosp_Ward_NICU', label: 'NICU' },
    { value: 'maternity', key: 'Hosp_Ward_Maternity', label: 'Maternity' },
    { value: 'isolation', key: 'Hosp_Ward_Isolation', label: 'Isolation' },
];

export const BED_STATUSES = [
    { value: 'available', key: 'Available', label: 'Available', color: 'success' },
    { value: 'occupied', key: 'Occupied', label: 'Occupied', color: 'error' },
    { value: 'reserved', key: 'Status_reserved', label: 'Reserved', color: 'warning' },
    { value: 'maintenance', key: 'Maintenance', label: 'Maintenance', color: 'default' },
];

export const ADMISSION_STATUSES = [
    { value: 'admitted', key: 'Hosp_Admitted', label: 'Admitted', color: 'processing' },
    { value: 'discharged', key: 'Hosp_Discharged', label: 'Discharged', color: 'success' },
    { value: 'transferred', key: 'Hosp_Transferred', label: 'Transferred', color: 'warning' },
    { value: 'deceased', key: 'Hosp_Deceased', label: 'Deceased', color: 'error' },
];

export const LAB_PRIORITIES = [
    { value: 'routine', key: 'Hosp_Routine', label: 'Routine', color: 'default' },
    { value: 'urgent', key: 'Rental_Pr_Urgent', label: 'Urgent', color: 'warning' },
    { value: 'stat', key: 'Hosp_STAT', label: 'STAT', color: 'error' },
];

export const LAB_STATUSES = [
    { value: 'ordered', key: 'Ordered', label: 'Ordered', color: 'default' },
    { value: 'sample_collected', key: 'Hosp_Sample_Collected', label: 'Sample collected', color: 'processing' },
    { value: 'in_progress', key: 'Rental_Mt_In_Progress', label: 'In progress', color: 'warning' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'default' },
];

export const RESULT_FLAGS = [
    { value: 'normal', key: 'Rental_Pr_Normal', label: 'Normal', color: 'success' },
    { value: 'low', key: 'Low', label: 'Low', color: 'warning' },
    { value: 'high', key: 'Rental_Pr_High', label: 'High', color: 'warning' },
    { value: 'critical', key: 'Critical', label: 'Critical', color: 'error' },
];

export const INVOICE_STATUSES = [
    { value: 'draft', key: 'Draft', label: 'Draft', color: 'default' },
    { value: 'unpaid', key: 'Unpaid', label: 'Unpaid', color: 'error' },
    { value: 'partial', key: 'Rental_Inv_Partial', label: 'Partly paid', color: 'warning' },
    { value: 'paid', key: 'Paid', label: 'Paid', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'default' },
];

export const INVOICE_ITEM_TYPES = [
    { value: 'consultation', key: 'Hosp_Consultation', label: 'Consultation' },
    { value: 'procedure', key: 'Hosp_Procedure', label: 'Procedure' },
    { value: 'medicine', key: 'Hosp_Medicine', label: 'Medicine' },
    { value: 'lab', key: 'Hosp_Lab_Test', label: 'Lab test' },
    { value: 'bed', key: 'Hosp_Bed_Charge', label: 'Bed charge' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const PAYMENT_METHODS = [
    { value: 'cash', key: 'Hosp_Cash', label: 'Cash' },
    { value: 'card', key: 'Card', label: 'Card' },
    { value: 'bank_transfer', key: 'Hosp_Bank_Transfer', label: 'Bank transfer' },
    { value: 'insurance', key: 'Rental_Ex_Insurance', label: 'Insurance' },
    { value: 'cheque', key: 'Hosp_Cheque', label: 'Cheque' },
    { value: 'other', key: 'Other', label: 'Other' },
];

export const WEEK_DAYS = [
    { value: 'mon', key: 'Mon', label: 'Mon' },
    { value: 'tue', key: 'Tue', label: 'Tue' },
    { value: 'wed', key: 'Wed', label: 'Wed' },
    { value: 'thu', key: 'Thu', label: 'Thu' },
    { value: 'fri', key: 'Fri', label: 'Fri' },
    { value: 'sat', key: 'Sat', label: 'Sat' },
    { value: 'sun', key: 'Sun', label: 'Sun' },
];

/** A list with its labels translated — call inside computed() so it re-renders on language change. */
export function tr(list) {
    return list.map(o => (o.key ? { ...o, label: t(o.key, o.label) } : o));
}

/** Look one up, falling back to the raw value so nothing renders blank. */
export function optionOf(list, value) {
    return list.find(o => o.value === value) || { value, key: '', label: value || '—', color: 'default' };
}

export function labelOf(list, value) {
    const o = optionOf(list, value);
    return o.key ? t(o.key, o.label) : o.label;
}

/** Translate a message with {named} placeholders, falling back to English. */
export function tp(key, fallback, params = {}) {
    const out = i18n.global.t(key, params);
    if (out !== key) return out;
    return Object.entries(params).reduce((s, [k, v]) => s.split(`{${k}}`).join(v), fallback);
}

/**
 * Vital-sign interpretation for adults, used to tint the readings on screen.
 * Deliberately coarse: this flags what a clinician should LOOK at, it does not
 * diagnose, and paediatric ranges differ enough that under-16s get no flag.
 */
export function vitalTone(key, value, age = null) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    if (Number.isNaN(n)) return null;
    if (age !== null && age !== undefined && age < 16) return null;

    const ranges = {
        temperature: [36.1, 37.8],
        pulse: [60, 100],
        bp_systolic: [90, 140],
        bp_diastolic: [60, 90],
        respiratory_rate: [12, 20],
        spo2: [95, 100],
    };

    const range = ranges[key];
    if (!range) return null;
    if (n < range[0]) return 'low';
    if (n > range[1]) return 'high';
    return 'normal';
}

/** Standard adult BMI bands. */
export function bmiBand(bmi) {
    if (!bmi) return null;
    if (bmi < 18.5) return { label: t('Hosp_Underweight', 'Underweight'), color: 'warning' };
    if (bmi < 25) return { label: t('Rental_Pr_Normal', 'Normal'), color: 'success' };
    if (bmi < 30) return { label: t('Hosp_Overweight', 'Overweight'), color: 'warning' };
    return { label: t('Hosp_Obese', 'Obese'), color: 'error' };
}
