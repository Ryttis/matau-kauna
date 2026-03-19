import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useSuccessModal() {
    const page = usePage()
    const showSuccessModal = ref(false)
    const messageOverride = ref('')
    const lastShownFlashKey = ref('')

    const successMessage = computed(
        () =>
            messageOverride.value ||
            page.props.flash?.success ||
            page.props.flash?.status ||
            ''
    )

    const flashMessageKey = computed(() => {
        const success = page.props.flash?.success || ''
        const status = page.props.flash?.status || ''
        return `${success}|||${status}`
    })

    watch(
        flashMessageKey,
        (key) => {
            if (key && key !== '|||' && key !== lastShownFlashKey.value) {
                lastShownFlashKey.value = key
                messageOverride.value = ''
                showSuccessModal.value = true
            }
        },
        { immediate: true }
    )

    const closeSuccessModal = () => {
        showSuccessModal.value = false
        messageOverride.value = ''
    }

    const showWithMessage = (msg) => {
        messageOverride.value = msg || 'Operacija atlikta sėkmingai.'
        showSuccessModal.value = true
    }

    return { showSuccessModal, successMessage, closeSuccessModal, showWithMessage }
}
