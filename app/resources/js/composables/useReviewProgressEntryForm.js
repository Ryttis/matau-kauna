import { toValue } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { REVIEW_ENTRY_VISIBILITY } from '@/constants/reviewProgressEntry'

export function useReviewProgressEntryForm(problemId, { onSuccess, getFiles, getEntryId } = {}) {
    const form = useForm({
        status: '',
        comment: '',
        visibility: REVIEW_ENTRY_VISIBILITY.INTERNAL,
        files: [],
    })

    function submit(visibility) {
        form.visibility = visibility
        const files = typeof getFiles === 'function' ? getFiles() : []
        form.files = files
        const pid = toValue(problemId)
        const entryId = typeof getEntryId === 'function' ? getEntryId() : null

        const resetTransform = () => form.transform((d) => d)

        const options = {
            preserveScroll: true,
            forceFormData: files.length > 0,
            onSuccess: () => {
                form.reset()
                onSuccess?.()
            },
            onFinish: () => {
                resetTransform()
            },
        }

        if (entryId) {
            const url = route('problems.reviewProgress.update', { problem: pid, entry: entryId })
            if (files.length > 0) {
                form.transform((d) => ({ ...d, _method: 'put' }))
                form.post(url, options)
            } else {
                form.put(url, options)
            }
        } else {
            form.post(route('problems.reviewProgress.store', pid), options)
        }
    }

    return { form, submit }
}
