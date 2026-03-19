import { PROBLEM_STATUS } from '@/constants/problemStatus'

export const REVIEW_ENTRY_VISIBILITY = {
    PUBLIC: 'public',
    INTERNAL: 'internal',
}

export const REVIEW_PROGRESS_MODAL_STATUS_TAGS = [
    { key: 'new', label: 'Nauja', value: PROBLEM_STATUS.NEW, borderClass: 'add-entry-status--orange' },
    { key: 'in_review', label: 'Nagrinėjama', value: PROBLEM_STATUS.IN_REVIEW, borderClass: 'add-entry-status--yellow' },
    {
        key: 'returned',
        label: 'Grąžinta nagrinėjimui',
        value: PROBLEM_STATUS.RETURNED_FOR_REVIEW,
        borderClass: 'add-entry-status--blue',
    },
    { key: 'resolved', label: 'Išnagrinėta', value: PROBLEM_STATUS.RESOLVED, borderClass: 'add-entry-status--green' },
]

export function reviewModalTagKeyForStatus(status) {
    if (!status) return null
    return REVIEW_PROGRESS_MODAL_STATUS_TAGS.find((t) => t.value === status)?.key ?? null
}
