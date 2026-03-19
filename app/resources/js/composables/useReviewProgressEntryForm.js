import { toValue } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { REVIEW_ENTRY_VISIBILITY } from '@/constants/reviewProgressEntry'

export function useReviewProgressEntryForm(problemId, { onSuccess } = {}) {
    const form = useForm({
        status: '',
        comment: '',
        visibility: REVIEW_ENTRY_VISIBILITY.INTERNAL,
    })

    function submit(visibility) {
        form.visibility = visibility
        form.post(route('problems.reviewProgress.store', toValue(problemId)), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset()
                onSuccess?.()
            },
        })
    }

    return { form, submit }
}
