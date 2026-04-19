import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'

export const REVIEW_ENTRY_VISIBILITY = {
    PUBLIC: 'public',
    INTERNAL: 'internal',
}

export const REVIEW_PROGRESS_MODAL_STATUS_TAGS = [
    { key: 'in_review', label: 'Nagrinėjama', value: PROBLEM_STATUS.IN_REVIEW, borderClass: 'add-entry-status--yellow' },
    {
        key: 'returned',
        label: 'Grąžinta nagrinėjimui',
        value: PROBLEM_STATUS.RETURNED_FOR_REVIEW,
        borderClass: 'add-entry-status--violet',
    },
    { key: 'resolved', label: 'Išspręsta', value: PROBLEM_STATUS.RESOLVED, borderClass: 'add-entry-status--green' },
    { key: 'inactive', label: 'Neaktuali', value: PROBLEM_STATUS.INACTIVE, borderClass: 'add-entry-status--gray' },
]

export function reviewModalTagKeyForStatus(status) {
    if (!status) return null
    return REVIEW_PROGRESS_MODAL_STATUS_TAGS.find((t) => t.value === status)?.key ?? null
}

export function buildInactiveReviewPostBody(comment, visibility) {
    return {
        status: PROBLEM_STATUS.INACTIVE,
        comment,
        visibility,
    }
}
