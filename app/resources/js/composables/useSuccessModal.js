import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useSuccessModal() {
    const page = usePage()
    const showSuccessModal = ref(false)
    const messageOverride = ref('')

    const successMessage = computed(
        () =>
            messageOverride.value ||
            page.props.flash?.success ||
            page.props.flash?.status ||
            ''
    )

    watch(
        () => [page.props.flash?.success, page.props.flash?.status],
        ([success, status]) => {
            if (success || status) {
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
