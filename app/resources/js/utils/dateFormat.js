/**
 * Lithuanian date display helpers — YYYY/MM/DD format.
 */

function toDate(input) {
    if (!input) return null
    if (input instanceof Date) return Number.isNaN(input.getTime()) ? null : input
    const d = new Date(input)
    return Number.isNaN(d.getTime()) ? null : d
}

function pad(n) {
    return String(n).padStart(2, '0')
}

export function formatDateLt(input, fallback = '') {
    const d = toDate(input)
    if (!d) return fallback
    return `${d.getFullYear()}/${pad(d.getMonth() + 1)}/${pad(d.getDate())}`
}

export function formatDateTimeLt(input, fallback = '') {
    const d = toDate(input)
    if (!d) return fallback
    return `${d.getFullYear()}/${pad(d.getMonth() + 1)}/${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}
