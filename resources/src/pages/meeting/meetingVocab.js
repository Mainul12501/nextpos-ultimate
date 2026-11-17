import { t } from '../../i18n';

/**
 * Meeting shared vocabulary: the enum values the meeting API stores (meeting
 * type/status/platform, invitation + attendance status, note type/status)
 * mapped to their i18n keys. Unknown values fall back to humanised snake_case.
 */
const KEYS = {
    // meeting status
    scheduled: 'Scheduled',
    ongoing: 'Ongoing',
    completed: 'Completed',
    cancelled: 'Cancelled',
    // meeting type
    physical: 'Physical',
    online: 'Online',
    // platform
    zoom: 'Zoom',
    google_meet: 'Google_Meet',
    teams: 'Teams',
    other: 'Other',
    // attendance
    pending: 'Pending',
    present: 'Present',
    absent: 'Absent',
    late: 'Late',
    // invitation
    invited: 'Invited',
    accepted: 'Accepted',
    declined: 'Declined',
    tentative: 'Tentative',
    // notes
    note: 'Note',
    decision: 'Decision',
    action_item: 'Action_Item',
    open: 'Open',
    in_progress: 'In_Progress',
    done: 'Done',
};

function humanize(v) {
    return String(v).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

/** Translated label for any meeting enum value. */
export function formatLabel(v) {
    if (!v) return '-';
    const key = KEYS[v];
    return key ? t(key, humanize(v)) : humanize(v);
}
