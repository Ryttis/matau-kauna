import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
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

    const tryShowFromFlash = () => {
        if (messageOverride.value) {
            return
        }
        const flash = page.props?.flash
        const flashMessage = flash?.success || flash?.status
        if (flashMessage && String(flashMessage).trim()) {
            showSuccessModal.value = true
        }
    }

    const onInertiaSuccess = () => {
        nextTick(tryShowFromFlash)
    }

    onMounted(() => {
        tryShowFromFlash()
        document.addEventListener('inertia:success', onInertiaSuccess)
    })

    onUnmounted(() => {
        document.removeEventListener('inertia:success', onInertiaSuccess)
    })

    const closeSuccessModal = () => {
        showSuccessModal.value = false
        messageOverride.value = ''
    }

    const showWithMessage = (message) => {
        messageOverride.value = message || 'Operacija atlikta sėkmingai.'
        showSuccessModal.value = true
    }

    return { showSuccessModal, successMessage, closeSuccessModal, showWithMessage }
}
