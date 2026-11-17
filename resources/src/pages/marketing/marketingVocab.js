import { t } from '../../i18n';

/**
 * Marketing vocabulary shared by the module's pages. Channel and status
 * values are backend enums (campaigns.type / campaigns.status and recipient
 * statuses); labels are translated here (channels stay brand names except
 * Email).
 */

export const CHANNELS = ['sms', 'email', 'whatsapp'];

export const CAMPAIGN_STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'];

const CHANNEL_LABELS = { sms: 'SMS', email: 'Email', whatsapp: 'WhatsApp' };

export function channelLabel(type) {
    if (type === 'email') return t('Email', 'Email');
    return CHANNEL_LABELS[type] || type || '—';
}

/** Campaign + recipient status → i18n key (all exist in en.php). */
const STATUS_KEYS = {
    draft: 'Draft',
    scheduled: 'Scheduled',
    sending: 'Sending',
    sent: 'Sent',
    failed: 'Failed',
    cancelled: 'Cancelled',
    pending: 'Pending',
    delivered: 'Delivered',
    skipped: 'Skipped',
};

/** Translated status label; unknown values: "not_started" → "Not started". */
export function plainLabel(v) {
    if (!v) return '—';
    const s = String(v).replace(/_/g, ' ');
    const en = s.charAt(0).toUpperCase() + s.slice(1);
    return STATUS_KEYS[v] ? t(STATUS_KEYS[v], en) : en;
}

const STATUS_COLORS = {
    draft: 'default',
    scheduled: 'blue',
    sending: 'orange',
    sent: 'green',
    failed: 'red',
    cancelled: 'default',
};

export function campaignStatusColor(status) {
    return STATUS_COLORS[status] || 'default';
}

const RECIPIENT_COLORS = {
    pending: 'orange',
    sent: 'green',
    delivered: 'green',
    failed: 'red',
    skipped: 'default',
};

export function recipientStatusColor(status) {
    return RECIPIENT_COLORS[status] || 'default';
}

/** Statuses a campaign can be edited/sent in (legacy canEdit/canSend). */
export function campaignEditable(status) {
    return ['draft', 'scheduled', 'failed', 'cancelled'].includes(status);
}

export function campaignSendable(status) {
    return ['draft', 'scheduled', 'failed'].includes(status);
}

/** Placeholders the dispatcher replaces per recipient. */
export const PERSONALIZATION_VARIABLES = [
    '{customer_name}', '{phone}', '{email}', '{last_purchase}', '{total_spent}',
];
