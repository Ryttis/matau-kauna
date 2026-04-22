function flattenValidationErrors(errors) {
    if (!errors || typeof errors !== 'object') {
        return []
    }
    const messages = []
    for (const fieldKey of Object.keys(errors).sort()) {
        const fieldErrors = errors[fieldKey]
        if (Array.isArray(fieldErrors)) {
            fieldErrors.forEach((fieldError) => {
                const message = fieldError != null ? String(fieldError).trim() : ''
                if (message) {
                    messages.push(message)
                }
            })
        } else if (typeof fieldErrors === 'string' && fieldErrors.trim()) {
            messages.push(fieldErrors.trim())
        }
    }
    return messages
}

function laravelValidationMessages(errors) {
    return flattenValidationErrors(errors).join(' ')
}

export function firstInertiaErrorForPrefix(errors, prefix) {
    if (!errors || typeof errors !== 'object') {
        return ''
    }
    const matchingKeys = Object.keys(errors)
        .filter((key) => key === prefix || key.startsWith(`${prefix}.`))
        .sort()
    for (const key of matchingKeys) {
        const errorValue = errors[key]
        if (Array.isArray(errorValue) && errorValue.length) {
            const firstMessage = String(errorValue[0]).trim()
            if (firstMessage) {
                return firstMessage
            }
        }
        if (typeof errorValue === 'string' && errorValue.trim()) {
            return errorValue.trim()
        }
    }
    return ''
}

const GENERIC_EN_422 = /the given data was invalid/i

const TECHNICAL_UPLOAD_PATTERNS = [
    /mkdir\s*\(/i,
    /permission denied/i,
    /failed to open stream/i,
    /file_put_contents/i,
    /unable to create/i,
    /no such file or directory/i,
    /failed to upload/i,
]

export function humanizeTechnicalUploadError(message, fallback = 'Įkelti nepavyko. Bandykite vėliau arba kreipkitės į administratorių.') {
    if (!message || typeof message !== 'string') {
        return message || ''
    }
    const trimmed = message.trim()
    if (!trimmed) {
        return ''
    }
    if (TECHNICAL_UPLOAD_PATTERNS.some((re) => re.test(trimmed))) {
        return fallback
    }
    return trimmed
}

function looksLikeEnglishServerMessage(message) {
    if (!message || typeof message !== 'string') {
        return true
    }
    const trimmedMessage = message.trim()
    if (!trimmedMessage) {
        return true
    }
    return (
        GENERIC_EN_422.test(trimmedMessage)
        || /^The\s+/i.test(trimmedMessage)
        || /^Unauthorized\.?$/i.test(trimmedMessage)
        || /^Forbidden\.?$/i.test(trimmedMessage)
        || /^Not Found\.?$/i.test(trimmedMessage)
        || /^Server Error/i.test(trimmedMessage)
        || /^Too Many Requests/i.test(trimmedMessage)
        || /^Page Expired/i.test(trimmedMessage)
    )
}

export function messageFromAxiosError(error, fallback) {
    const status = error.response?.status
    const responseData = error.response?.data

    if (!error.response) {
        return 'Nėra ryšio.'
    }

    if (typeof responseData === 'string') {
        return fallback
    }

    if (status === 422 && responseData?.errors) {
        const fieldMessages = humanizeTechnicalUploadError(laravelValidationMessages(responseData.errors))
        if (fieldMessages) {
            return fieldMessages
        }
        if (responseData.message && typeof responseData.message === 'string' && !looksLikeEnglishServerMessage(responseData.message)) {
            return responseData.message
        }
        return fallback
    }

    if (status === 422 && responseData?.message && typeof responseData.message === 'string') {
        if (!looksLikeEnglishServerMessage(responseData.message)) {
            return responseData.message
        }
    }

    if (responseData?.message && typeof responseData.message === 'string' && !looksLikeEnglishServerMessage(responseData.message)) {
        return humanizeTechnicalUploadError(responseData.message)
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
