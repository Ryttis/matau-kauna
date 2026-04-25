import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { useReporterApi } from '@/composables/useReporterApi'
import { KAUNAS_CENTER } from '@/utils/leafletKaunas'
import { REPORTER_WIZARD_STEPS } from '@/constants/domain/reporterWizardSteps'
import { useFileUploadQueue, defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { messageFromAxiosError } from '@/utils/laravelApiErrors'
import {
    fileUploadPickerLabel,
    formatDetailedFileUploadHint,
} from '@/utils/fileUploadClient'

export function useReporterReportPage(props) {
    const step = ref(1)
    const problemTypeId = ref('')
    const problemNatureId = ref('')
    const description = ref('')
    const addressStep2 = ref('')
    const latStep2 = ref(null)
    const lngStep2 = ref(null)
    const wizardStep = ref(0)
    const wizardClosed = ref(false)
    const wizardHiddenUntilNextStep = ref(false)
    const showReviewModal = ref(!props.hasAcceptedReportTerms)
    const reviewModalMode = ref(props.hasAcceptedReportTerms ? 'submit' : 'terms-first')
    const termsAcceptedThisSession = ref(false)
    const showCancelModal = ref(false)
    const isSubmitting = ref(false)
    const submitError = ref('')

    const api = useReporterApi()

    const natureOptionsForSelectedType = computed(() => {
        if (!problemTypeId.value) return []
        const type = props.typeValues.find((t) => String(t.id) === String(problemTypeId.value))
        return type?.natures ?? []
    })

    const isStep1 = computed(() => step.value === 1)

    const uploadRules = computed(() => ({ ...defaultFileUploadSettings(), ...props.fileUpload }))

    const {
        selectedFiles,
        fileHint,
        fileInputRef,
        handleFileChange,
        removeFile,
        triggerPicker,
        showFileSection,
    } = useFileUploadQueue(uploadRules, {})

    const fileFieldLabel = computed(() => fileUploadPickerLabel(uploadRules.value))

    const descriptionMaxChars = computed(() => Number(uploadRules.value.description_max_chars) || 65535)

    const uploadHintText = computed(() =>
        formatDetailedFileUploadHint(uploadRules.value, { includeExistingInTotal: false }),
    )

    const attachButtonText = computed(() => {
        const u = uploadRules.value
        if (u.allow_photo && !u.allow_video) {
            return 'Prisegti nuotrauką'
        }
        return 'Prisegti failą'
    })

    const previewUrls = ref(new Map())

    const filePreviews = computed(() => {
        const currentUrls = new Set()
        const result = selectedFiles.value.map((file, idx) => {
            const isPhoto = file.type?.startsWith('image/')
            let previewUrl = null
            if (isPhoto) {
                const key = `${idx}-${file.name}-${file.size}`
                if (!previewUrls.value.has(key)) {
                    previewUrls.value.set(key, URL.createObjectURL(file))
                }
                previewUrl = previewUrls.value.get(key)
                currentUrls.add(key)
            }
            return {
                file,
                previewUrl,
                isPhoto,
                key: isPhoto ? `${idx}-${file.name}-${file.size}` : null,
            }
        })
        previewUrls.value.forEach((url, key) => {
            if (!currentUrls.has(key)) {
                URL.revokeObjectURL(url)
                previewUrls.value.delete(key)
            }
        })
        return result
    })

    onBeforeUnmount(() => {
        previewUrls.value.forEach((url) => {
            URL.revokeObjectURL(url)
        })
        previewUrls.value.clear()
    })

    const hasAcceptedTerms = computed(
        () => props.hasAcceptedReportTerms || termsAcceptedThisSession.value,
    )

    const isWizardVisible = computed(() => {
        if (wizardClosed.value) return false
        if (!isStep1.value) return false
        if (!props.showReporterWizard) return false
        if (!hasAcceptedTerms.value) return false

        return !wizardHiddenUntilNextStep.value
    })

    watch(problemTypeId, () => {
        problemNatureId.value = ''
    })

    function dismissWizard() {
        wizardClosed.value = true
        api.post(route('reporter.wizardDismissed')).catch(() => {})
    }

    function wizardNext() {
        const isLastStep = wizardStep.value >= REPORTER_WIZARD_STEPS.length - 1

        if (!isLastStep) {
            wizardStep.value += 1
            return
        }

        wizardHiddenUntilNextStep.value = true
        api.post(route('reporter.wizardDismissed')).catch(() => {})
    }

    function wizardBack() {
        const hasPreviousStep = wizardStep.value > 0

        if (hasPreviousStep) {
            wizardStep.value -= 1
            return
        }

        dismissWizard()
    }

    function wizardClose() {
        dismissWizard()
    }

    const step1Errors = ref({
        problemTypeId: '',
        problemNatureId: '',
        description: '',
    })

    const ADDRESS_REQUIRED_MSG = 'Pasirinkite problemos vietą žemėlapyje arba įveskite adresą.'

    function getStep1ValidationErrors() {
        const errors = {
            problemTypeId: '',
            problemNatureId: '',
            description: '',
        }

        if (!problemTypeId.value) {
            errors.problemTypeId = 'Pasirinkite problemos rūšį.'
        }

        const natures = natureOptionsForSelectedType.value
        const natureRequired = natures.length > 0

        if (natureRequired && !problemNatureId.value) {
            errors.problemNatureId = 'Pasirinkite problemos pobūdį.'
        }

        if (!(description.value ?? '').trim()) {
            errors.description = 'Įveskite aprašymą.'
        }

        return errors
    }

    function handleNext() {
        if (step.value !== 1) return

        step1Errors.value = {
            problemTypeId: '',
            problemNatureId: '',
            description: '',
        }
        const validationErrors = getStep1ValidationErrors()
        const hasErrors = Object.values(validationErrors).some((message) => !!message)

        if (hasErrors) {
            step1Errors.value = validationErrors
            return
        }

        step.value = 2
        wizardStep.value = 0
    }

    function getAddressError() {
        return (addressStep2.value ?? '').trim() ? null : ADDRESS_REQUIRED_MSG
    }

    function handlePerziureti() {
        step.value = 1
        wizardHiddenUntilNextStep.value = false
        submitError.value = ''
    }

    function handlePateikti() {
        if (isSubmitting.value) {
            return
        }
        submitError.value = ''
        const addressErr = getAddressError()

        if (addressErr) {
            submitError.value = addressErr
            return
        }

        if (hasAcceptedTerms.value) {
            handleReviewConfirm()
            return
        }

        reviewModalMode.value = 'submit'
        showReviewModal.value = true
    }

    const termsAcceptBusy = ref(false)
    const termsSaveError = ref('')

    function handleReviewContinue() {
        if (termsAcceptBusy.value) {
            return
        }
        termsSaveError.value = ''
        termsAcceptBusy.value = true
        api.post(route('reporter.termsAccepted'))
            .then(() => {
                termsAcceptedThisSession.value = true
                showReviewModal.value = false
            })
            .catch((err) => {
                termsSaveError.value = messageFromAxiosError(err, 'Nepavyko išsaugoti sutikimo. Bandykite dar kartą.')
            })
            .finally(() => {
                termsAcceptBusy.value = false
            })
    }

    function getSubmitErrorMessage(err) {
        return messageFromAxiosError(
            err,
            'Nepavyko pateikti.',
        )
    }

    function buildReportFormData() {
        const fd = new FormData()
        fd.append('problem_type_id', String(Number(problemTypeId.value)))
        if (problemNatureId.value) {
            fd.append('problem_nature_id', String(Number(problemNatureId.value)))
        }
        fd.append('description', description.value ?? '')
        fd.append('address', (addressStep2.value ?? '').trim())
        if (latStep2.value != null) fd.append('lat', String(latStep2.value))
        if (lngStep2.value != null) fd.append('lng', String(lngStep2.value))
        selectedFiles.value.forEach((file) => {
            fd.append('files[]', file)
        })
        return fd
    }

    function handleReviewConfirm() {
        if (isSubmitting.value) return

        const addressErr = getAddressError()

        if (addressErr) {
            submitError.value = addressErr
            return
        }

        submitError.value = ''
        isSubmitting.value = true

        api.postMultipart(route('reporter.report.store'), buildReportFormData())
            .then(() => {
                termsAcceptedThisSession.value = true
                showReviewModal.value = false
                const base = route('dashboard')
                const sep = base.includes('?') ? '&' : '?'
                router.visit(`${base}${sep}report_created=1`, {
                    onFinish: () => {
                        isSubmitting.value = false
                    },
                })
            })
            .catch((err) => {
                submitError.value = getSubmitErrorMessage(err)
                isSubmitting.value = false
            })
    }

    function handleCloseCancelModal() {
        showCancelModal.value = false
    }

    function handleConfirmCancel() {
        showCancelModal.value = false
        router.visit(route('dashboard'))
    }

    function openCancelModal() {
        showCancelModal.value = true
    }

    function handleReviewModalClose() {
        showReviewModal.value = false
        termsSaveError.value = ''
    }

    return {
        KAUNAS_CENTER,
        REPORTER_WIZARD_STEPS,
        step,
        problemTypeId,
        problemNatureId,
        description,
        addressStep2,
        latStep2,
        lngStep2,
        wizardStep,
        isWizardVisible,
        showReviewModal,
        reviewModalMode,
        showCancelModal,
        isSubmitting,
        submitError,
        natureOptionsForSelectedType,
        uploadRules,
        fileInputRef,
        handleFileChange,
        removeFile,
        triggerPicker,
        showFileSection,
        fileFieldLabel,
        descriptionMaxChars,
        uploadHintText,
        attachButtonText,
        filePreviews,
        selectedFiles,
        fileHint,
        step1Errors,
        termsAcceptBusy,
        termsSaveError,
        wizardClose,
        wizardNext,
        wizardBack,
        handleNext,
        handlePerziureti,
        handlePateikti,
        hasAcceptedTerms,
        handleReviewContinue,
        handleReviewConfirm,
        handleReviewModalClose,
        handleCloseCancelModal,
        handleConfirmCancel,
        openCancelModal,
    }
}
