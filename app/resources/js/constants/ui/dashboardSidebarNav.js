import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'

export const SIDEBAR_DASHBOARD_STATUS_ENTRIES = [
    { key: 'all', status: null, label: 'Visos' },
    { key: 'new', status: PROBLEM_STATUS.NEW, label: 'Nauja' },
    { key: 'review', status: PROBLEM_STATUS.IN_REVIEW, label: 'Nagrinėjama' },
    { key: 'returned', status: PROBLEM_STATUS.RETURNED_FOR_REVIEW, label: 'Grąžinta nagrinėjimui' },
    { key: 'inactive', status: PROBLEM_STATUS.INACTIVE, label: 'Neaktuali' },
    { key: 'resolved', status: PROBLEM_STATUS.RESOLVED, label: 'Išnagrinėta' },
]
