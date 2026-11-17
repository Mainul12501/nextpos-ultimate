import {
    FileImageOutlined, FilePdfOutlined, FileWordOutlined, FileExcelOutlined,
    FilePptOutlined, FileZipOutlined, VideoCameraOutlined, CustomerServiceOutlined,
    FileTextOutlined, FileOutlined,
} from '@ant-design/icons-vue';
import { t, i18n } from '../../i18n';

/**
 * One place deciding how a file "reads" in the archive: its icon, its accent
 * colour and its human label. The `kind` string is computed server-side
 * (Document::kindFor) so the filter dropdown, the cards, the table and the
 * preview pane can never disagree about what a file is.
 *
 * Colours are picked to stay legible on both the light and dark antd surfaces —
 * mid-tone hues, never near-white or near-black.
 *
 * `key` is the i18n key for the label; kindOf() / kindOptions() hand back the
 * translated label, so call them at render time or inside a computed().
 */
export const DOCUMENT_KINDS = {
    image: { key: 'DocArc_Kind_Images', label: 'Images', icon: FileImageOutlined, color: '#0891b2' },
    pdf: { key: 'PDF', label: 'PDF', icon: FilePdfOutlined, color: '#dc2626' },
    word: { key: 'Documents', label: 'Documents', icon: FileWordOutlined, color: '#2563eb' },
    excel: { key: 'DocArc_Kind_Spreadsheets', label: 'Spreadsheets', icon: FileExcelOutlined, color: '#16a34a' },
    powerpoint: { key: 'DocArc_Kind_Presentations', label: 'Presentations', icon: FilePptOutlined, color: '#ea580c' },
    archive: { key: 'DocArc_Kind_Archives', label: 'Archives', icon: FileZipOutlined, color: '#b45309' },
    video: { key: 'DocArc_Kind_Video', label: 'Video', icon: VideoCameraOutlined, color: '#db2777' },
    audio: { key: 'DocArc_Kind_Audio', label: 'Audio', icon: CustomerServiceOutlined, color: '#7c3aed' },
    text: { key: 'Text', label: 'Text', icon: FileTextOutlined, color: '#64748b' },
    other: { key: 'Other', label: 'Other', icon: FileOutlined, color: '#6b7280' },
};

export function kindOf(record) {
    const k = DOCUMENT_KINDS[record?.kind] || DOCUMENT_KINDS.other;
    return { ...k, label: t(k.key, k.label) };
}

/** Options for the type filter, in the order the tiles read best (call inside a computed). */
export function kindOptions() {
    return Object.entries(DOCUMENT_KINDS)
        .map(([value, k]) => ({ value, label: t(k.key, k.label) }));
}

/** Parameterised translation with an English fallback while the key is missing. */
export function tp(key, params, fallback) {
    const out = i18n.global.t(key, params);
    return out === key ? fallback : out;
}

/**
 * "3 days ago" — enough precision for a file list, no dayjs plugin needed.
 * Worded by Intl.RelativeTimeFormat in the active UI language.
 */
export function timeAgo(iso) {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return '';

    const seconds = Math.round((Date.now() - then) / 1000);
    if (seconds < 60) return t('just_now', 'just now');

    // Already in minutes; each step promotes to the next unit once it fits.
    const steps = [
        [60, 'hour'], [24, 'day'], [7, 'week'], [4.35, 'month'], [12, 'year'],
    ];
    let value = seconds / 60;
    let unit = 'minute';
    for (const [size, next] of steps) {
        if (Math.abs(value) < size) break;
        value /= size;
        unit = next;
    }
    const rounded = Math.round(value);
    try {
        const locale = i18n.global.locale.value || 'en';
        return new Intl.RelativeTimeFormat(locale, { numeric: 'always' }).format(-rounded, unit);
    } catch (e) {
        return `${rounded} ${unit}${rounded === 1 ? '' : 's'} ago`;
    }
}
