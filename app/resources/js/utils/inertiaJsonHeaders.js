export function inertiaJsonHeaders() {
    const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
    const headers = { Accept: 'application/json' }
    if (token) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(token)
    }
    return headers
}
