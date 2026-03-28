export function parseDateFilterInput(dateString) {
    if (!dateString || !dateString.trim()) {
        return null
    }
    const trimmed = dateString.trim()
    const dayMonthYear = trimmed.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/)
    if (dayMonthYear) {
        return new Date(+dayMonthYear[3], +dayMonthYear[2] - 1, +dayMonthYear[1])
    }
    const parsed = new Date(trimmed)
    return Number.isNaN(parsed.getTime()) ? null : parsed
}
