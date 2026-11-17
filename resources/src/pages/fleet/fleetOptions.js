import { Car, Truck, Bus, Bike, Forklift, Caravan, Container, Package } from 'lucide-vue-next';
import { t, i18n } from '../../i18n';

/**
 * Shared vocabulary for the fleet module: the enum values the API stores, the
 * i18n key + English fallback each one shows and the antd tag colour it wears. Every fleet page reads
 * these, so a status can never look one way in the list and another in a report.
 *
 * Type icons come from lucide (the sidebar's icon set) rather than antd, which
 * ships a car and nothing else — a fleet where a bus, a forklift and a trailer
 * all render as the same car glyph tells the user nothing.
 *
 * Lists are translated through tr() inside a computed(), so they re-render
 * when the language changes.
 */

export const VEHICLE_TYPES = [
    { value: 'car', key: 'Fleet_Type_Car', label: 'Car', icon: Car },
    { value: 'van', key: 'Fleet_Type_Van', label: 'Van', icon: Caravan },
    { value: 'truck', key: 'Fleet_Type_Truck', label: 'Truck', icon: Truck },
    { value: 'bus', key: 'Fleet_Type_Bus', label: 'Bus', icon: Bus },
    { value: 'motorcycle', key: 'Fleet_Type_Motorcycle', label: 'Motorcycle', icon: Bike },
    { value: 'forklift', key: 'Fleet_Type_Forklift', label: 'Forklift', icon: Forklift },
    { value: 'trailer', key: 'Fleet_Type_Trailer', label: 'Trailer', icon: Container },
    { value: 'other', key: 'Other', label: 'Other', icon: Package },
];

/** The glyph for a vehicle's type, falling back to the generic car. */
export function typeIcon(type) {
    return (VEHICLE_TYPES.find(o => o.value === type) || VEHICLE_TYPES[0]).icon;
}

export const VEHICLE_STATUSES = [
    { value: 'active', key: 'Active', label: 'Active', color: 'success' },
    { value: 'maintenance', key: 'Rental_In_Maintenance', label: 'In maintenance', color: 'warning' },
    { value: 'inactive', key: 'Inactive', label: 'Inactive', color: 'default' },
    { value: 'sold', key: 'Sold', label: 'Sold', color: 'error' },
];

export const FUEL_TYPES = [
    { value: 'petrol', key: 'Fleet_Fuel_Petrol', label: 'Petrol' },
    { value: 'diesel', key: 'Fleet_Fuel_Diesel', label: 'Diesel' },
    { value: 'electric', key: 'Fleet_Fuel_Electric', label: 'Electric' },
    { value: 'hybrid', key: 'Fleet_Fuel_Hybrid', label: 'Hybrid' },
    { value: 'lpg', key: 'Fleet_Fuel_LPG', label: 'LPG' },
    { value: 'cng', key: 'Fleet_Fuel_CNG', label: 'CNG' },
];

export const MAINTENANCE_TYPES = [
    { value: 'service', key: 'Service', label: 'Service', color: 'blue' },
    { value: 'repair', key: 'Rental_Mt_Repair', label: 'Repair', color: 'volcano' },
    { value: 'tyres', key: 'Tyres', label: 'Tyres', color: 'purple' },
    { value: 'inspection', key: 'Rental_Mt_Inspection', label: 'Inspection', color: 'cyan' },
    { value: 'insurance', key: 'Rental_Ex_Insurance', label: 'Insurance', color: 'geekblue' },
    { value: 'other', key: 'Other', label: 'Other', color: 'default' },
];

export const MAINTENANCE_STATUSES = [
    { value: 'scheduled', key: 'Scheduled', label: 'Scheduled', color: 'processing' },
    { value: 'in_progress', key: 'Rental_Mt_In_Progress', label: 'In progress', color: 'warning' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
];

export const ASSIGNMENT_STATUSES = [
    { value: 'active', key: 'Out', label: 'Out', color: 'processing' },
    { value: 'completed', key: 'Returned', label: 'Returned', color: 'success' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'default' },
];

/**
 * Dashboard alert kinds (the API's `kind`), so the alert label follows the UI
 * language instead of the English label the API sends.
 */
export const ALERT_KINDS = [
    { value: 'insurance_expiry', key: 'Rental_Ex_Insurance', label: 'Insurance' },
    { value: 'registration_expiry', key: 'Fleet_Registration', label: 'Registration' },
    { value: 'inspection_expiry', key: 'Rental_Mt_Inspection', label: 'Inspection' },
    { value: 'service', key: 'Fleet_Next_Service', label: 'Next service' },
];

/** Translated copy of an option list (call inside a computed). */
export function tr(list) {
    return list.map(o => ({ ...o, label: t(o.key, o.label) }));
}

/** Look one up, falling back to the raw value so nothing renders blank. */
export function optionOf(list, value) {
    return list.find(o => o.value === value) || { value, key: '', label: value || '—', color: 'default' };
}

export function labelOf(list, value) {
    const o = optionOf(list, value);
    return o.key ? t(o.key, o.label) : o.label;
}

/** Translate a message with {placeholders}, falling back to the English text. */
export function tp(key, fallback, params = {}) {
    const out = i18n.global.t(key, params);
    if (out !== key) return out;
    return Object.entries(params).reduce((str, [k, v]) => str.replace(`{${k}}`, v), fallback);
}

/**
 * How urgent a renewal is. `days` is negative once the date has passed.
 * Returns null when there is nothing to warn about, so callers can `v-if` it.
 */
export function expiryTone(days, soonWithin = 30) {
    if (days === null || days === undefined) return null;
    if (days < 0) return { color: 'error', text: tp('Fleet_Overdue_By_Days', 'Overdue by {n}d', { n: Math.abs(days) }), level: 'overdue' };
    if (days === 0) return { color: 'error', text: t('Crm_Due_Today', 'Due today'), level: 'overdue' };
    if (days <= soonWithin) return { color: 'warning', text: tp('Fleet_Days_Left', '{n}d left', { n: days }), level: 'soon' };
    return null;
}
