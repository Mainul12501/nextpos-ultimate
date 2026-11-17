/**
 * Shared formatters for WooCommerce sync log rows (legacy StatusOverviewTab/
 * LogsTab helpers, verbatim semantics). Log rows: {created_at, action
 * "products.push"|"stock.push"|"orders.pull"..., level info|warning|error,
 * message}.
 */
import dayjs from 'dayjs';

export function formatLogDate(val) {
    return val ? dayjs(val).format('YYYY-MM-DD HH:mm') : '';
}

export function actionKind(action) {
    return String(action || '').split('.')[0];
}

export function formatAction(action, t) {
    const key = actionKind(action);
    if (key === 'products') return t('Product');
    if (key === 'orders') return t('Order');
    if (key === 'stock') return t('Stock');
    return key;
}

export function formatDirection(action, t) {
    // Orders flow WooCommerce → POS; everything else pushes POS → WooCommerce.
    return actionKind(action) === 'orders' ? t('WooCommerce_to_POS') : t('POS_to_WooCommerce');
}

export function formatStatus(level, t) {
    if (level === 'error') return t('Failed');
    if (level === 'warning') return t('Warning');
    return t('Success');
}

export function levelColor(level) {
    if (level === 'error') return 'error';
    if (level === 'warning') return 'warning';
    return 'success';
}

/**
 * Woo's REST error inside a log row's context. Failed requests log the raw
 * response as `body` (abort rows: `last_body`/`last_status`); a few rows log
 * the parsed code/message as `woo_error_code`/`woo_error_message`.
 */
export function parseWooError(ctx) {
    const c = ctx || {};
    const raw = c.body ?? c.last_body ?? null;
    let json = null;
    if (raw && typeof raw === 'object') json = raw;
    else if (typeof raw === 'string' && raw.trim().startsWith('{')) {
        try { json = JSON.parse(raw); } catch (e) { json = null; }
    }
    const status = Number(c.status ?? c.last_status ?? json?.data?.status ?? 0) || null;
    return {
        status,
        code: String(json?.code ?? c.woo_error_code ?? ''),
        message: String(json?.message ?? c.woo_error_message ?? (typeof raw === 'string' && !json ? raw : '')),
        resourceId: json?.data?.resource_id ?? c.woo_product_holding_gtin ?? null,
    };
}

/**
 * Which plain-language explanation a log row gets. Each kind has
 * Woo_Log_<Kind>_Title / _Explain / _Fix translation keys. Rows we have no
 * specific text for fall back to generic_error / generic_warning / ok.
 */
export function explainKind(record) {
    const msg = String(record?.message || '');
    const ctx = record?.context || {};
    const err = parseWooError(ctx);
    const code = err.code.toLowerCase();
    const level = record?.level;
    if (level !== 'error' && level !== 'warning') return 'Ok';

    // Message-specific rows first: their context `status` may not be a Woo API status.
    if (msg.startsWith('Aborting sync')) return 'Abort';
    if (msg.startsWith('Woo rejected the product barcode')) return 'Gtin';
    if (msg.startsWith('Skipping product sync: missing local SKU')) return 'MissingSku';
    if (msg.startsWith('Missing Woo category mapping') || msg.startsWith('Category resolution failed')) return 'Category';
    if (msg.startsWith('Missing Woo brand mapping') || msg.startsWith('Brand resolution failed')) return 'Brand';
    if (msg.startsWith('Ambiguous Woo match') || msg.startsWith('Refusing to store woocommerce_id')) return 'Ambiguous';
    if (msg.startsWith('SKU is blocked')) return 'Sku';
    if (msg.startsWith('Skipped order: could not map line item product')) return 'OrderProduct';
    if (msg.startsWith('Skipped order:')) return 'OrderCustomer';
    if (/email (uniqueness|overwrite)|Ambiguous email match/i.test(msg)) return 'CustomerEmail';
    if (/product image|images directory|brand image/i.test(msg)) return 'Image';

    if (code === 'product_invalid_global_unique_id') return 'Gtin';
    if (code === 'product_invalid_sku' || code.includes('lookup')
        || (code === 'woocommerce_rest_product_not_created' && /sku/i.test(err.message))) return 'Sku';
    if (code.includes('image')) return 'Image';
    if (err.status === 401 || err.status === 403 || code.includes('cannot_') || code.includes('authentication')) return 'Auth';
    if (err.status === 404 || code.endsWith('_invalid_id')) return 'NotFound';
    if (err.status === 429) return 'RateLimit';
    if ((err.status && err.status >= 500) || /null response|timed out|cURL error|Could not resolve host|Connection refused/i.test(`${err.message} ${msg}`)) return 'Server';

    if (level === 'error') return 'GenericError';
    if (level === 'warning') return 'GenericWarning';
    return 'Ok';
}
