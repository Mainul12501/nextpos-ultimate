import {
    Package, Boxes, Users, ReceiptText, LayoutGrid, Truck,
} from 'lucide-vue-next';
import { i18n, t } from '../../i18n';

/**
 * Shared vocabulary for the Shopify module: the values the API stores, the
 * label each shows and the antd colour it wears. Every Shopify page reads from
 * here, so a run cannot look "failed" on one screen and "error" on another.
 *
 * Each entry carries the i18n `key` + English `label` (entities also a
 * `hintKey`, directions a `shortKey`); translate lists with tr() inside a
 * computed() and single values with labelOf() / entityOf().
 */

export const STORE_STATUSES = [
    { value: 'connected', key: 'Connected', label: 'Connected', color: 'success' },
    { value: 'error', key: 'Shopify_St_Error', label: 'Error', color: 'error' },
    { value: 'disconnected', key: 'Not_connected', label: 'Not connected', color: 'default' },
];

/**
 * The six things this module moves. `directions` is the honest list of what is
 * possible, not what is convenient: orders are pull-only because Shopify is
 * where a customer places one — pushing an ERP sale back would invent an order
 * that never happened.
 */
export const ENTITIES = [
    {
        value: 'products',
        key: 'Products',
        hintKey: 'Shopify_Hint_Products',
        label: 'Products',
        icon: Package,
        directions: ['push', 'pull'],
        hint: 'Catalogue, prices and variants.',
    },
    {
        value: 'inventory',
        key: 'Inventory',
        hintKey: 'Shopify_Hint_Inventory',
        label: 'Inventory',
        icon: Boxes,
        directions: ['push', 'pull'],
        hint: 'Stock on hand for the mapped warehouse and location.',
    },
    {
        value: 'customers',
        key: 'Customers',
        hintKey: 'Shopify_Hint_Customers',
        label: 'Customers',
        icon: Users,
        directions: ['push', 'pull'],
        hint: 'Matched on email — customers without one are skipped.',
    },
    {
        value: 'orders',
        key: 'Orders',
        hintKey: 'Shopify_Hint_Orders',
        label: 'Orders',
        icon: ReceiptText,
        directions: ['pull'],
        hint: 'Imported as sales. Stock moves only for fulfilled orders.',
    },
    {
        value: 'collections',
        key: 'Collections',
        hintKey: 'Shopify_Hint_Collections',
        label: 'Collections',
        icon: LayoutGrid,
        directions: ['push', 'pull'],
        hint: 'Mapped to ERP categories.',
    },
    {
        value: 'fulfillments',
        key: 'Shopify_Fulfillments',
        hintKey: 'Shopify_Hint_Fulfillments',
        label: 'Fulfillments',
        icon: Truck,
        directions: ['push', 'pull'],
        hint: 'Push marks shipped orders fulfilled; pull refreshes their status.',
    },
];

/**
 * What a store syncs automatically when nobody has chosen a plan. Mirrors
 * ShopifyStore::DEFAULT_AUTO_PLAN on the server — the two pair of steps every
 * shop wants and no shop is surprised by: orders in, stock out.
 */
export const DEFAULT_AUTO_PLAN = [
    { entity: 'orders', direction: 'pull' },
    { entity: 'inventory', direction: 'push' },
];

/** Every entity+direction an automatic step could be. */
export function autoStepChoices() {
    return ENTITIES.flatMap(e => e.directions.map(d => ({ entity: e.value, direction: d })));
}

export function stepKey(step) {
    return `${step.entity}:${step.direction}`;
}

/** "Orders · Pull" — short enough for a tag, clear about which way it goes. */
export function stepLabel(step) {
    return `${entityOf(step.entity).label} · ${directionShort(step.direction)}`;
}

export const RUN_STATUSES = [
    { value: 'pending', key: 'Pending', label: 'Pending', color: 'default' },
    { value: 'running', key: 'Running', label: 'Running', color: 'processing' },
    { value: 'completed', key: 'Completed', label: 'Completed', color: 'success' },
    { value: 'failed', key: 'Failed', label: 'Failed', color: 'error' },
    { value: 'cancelled', key: 'Cancelled', label: 'Cancelled', color: 'warning' },
    // Not a stored status: a run whose worker stopped sending heartbeats.
    { value: 'stale', key: 'Shopify_Stalled', label: 'Stalled', color: 'error' },
];

export const DIRECTIONS = [
    { value: 'push', key: 'Shopify_Dir_Push', label: 'ERP → Shopify', shortKey: 'Shopify_Push', short: 'Push', color: 'blue' },
    { value: 'pull', key: 'Shopify_Dir_Pull', label: 'Shopify → ERP', shortKey: 'Shopify_Pull', short: 'Pull', color: 'purple' },
];

export const LOG_LEVELS = [
    { value: 'info', key: 'Info', label: 'Info', color: 'default' },
    { value: 'warning', key: 'Warning', label: 'Warning', color: 'warning' },
    { value: 'error', key: 'Shopify_St_Error', label: 'Error', color: 'error' },
];

export const LINK_TYPES = [
    { value: 'product', key: 'Product', label: 'Product' },
    { value: 'variant', key: 'Variant', label: 'Variant' },
    { value: 'inventory_item', key: 'Shopify_Inventory_Item', label: 'Inventory item' },
    { value: 'customer', key: 'Customer', label: 'Customer' },
    { value: 'order', key: 'Order', label: 'Order' },
    { value: 'collection', key: 'Collection', label: 'Collection' },
];

export const PRICE_FIELDS = [
    { value: 'price', key: 'Shopify_Retail_Price', label: 'Retail price' },
    { value: 'wholesale_price', key: 'Shopify_Wholesale_Price', label: 'Wholesale price' },
    { value: 'min_price', key: 'Shopify_Minimum_Price', label: 'Minimum price' },
];

export const WEBHOOK_STATUSES = [
    { value: 'pending', key: 'Pending', label: 'Pending', color: 'processing' },
    { value: 'processed', key: 'Status_Processed', label: 'Processed', color: 'success' },
    { value: 'failed', key: 'Failed', label: 'Failed', color: 'error' },
    { value: 'ignored', key: 'Status_Ignored', label: 'Ignored', color: 'default' },
];

/**
 * Topics worth subscribing to, shown on the store's webhook tab so the values
 * can be copied into Shopify rather than remembered.
 */
export const WEBHOOK_TOPICS = [
    'orders/create',
    'orders/updated',
    'orders/cancelled',
    'orders/fulfilled',
    'products/create',
    'products/update',
    'customers/create',
    'customers/update',
    'inventory_levels/update',
];

/** One option with its label (and hint) translated. */
function trOne(o) {
    if (!o.key && !o.hintKey) return o;
    const out = { ...o };
    if (o.key) out.label = t(o.key, o.label);
    if (o.hintKey) out.hint = t(o.hintKey, o.hint);
    return out;
}

/** A list with its labels translated — call inside computed() so it re-renders on language change. */
export function tr(list) {
    return list.map(trOne);
}

/** Look one up (label translated), falling back to the raw value so nothing renders blank. */
export function optionOf(list, value) {
    const o = list.find(x => x.value === value);
    return o ? trOne(o) : { value, label: value || '—', color: 'default' };
}

export function labelOf(list, value) {
    return optionOf(list, value).label;
}

/** "Push" / "Pull", translated. */
export function directionShort(value) {
    const o = DIRECTIONS.find(d => d.value === value);
    return o ? t(o.shortKey, o.short) : value;
}

export function entityOf(value) {
    const e = ENTITIES.find(x => x.value === value);
    return e ? trOne(e) : { value, label: value, icon: Package, directions: [] };
}

/** Translate a message with {named} placeholders, falling back to English. */
export function tp(key, fallback, params = {}) {
    const out = i18n.global.t(key, params);
    if (out !== key) return out;
    return Object.entries(params).reduce((str, [k, v]) => str.split(`{${k}}`).join(v), fallback);
}

/**
 * Progress stages a run reports. The server writes them as plain words; the
 * known ones are translated here, anything else is shown as it came.
 */
const STAGES = {
    working: ['Shopify_Stage_Working', 'working'],
    starting: ['Shopify_Stage_Starting', 'starting'],
    queued: ['Shopify_Stage_Queued', 'queued'],
    done: ['Shopify_Stage_Done', 'done'],
    'pushing products': ['Shopify_Stage_Pushing_Products', 'pushing products'],
    'pushing inventory': ['Shopify_Stage_Pushing_Inventory', 'pushing inventory'],
    'pushing customers': ['Shopify_Stage_Pushing_Customers', 'pushing customers'],
    'pushing collections': ['Shopify_Stage_Pushing_Collections', 'pushing collections'],
    'pushing fulfillments': ['Shopify_Stage_Pushing_Fulfillments', 'pushing fulfillments'],
    'pulling products': ['Shopify_Stage_Pulling_Products', 'pulling products'],
    'pulling inventory': ['Shopify_Stage_Pulling_Inventory', 'pulling inventory'],
    'pulling customers': ['Shopify_Stage_Pulling_Customers', 'pulling customers'],
    'pulling orders': ['Shopify_Stage_Pulling_Orders', 'pulling orders'],
    'pulling collections': ['Shopify_Stage_Pulling_Collections', 'pulling collections'],
    'pulling fulfillment status': ['Shopify_Stage_Pulling_Fulfillment_Status', 'pulling fulfillment status'],
};

export function stageText(stage) {
    const hit = STAGES[stage];
    return hit ? t(hit[0], hit[1]) : stage;
}

/** Whether an entity can travel in a given direction at all. */
export function supports(entity, direction) {
    return entityOf(entity).directions.includes(direction);
}

/** A run is worth polling only while it might still change. */
export function isLive(status) {
    return status === 'running' || status === 'pending';
}
