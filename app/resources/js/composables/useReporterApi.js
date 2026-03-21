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

    function postMultipart(url, formData) {
        return axios.post(url, formData, {
            headers: {
                ...getHeaders(),
                'X-Upload-Channel': 'web',
            },
        })
    }

    function postProblemAttachments(problemId, files) {
        const fd = new FormData()
        files.forEach((f) => fd.append('files[]', f))

        return axios.post(route('problems.attachments.store', problemId), fd, {
            headers: {
                ...getHeaders(),
                'Content-Type': 'multipart/form-data',
                'X-Upload-Channel': 'web',
                Accept: 'application/json',
            },
        })
    }

    return { post, postMultipart, postProblemAttachments }
}
