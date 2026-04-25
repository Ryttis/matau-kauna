export const PHONE_INTL_COUNTRIES = [
    { iso: 'LT', dial: '370', name: 'Lietuva', natMin: 8, natMax: 8 },
    { iso: 'LV', dial: '371', name: 'Latvija', natMin: 8, natMax: 8 },
    { iso: 'EE', dial: '372', name: 'Estija', natMin: 7, natMax: 8 },
    { iso: 'PL', dial: '48', name: 'Lenkija', natMin: 9, natMax: 9 },
    { iso: 'DE', dial: '49', name: 'Vokietija', natMin: 10, natMax: 11 },
    { iso: 'GB', dial: '44', name: 'Jungtinė Karalystė', natMin: 10, natMax: 10 },
    { iso: 'IE', dial: '353', name: 'Airija', natMin: 7, natMax: 9 },
    { iso: 'NL', dial: '31', name: 'Nyderlandai', natMin: 9, natMax: 9 },
    { iso: 'FR', dial: '33', name: 'Prancūzija', natMin: 9, natMax: 9 },
    { iso: 'ES', dial: '34', name: 'Ispanija', natMin: 9, natMax: 9 },
]

export const PHONE_INTL_BY_DIAL_DESC = [...PHONE_INTL_COUNTRIES].sort(
    (a, b) => b.dial.length - a.dial.length,
)

export function digitsOnly(str) {
    return (str || '').replace(/\D/g, '')
}

export function isValidLithuanianPhoneDigits(d) {
    if (!d) return false
    if (/^370\d{8}$/.test(d)) return true
    if (/^0[1-9]\d{7,9}$/.test(d)) return true
    if (/^8\d{8}$/.test(d)) return true
    return /^6\d{7}$/.test(d)
}

export function isValidLithuanianPhoneString(value) {
    return isValidLithuanianPhoneDigits(digitsOnly(value))
}

export function internationalDigitsValid(d) {
    for (const c of PHONE_INTL_BY_DIAL_DESC) {
        if (!d.startsWith(c.dial)) continue
        const natLen = d.length - c.dial.length
        if (natLen >= c.natMin && natLen <= c.natMax) return true
    }
    return false
}

export function isValidOptionalPhone(value) {
    const trimmed = (value || '').trim()
    if (!trimmed) return true
    if (!/^[0-9+\s-]+$/.test(trimmed)) return false
    const d = digitsOnly(trimmed)
    if (isValidLithuanianPhoneDigits(d)) return true
    return internationalDigitsValid(d)
}

export function parsePhoneForInternationalInput(value) {
    const raw = (value || '').trim()
    if (!raw) {
        return { iso: 'LT', dial: '370', national: '' }
    }
    const d = digitsOnly(raw)
    for (const c of PHONE_INTL_BY_DIAL_DESC) {
        if (d.startsWith(c.dial)) {
            return { iso: c.iso, dial: c.dial, national: d.slice(c.dial.length) }
        }
    }
    if (/^370\d{8}$/.test(d)) {
        return { iso: 'LT', dial: '370', national: d.slice(3) }
    }
    if (/^8\d{8}$/.test(d)) {
        return { iso: 'LT', dial: '370', national: d.slice(1) }
    }
    if (/^0[1-9]\d{7,9}$/.test(d)) {
        return { iso: 'LT', dial: '370', national: d.slice(1) }
    }
    if (/^6\d{7}$/.test(d)) {
        return { iso: 'LT', dial: '370', national: d }
    }
    return { iso: 'LT', dial: '370', national: '' }
}

export function buildE164(dial, nationalDigits) {
    const n = digitsOnly(nationalDigits)
    if (!n) return ''
    return `+${dial}${n}`
}

export function canonicalPhoneDigits(value) {
    const raw = (value || '').trim()
    if (!raw) return ''
    const d = digitsOnly(raw)
    if (!d) return ''
    if (isValidLithuanianPhoneDigits(d)) {
        if (/^370\d{8}$/.test(d)) return d
        if (/^8\d{8}$/.test(d)) return `370${d.slice(1)}`
        if (/^0[1-9]\d{7,9}$/.test(d)) return `370${d.slice(1)}`
        if (/^6\d{7}$/.test(d)) return `370${d}`
    }
    return d
}
