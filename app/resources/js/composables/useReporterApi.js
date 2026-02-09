import axios from 'axios'

function getHeaders() {
    const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
    const headers = { Accept: 'application/json' }
    if (token) headers['X-XSRF-TOKEN'] = decodeURIComponent(token)
    return headers
}

export function useReporterApi() {
    function post(url, data = {}) {
        return axios.post(url, data, { headers: getHeaders() })
    }

    return { getHeaders, post }
}
