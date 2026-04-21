function normalizeInternalNumber(s) {
    return String(s || '').replace(/\s/g, '').toUpperCase()
}

export function problemMatchesNumberQuery(problem, rawQuery) {
    const q = normalizeInternalNumber(rawQuery)
    if (!q) return true
    const nr = normalizeInternalNumber(problem?.internal_number)
    return nr.includes(q)
}
