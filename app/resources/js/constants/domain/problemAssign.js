import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'

export const ASSIGN_PAGE_STATUSES = [
    PROBLEM_STATUS.NEW,
    PROBLEM_STATUS.IN_REVIEW,
    PROBLEM_STATUS.RETURNED_FOR_REVIEW,
]

export function normalizeStatusForAssignPage(status) {
    return ASSIGN_PAGE_STATUSES.includes(status) ? status : PROBLEM_STATUS.NEW
}
