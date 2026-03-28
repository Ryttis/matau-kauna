import axios from 'axios'
import { inertiaJsonHeaders } from '@/utils/inertiaJsonHeaders'

export function useReporterApi() {
    function post(url, data = {}) {
        return axios.post(url, data, { headers: inertiaJsonHeaders() })
    }

    function postMultipart(url, formData) {
        return axios.post(url, formData, {
            headers: {
                ...inertiaJsonHeaders(),
                'X-Upload-Channel': 'web',
            },
        })
    }

    function postProblemAttachments(problemId, files) {
        const fd = new FormData()
        files.forEach((f) => fd.append('files[]', f))

        return axios.post(route('problems.attachments.store', problemId), fd, {
            headers: {
                ...inertiaJsonHeaders(),
                'Content-Type': 'multipart/form-data',
                'X-Upload-Channel': 'web',
                Accept: 'application/json',
            },
        })
    }

    return { post, postMultipart, postProblemAttachments }
}
