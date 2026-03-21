function flattenValidationErrors(errors) {
    if (!errors || typeof errors !== 'object') {
        return []
    }
    const out = []
    for (const key of Object.keys(errors).sort()) {
        const v = errors[key]
        if (Array.isArray(v)) {
            v.forEach((x) => {
                const s = x != null ? String(x).trim() : ''
                if (s) {
                    out.push(s)
                }
            })
        } else if (typeof v === 'string' && v.trim()) {
            out.push(v.trim())
        }
    }
    return out
}

export function laravelValidationMessages(errors) {
    return flattenValidationErrors(errors).join(' ')
}

export function firstInertiaErrorForPrefix(errors, prefix) {
    if (!errors || typeof errors !== 'object') {
        return ''
    }
    const keys = Object.keys(errors)
        .filter((k) => k === prefix || k.startsWith(`${prefix}.`))
        .sort()
    for (const key of keys) {
        const v = errors[key]
        if (Array.isArray(v) && v.length) {
            const s = String(v[0]).trim()
            if (s) {
                return s
            }
        }
        if (typeof v === 'string' && v.trim()) {
            return v.trim()
        }
    }
    return ''
}

const GENERIC_EN_422 = /the given data was invalid/i

function looksLikeEnglishServerMessage(s) {
    if (!s || typeof s !== 'string') {
        return true
    }
    const t = s.trim()
    if (!t) {
        return true
    }
    return (
        GENERIC_EN_422.test(t)
        || /^The\s+/i.test(t)
        || /^Unauthorized\.?$/i.test(t)
        || /^Forbidden\.?$/i.test(t)
        || /^Not Found\.?$/i.test(t)
        || /^Server Error/i.test(t)
        || /^Too Many Requests/i.test(t)
        || /^Page Expired/i.test(t)
    )
}

export function messageFromAxiosError(err, fallback) {
    const status = err.response?.status
    const data = err.response?.data

    if (!err.response) {
        return 'Nėra ryšio.'
    }

    if (typeof data === 'string') {
        return fallback
    }

    if (status === 422 && data?.errors) {
        const fromFields = laravelValidationMessages(data.errors)
        if (fromFields) {
            return fromFields
        }
        if (data.message && typeof data.message === 'string' && !looksLikeEnglishServerMessage(data.message)) {
            return data.message
        }
        return fallback
    }

    if (status === 422 && data?.message && typeof data.message === 'string') {
        if (!looksLikeEnglishServerMessage(data.message)) {
            return data.message
        }
    }

    if (data?.message && typeof data.message === 'string' && !looksLikeEnglishServerMessage(data.message)) {
        return data.message
    }

    if (status === 413) {
        return 'Failas per didelis.'
    }

    if (status === 403) {
        return 'Nėra leidimo.'
    }

    if (status === 401) {
        return 'Prisijunkite iš naujo.'
    }

    if (status === 419) {
        return 'Sesija pasibaigė. Atnaujinkite puslapį ir bandykite dar kartą.'
    }

    if (status === 500 || status === 502 || status === 503) {
        return 'Serverio klaida. Bandykite vėliau.'
    }

    return fallback
}
