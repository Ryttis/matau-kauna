export const DEFAULT_DISPLAY_MAX_LENGTH = 200

export function truncateDisplay(text, maxLength = DEFAULT_DISPLAY_MAX_LENGTH) {
    const s = String(text ?? '').trim()
    if (!s) return ''
    if (s.length <= maxLength) return s
    return `${s.slice(0, maxLength)}…`
}
