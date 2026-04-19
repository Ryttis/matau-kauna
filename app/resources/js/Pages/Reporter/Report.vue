<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useReporterApi } from '@/composables/useReporterApi'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import ReporterWizardModal from '@/Components/Reporter/ReporterWizardModal.vue'
import ReporterLocationMap from '@/Components/Reporter/ReporterLocationMap.vue'
import ReporterReviewModal from '@/Components/Reporter/ReporterReviewModal.vue'
import ReporterCancelConfirmModal from '@/Components/Reporter/ReporterCancelConfirmModal.vue'
import { KAUNAS_CENTER } from '@/utils/leafletKaunas'
import { REPORTER_WIZARD_STEPS } from '@/constants/domain/reporterWizardSteps'
import { useFileUploadQueue, defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { messageFromAxiosError } from '@/utils/laravelApiErrors'
import {
    fileUploadPickerLabel,
    formatDetailedFileUploadHint,
} from '@/utils/fileUploadClient'

const props = defineProps({
    typeValues: {
        type: Array,
        default: () => [],
    },
    showReporterWizard: {
        type: Boolean,
        default: false,
    },
    hasAcceptedReportTerms: {
        type: Boolean,
        default: false,
    },
    fileUpload: {
        type: Object,
        default: defaultFileUploadSettings,
    },
})

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
const showReviewModal = ref(false)
const reviewModalMode = ref('terms-first')
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
const isWizardVisible = computed(() => {
    if (wizardClosed.value) return false
    if (!isStep1.value) return false
    if (!props.showReporterWizard) return false

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

const step1Error = ref('')

const ADDRESS_REQUIRED_MSG = 'Pasirinkite problemos vietą žemėlapyje arba įveskite adresą.'

function getStep1ValidationError() {
    if (!problemTypeId.value) return 'Pasirinkite problemos rūšį.'

    const natures = natureOptionsForSelectedType.value
    const natureRequired = natures.length > 0

    if (natureRequired && !problemNatureId.value) return 'Pasirinkite problemos pobūdį.'

    return null
}

function handleNext() {
    if (step.value !== 1) return

    step1Error.value = ''
    const validationError = getStep1ValidationError()

    if (validationError) {
        step1Error.value = validationError
        return
    }

    step.value = 2
    wizardStep.value = 0

    const needsTermsModal = !props.hasAcceptedReportTerms

    if (needsTermsModal) {
        reviewModalMode.value = 'terms-first'
        showReviewModal.value = true
    }
}

function handleBackToStep1() {
    step.value = 1
}

function getAddressError() {
    return (addressStep2.value ?? '').trim() ? null : ADDRESS_REQUIRED_MSG
}

function handlePerziureti() {
    step.value = 1
    wizardHiddenUntilNextStep.value = false
    submitError.value = ''
}

const hasAcceptedTerms = computed(
    () => props.hasAcceptedReportTerms || termsAcceptedThisSession.value
)

function handlePateikti() {
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

function handleReviewContinue() {
    api.post(route('reporter.termsAccepted')).finally(() => {
        termsAcceptedThisSession.value = true
        showReviewModal.value = false
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
            router.visit(`${base}${sep}report_created=1`)
        })
        .catch((err) => {
            submitError.value = getSubmitErrorMessage(err)
        })
        .finally(() => { isSubmitting.value = false })
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
</script>

<template>
    <Head title="Naujas pranešimas" />
    <AuthenticatedLayout>
        <ReporterWizardModal
            :show="isWizardVisible"
            :step="wizardStep"
            :steps="REPORTER_WIZARD_STEPS"
            @close="wizardClose"
            @next="wizardNext"
            @back="wizardBack"
        />
        <ReporterReviewModal
            :show="showReviewModal"
            :mode="reviewModalMode"
            :submitting="isSubmitting"
            @close="showReviewModal = false"
            @confirm="handleReviewConfirm"
            @continue="handleReviewContinue"
        />
        <ReporterCancelConfirmModal
            :show="showCancelModal"
            @close="handleCloseCancelModal"
            @confirm-cancel="handleConfirmCancel"
        />
        <div class="reporter-report-page">
            <div class="reporter-report-card" :class="{ 'reporter-report-card--step2': step === 2 }">
                <header class="reporter-report-header">
                    <h1 class="reporter-report-title">Naujas pranešimas {{ step }}/2</h1>
                    <button
                        type="button"
                        class="reporter-report-close"
                        aria-label="Uždaryti"
                        @click="openCancelModal"
                    >
                        <span class="reporter-report-close-x">×</span>
                    </button>
                </header>

                <div class="reporter-report-body">
                    <template v-if="step === 1">
                    <div class="reporter-report-field">
                        <AppSelect
                            v-model="problemTypeId"
                            label="Problemos rūšis"
                            placeholder="Pasirinkti rūšį"
                            variant="white"
                        >
                            <option
                                v-for="v in typeValues"
                                :key="v.id"
                                :value="v.id"
                            >
                                {{ v.name }}
                            </option>
                        </AppSelect>
                    </div>
                    <div class="reporter-report-field">
                        <AppSelect
                            v-model="problemNatureId"
                            label="Problemos pobūdis"
                            placeholder="Pasirinkti pobūdį"
                            variant="white"
                            :disabled="!problemTypeId || !natureOptionsForSelectedType.length"
                        >
                            <option
                                v-for="v in natureOptionsForSelectedType"
                                :key="v.id"
                                :value="v.id"
                            >
                                {{ v.name }}
                            </option>
                        </AppSelect>
                    </div>
                    <p v-if="step1Error" class="reporter-report-error">{{ step1Error }}</p>

                    <div class="reporter-report-field">
                        <label class="reporter-report-label">Aprašykite problemą</label>
                        <textarea
                            v-model="description"
                            class="reporter-report-textarea"
                            placeholder=""
                            rows="6"
                            :maxlength="descriptionMaxChars"
                        />
                    </div>

                    <div v-if="showFileSection" class="reporter-report-field">
                        <label class="reporter-report-label">{{ fileFieldLabel }}</label>
                        <p class="reporter-report-file-hint">{{ uploadHintText }}</p>
                        <div
                            class="reporter-report-photo-wrap"
                            role="button"
                            tabindex="0"
                            @click="triggerPicker"
                            @keydown.enter="triggerPicker"
                        >
                            <div class="reporter-report-photo-placeholder" />
                            <span class="reporter-report-photo-text">{{ attachButtonText }}</span>
                        </div>
                        <input
                            ref="fileInputRef"
                            type="file"
                            class="reporter-report-file-input"
                            :accept="uploadRules.accept_attr"
                            :multiple="
                                (Number(uploadRules.max_photo_files_per_problem || 0) +
                                    Number(uploadRules.max_video_files_per_problem || 0)) > 1
                            "
                            @change="handleFileChange"
                        />
                        <p v-if="fileHint" class="reporter-report-error reporter-report-file-error">{{ fileHint }}</p>
                        <div v-if="selectedFiles.length" class="reporter-report-photos-grid">
                            <div
                                v-for="(item, idx) in filePreviews"
                                :key="idx + item.file.name"
                                class="reporter-report-photo-item"
                            >
                                <div v-if="item.isPhoto && item.previewUrl" class="reporter-report-photo-preview">
                                    <img :src="item.previewUrl" :alt="item.file.name" class="reporter-report-photo-img" />
                                    <button
                                        type="button"
                                        class="reporter-report-photo-remove"
                                        :aria-label="`Pašalinti ${item.file.name}`"
                                        @click.stop="removeFile(idx)"
                                    >
                                        ×
                                    </button>
                                </div>
                                <div v-else class="reporter-report-file-item">
                                    <span class="reporter-report-file-name">{{ item.file.name }}</span>
                                    <button type="button" class="reporter-report-file-remove" @click.stop="removeFile(idx)">
                                        Šalinti
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="reporter-report-actions">
                        <AppButton
                            text="Toliau"
                            variant="primary"
                            as-button
                            @click="handleNext"
                        />
                    </div>
                    </template>
                    <template v-else>
                        <h2 class="reporter-report-step2-title">Pasirinkite problemos vietą</h2>
                        <ReporterLocationMap
                            :initial-lat="latStep2 ?? KAUNAS_CENTER[0]"
                            :initial-lng="lngStep2 ?? KAUNAS_CENTER[1]"
                            :initial-address="addressStep2"
                            @update:address="addressStep2 = $event"
                            @update:lat="latStep2 = $event"
                            @update:lng="lngStep2 = $event"
                        />
                        <p v-if="submitError" class="reporter-report-error">{{ submitError }}</p>
                        <div class="reporter-report-step2-actions">
                            <AppButton
                                text="Pateikti"
                                variant="primary"
                                as-button
                                :loading="isSubmitting"
                                @click="handlePateikti"
                            />
                            <AppButton
                                text="Peržiūrėti"
                                variant="outline-light"
                                as-button
                                :disabled="isSubmitting"
                                @click="handlePerziureti"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.reporter-report-page {
    min-height: 60vh;
    padding: 24px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.reporter-report-card {
    max-width: 560px;
    margin: 0 auto;
}

.reporter-report-card.reporter-report-card--step2 {
    max-width: 640px;
    background: #f5f5f5;
    border-radius: 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    overflow: visible;
}

.reporter-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e0e0e0;
}

.reporter-report-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.reporter-report-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: none;
    background: transparent;
    color: #666;
    text-decoration: none;
    border-radius: 15px;
    cursor: pointer;
}

.reporter-report-close:hover {
    background: #f0f0f0;
    color: #333;
}

.reporter-report-close-x {
    font-size: 24px;
    line-height: 1;
}

.reporter-report-body {
    padding: 24px;
}

.reporter-report-field {
    margin-bottom: 24px;
}

.reporter-report-field:last-of-type {
    margin-bottom: 32px;
}

.reporter-report-label {
    display: block;
    margin-bottom: 8px;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.reporter-report-textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px 16px;
    border: 1px solid #e0e0e0;
    border-radius: 15px;
    font-size: 16px;
    font-family: inherit;
    color: #333;
    background: #fff;
    box-sizing: border-box;
    resize: vertical;
}

.reporter-report-textarea:focus {
    outline: none;
    border-color: var(--color-primary, #1946A0);
}

.reporter-report-photo-wrap {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    min-height: 0;
    padding: 10px 14px;
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    cursor: pointer;
}

.reporter-report-photo-wrap:hover {
    border-color: var(--color-primary, #1946A0);
    background: #f9fafb;
}

.reporter-report-photo-placeholder {
    width: 44px;
    height: 44px;
    min-width: 44px;
    min-height: 44px;
    flex-shrink: 0;
    background: repeating-conic-gradient(#eee 0% 25%, #fff 0% 50%) 50% / 12px 12px;
    border-radius: 8px;
    border: 1px dashed #ccc;
}

.reporter-report-photo-text {
    font-size: 14px;
    color: #666;
    text-align: left;
    line-height: 1.3;
}

.reporter-report-file-hint {
    font-size: 13px;
    color: #666;
    margin: 0 0 8px;
    line-height: 1.4;
}

.reporter-report-file-error {
    margin-top: 8px;
}

.reporter-report-photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin: 14px 0 0;
}

.reporter-report-photo-item {
    position: relative;
}

.reporter-report-photo-preview {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e0e0e0;
    background: #f5f5f5;
}

.reporter-report-photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.reporter-report-photo-remove {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    border: none;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.reporter-report-photo-remove:hover {
    background: rgba(0, 0, 0, 0.8);
}

.reporter-report-file-list {
    list-style: none;
    margin: 10px 0 0;
    padding: 0;
}

.reporter-report-file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 14px;
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}

.reporter-report-file-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.reporter-report-file-remove {
    flex-shrink: 0;
    border: none;
    background: none;
    color: var(--color-primary);
    cursor: pointer;
    font-size: 14px;
    text-decoration: underline;
}

.reporter-report-file-input {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
}

.reporter-report-actions {
    display: flex;
    justify-content: center;
}

.reporter-report-actions .app-button {
    min-width: 200px;
    border-radius: 15px;
}

.reporter-report-step2-title {
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.reporter-report-error {
    margin: 0 0 12px;
    font-size: 14px;
    color: #c62828;
}

.reporter-report-step2-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 24px;
}

.reporter-report-step2-actions .app-button {
    min-width: 140px;
    border-radius: 15px;
}
</style>
