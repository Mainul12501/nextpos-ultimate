import { t } from '../../i18n';

/**
 * Recruit shared vocabulary: every enum value the recruit API stores (stages,
 * candidate sources, interview types/statuses, job types/statuses) mapped to an
 * i18n key + English fallback. Unknown values fall back to humanised
 * snake_case so nothing renders blank.
 */
const LABELS = {
    // application stages
    applied: ['Recruit_Applied', 'Applied'],
    screening: ['Recruit_Screening', 'Screening'],
    shortlisted: ['Recruit_Shortlisted', 'Shortlisted'],
    interview: ['Recruit_Interview', 'Interview'],
    offered: ['Recruit_Offered', 'Offered'],
    hired: ['Hired', 'Hired'],
    rejected: ['Rejected', 'Rejected'],
    // candidate sources
    website: ['Crm_Website', 'Website'],
    referral: ['Recruit_Referral', 'Referral'],
    linkedin: ['LinkedIn', 'LinkedIn'],
    job_board: ['Recruit_Job_Board', 'Job board'],
    agency: ['Recruit_Agency', 'Agency'],
    walk_in: ['Recruit_Walk_In', 'Walk-in'],
    other: ['Other', 'Other'],
    // interview types
    phone: ['Phone', 'Phone'],
    video: ['DocArc_Kind_Video', 'Video'],
    in_person: ['Recruit_In_Person', 'In person'],
    technical: ['Recruit_Technical', 'Technical'],
    panel: ['Recruit_Panel', 'Panel'],
    group: ['Group', 'Group'],
    // interview statuses
    scheduled: ['Scheduled', 'Scheduled'],
    completed: ['Completed', 'Completed'],
    cancelled: ['Cancelled', 'Cancelled'],
    no_show: ['Hosp_No_Show', 'No show'],
    rescheduled: ['Recruit_Rescheduled', 'Rescheduled'],
    // job types
    full_time: ['Full_Time', 'Full Time'],
    part_time: ['Part_Time', 'Part Time'],
    contract: ['Contract', 'Contract'],
    internship: ['Internship', 'Internship'],
    remote: ['Remote', 'Remote'],
    // job statuses
    draft: ['Draft', 'Draft'],
    open: ['Open', 'Open'],
    on_hold: ['On_Hold', 'On Hold'],
    closed: ['Closed', 'Closed'],
};

/** Translated label for any recruit enum value. */
export function formatLabel(v) {
    if (!v) return '-';
    const hit = LABELS[v];
    if (hit) return t(hit[0], hit[1]);
    const s = String(v).replace(/_/g, ' ');
    return s.charAt(0).toUpperCase() + s.slice(1);
}
