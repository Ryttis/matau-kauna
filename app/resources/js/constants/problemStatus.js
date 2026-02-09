export const PROBLEM_STATUS = {
    UNREGISTERED: 'neuzregistruota',
    NEW: 'nauja',
    IN_REVIEW: 'nagrinejama',
    RESOLVED: 'isspresta',
    INACTIVE: 'neaktuali',
}

export const PROBLEM_STATUS_LABELS = {
    [PROBLEM_STATUS.UNREGISTERED]: 'Neužregistruota',
    [PROBLEM_STATUS.NEW]: 'Nauja',
    [PROBLEM_STATUS.IN_REVIEW]: 'Nagrinėjama',
    [PROBLEM_STATUS.RESOLVED]: 'Išspręsta',
    [PROBLEM_STATUS.INACTIVE]: 'Neaktuali',
}

export const PROBLEM_STATUS_OPTIONS = [
    { value: PROBLEM_STATUS.NEW, label: PROBLEM_STATUS_LABELS[PROBLEM_STATUS.NEW] },
    { value: PROBLEM_STATUS.IN_REVIEW, label: PROBLEM_STATUS_LABELS[PROBLEM_STATUS.IN_REVIEW] },
    { value: PROBLEM_STATUS.RESOLVED, label: PROBLEM_STATUS_LABELS[PROBLEM_STATUS.RESOLVED] },
    { value: PROBLEM_STATUS.INACTIVE, label: PROBLEM_STATUS_LABELS[PROBLEM_STATUS.INACTIVE] },
]

export const MARKER_COLOR_BY_STATUS = {
    [PROBLEM_STATUS.NEW]: '#e67e22',
    [PROBLEM_STATUS.IN_REVIEW]: '#f1c40f',
    [PROBLEM_STATUS.RESOLVED]: '#27ae60',
    [PROBLEM_STATUS.UNREGISTERED]: '#95a5a6',
    [PROBLEM_STATUS.INACTIVE]: '#95a5a6',
}

export const STATUS_BADGE_BG = {
    [PROBLEM_STATUS.NEW]: '#e67e22',
    [PROBLEM_STATUS.IN_REVIEW]: '#f1c40f',
    [PROBLEM_STATUS.RESOLVED]: '#2e7d32',
    [PROBLEM_STATUS.UNREGISTERED]: '#95a5a6',
    [PROBLEM_STATUS.INACTIVE]: '#95a5a6',
}

export const STATUS_BADGE_TEXT = {
    [PROBLEM_STATUS.IN_REVIEW]: '#1e1c1c',
}

export function getMarkerColorForStatus(status) {
    return MARKER_COLOR_BY_STATUS[status] || '#95a5a6'
}

export function getStatusBadgeStyle(status) {
    const bg = STATUS_BADGE_BG[status] || '#95a5a6'
    const textColor = STATUS_BADGE_TEXT[status] ?? '#fff'
    return { backgroundColor: bg, color: textColor }
}
