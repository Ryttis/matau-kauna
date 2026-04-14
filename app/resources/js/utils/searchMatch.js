export function normalizeSearchText(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
}

export function textMatchesQuery(haystack, needleQuery) {
    const q = normalizeSearchText(needleQuery)
    if (!q) return true
    return normalizeSearchText(haystack).includes(q)
}
