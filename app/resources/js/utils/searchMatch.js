import { foldLithuanianForSearch } from '@/utils/foldLithuanianForSearch'

export function normalizeSearchText(value) {
    return foldLithuanianForSearch(String(value ?? '').trim())
}

export function textMatchesQuery(haystack, needleQuery) {
    const q = normalizeSearchText(needleQuery)
    if (!q) return true
    return normalizeSearchText(haystack).includes(q)
}
