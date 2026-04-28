const LT_DATE_TIME_OPTIONS = {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
}

function parseBackendDateTime(value) {
    if (!value) {
        return null
    }
    const d = new Date(String(value).replace(' ', 'T'))
    return Number.isNaN(d.getTime()) ? null : d
}

export function formatLtDateTimeFromBackend(value) {
    const d = parseBackendDateTime(value)
    return d ? new Intl.DateTimeFormat('lt-LT', LT_DATE_TIME_OPTIONS).format(d) : '-'
}

export function formatHideAtAfterDays(resolvedAtStr, days) {
    const d = parseBackendDateTime(resolvedAtStr)
    if (!d || days == null || Number(days) < 1) {
        return '-'
    }
    const end = new Date(d.getTime())
    end.setDate(end.getDate() + Number(days))
    return new Intl.DateTimeFormat('lt-LT', LT_DATE_TIME_OPTIONS).format(end)
}
