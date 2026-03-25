export function institutionEmailList(institution) {
    const institutionEmails = institution?.emails
    if (!Array.isArray(institutionEmails) || !institutionEmails.length) {
        return []
    }
    return [...new Set(institutionEmails.filter(Boolean))]
}
