// Optional business modules the admin can switch on/off in System Settings →
// Modules. `key` MUST match the top-level entry key in config/menu.js — the
// sidebar filter and the router guard both key on it. Core commerce (products,
// sales, purchases, people, settings, reports…) is intentionally not listed:
// it can never be turned off.
//
// Flags live in settings.module_flags ({key: bool}) and reach the SPA on the
// get_user_auth payload (auth.user.module_flags). A null map or a missing key
// means the module is ENABLED — so existing installs see no change.

export const TOGGLEABLE_MODULES = [
    { key: 'Store', label: 'Store / E-commerce', description: 'Online storefront: orders, collections, banners, pages and Real Estate listings.', pathPrefixes: ['store', 'realestate'] },
    { key: 'hrm', label: 'HRM', description: 'Employees, attendance, payroll, contracts and the knowledge base.', pathPrefixes: ['hrm', 'contracts'] },
    { key: 'recruit', label: 'Recruits & Jobs', description: 'Job postings, candidates, applications and interviews.', pathPrefixes: ['recruit'] },
    { key: 'meeting', label: 'Meetings', description: 'Meeting planning, attendance and reports.', pathPrefixes: ['meeting', 'meetings'] },
    { key: 'marketing', label: 'Marketing', description: 'Campaigns and marketing tools.', pathPrefixes: [] },
    { key: 'accounting', label: 'Accounting', description: 'Chart of accounts, journal entries, financial reports, expenses and deposits.', pathPrefixes: ['accounting-v2'] },
    { key: 'EWallet', label: 'E-Wallet', description: 'Customer wallet balances and wallet items.', pathPrefixes: ['ewallet'] },
    { key: 'commissions', label: 'Commissions', description: 'Agent commission programs, rules and receipts.', pathPrefixes: ['commissions'] },
    { key: 'promotions', label: 'Promotions', description: 'Discount promotions applied at the POS checkout.', pathPrefixes: ['promotions'] },
    { key: 'kitchen', label: 'Kitchen', description: 'Kitchen display screen and kitchen performance report.', pathPrefixes: ['kitchen-display', 'kitchen'] },
    // Master switch for the whole Ecommerce Platforms section. The connectors
    // below name it as their `parent`: turning it off disables all of them,
    // whatever their own switch says.
    { key: 'ecommerce_platforms', label: 'Ecommerce Platforms', description: 'All external store connectors: WooCommerce, Shopify, Salla, PrestaShop and Jumia.', pathPrefixes: [] },
    { key: 'woocommerce_settings', parent: 'ecommerce_platforms', label: 'WooCommerce', description: 'WooCommerce store synchronization.', pathPrefixes: ['woocommerce'] },
    { key: 'shopify', parent: 'ecommerce_platforms', label: 'Shopify', description: 'Shopify store synchronization and logs.', pathPrefixes: ['shopify'] },
    { key: 'salla', parent: 'ecommerce_platforms', label: 'Salla', description: 'Salla store synchronization: products, stock and orders.', pathPrefixes: ['integrations/salla'] },
    { key: 'prestashop', parent: 'ecommerce_platforms', label: 'PrestaShop', description: 'PrestaShop store synchronization.', pathPrefixes: ['integrations/prestashop'] },
    { key: 'jumia', parent: 'ecommerce_platforms', label: 'Jumia', description: 'Jumia marketplace: price & stock push, order import.', pathPrefixes: ['integrations/jumia'] },
    { key: 'documents', label: 'Document Archive', description: 'Central document storage and archiving.', pathPrefixes: ['documents'] },
    { key: 'subscription_product', label: 'Subscription Products', description: 'Recurring product subscriptions.', pathPrefixes: ['subscriptions'] },
    { key: 'manufacturing', label: 'Manufacturing (MRP)', description: 'Bills of materials, production orders, work centers, quality and planning.', pathPrefixes: ['mrp'] },
    { key: 'assets', label: 'Asset Management', description: 'Company assets, assignments, maintenance, transfers and depreciation.', pathPrefixes: ['assets'] },
    { key: 'projects', label: 'Projects & Tasks', description: 'Projects, tasks, milestones, timesheets and project reports.', pathPrefixes: ['projects', 'tasks'] },
    { key: 'bookings', label: 'Booking Management', description: 'Bookings, calendar and trays.', pathPrefixes: ['bookings'] },
    { key: 'service', label: 'Service & Maintenance', description: 'Service jobs, technicians and checklists.', pathPrefixes: ['service'] },
    { key: 'fleet', label: 'Fleet Management', description: 'Vehicles, maintenance, fuel logs, assignments and fleet reports.', pathPrefixes: ['fleet'] },
    { key: 'crm', label: 'CRM', description: 'Leads, sales pipeline and deals, activities and follow-ups, lead capture and sales reports.', pathPrefixes: ['crm'] },
    { key: 'rental', label: 'Rental Management', description: 'Rental contracts, reservations, invoices & payments, maintenance & expenses, availability timeline, items and customers.', pathPrefixes: ['rental'] },
    { key: 'hospital', label: 'Hospital Management', description: 'Patients, doctors, appointments, visits, admissions, wards, lab and billing.', pathPrefixes: ['hospital'] },
    { key: 'school', label: 'School Management', description: 'Students, teachers, academics, exams, timetable and fees.', pathPrefixes: [] },
    { key: 'couriers', label: 'Courier Integration', description: 'Courier accounts, one-click booking, tracking, COD and returns.', pathPrefixes: ['couriers'] },
];

const MODULE_BY_KEY = Object.fromEntries(TOGGLEABLE_MODULES.map(m => [m.key, m]));

// URL prefix ('hospital', 'integrations/salla') -> module key, for the router
// guard. Prefixes are one or two path segments; the longer one wins.
const PREFIX_TO_MODULE = {};
for (const m of TOGGLEABLE_MODULES) {
    for (const p of m.pathPrefixes) PREFIX_TO_MODULE[p] = m.key;
}

/** True when `key` is enabled under the given flags map (null map = all on).
 *  A module whose `parent` is switched off counts as off too. */
export function isModuleEnabled(flags, key) {
    const mod = MODULE_BY_KEY[key];
    if (!mod) return true; // not toggleable -> always on
    if (!flags || typeof flags !== 'object') return true;
    if (flags[key] === false) return false;
    return mod.parent ? isModuleEnabled(flags, mod.parent) : true;
}

/** Module key owning an SPA path ('/hospital/patients' -> 'hospital'), or null. */
export function moduleKeyForPath(path) {
    const segs = String(path || '').replace(/^\/+/, '').split('/');
    return PREFIX_TO_MODULE[segs.slice(0, 2).join('/')] || PREFIX_TO_MODULE[segs[0]] || null;
}
