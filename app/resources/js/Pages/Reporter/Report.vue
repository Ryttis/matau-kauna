<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useReporterApi } from '@/composables/useReporterApi'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import ReporterWizardModal from '@/Components/Reporter/ReporterWizardModal.vue'
import ReporterLocationMap from '@/Components/Reporter/ReporterLocationMap.vue'
import ReporterReviewModal from '@/Components/Reporter/ReporterReviewModal.vue'
import ReporterSuccessModal from '@/Components/Reporter/ReporterSuccessModal.vue'
import ReporterCancelConfirmModal from '@/Components/Reporter/ReporterCancelConfirmModal.vue'
import { KAUNAS_CENTER } from '@/composables/leafletKaunas'
import { REPORTER_WIZARD_STEPS } from '@/constants/reporterWizardSteps'

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
})

const step = ref(1)
const problemTypeId = ref('')
const problemNatureId = ref('')
const description = ref('')
const fileInput = ref(null)
const addressStep2 = ref('')
const latStep2 = ref(null)
const lngStep2 = ref(null)
const wizardStep = ref(0)
const wizardClosed = ref(false)
const wizardHiddenUntilNextStep = ref(false)
const showReviewModal = ref(false)
const reviewModalMode = ref('terms-first')
const termsAcceptedThisSession = ref(false)
const showSuccessModal = ref(false)
const showCancelModal = ref(false)
const createdProblemId = ref(null)
const isSubmitting = ref(false)
const submitError = ref('')

const api = useReporterApi()

const natureOptionsForSelectedType = computed(() => {
    if (!problemTypeId.value) return []
    const type = props.typeValues.find((t) => String(t.id) === String(problemTypeId.value))
    return type?.natures ?? []
})

watch(problemTypeId, () => {
    problemNatureId.value = ''
})

const showWizard = computed(() => {
    if (wizardClosed.value || step.value !== 1) return false
    return props.showReporterWizard && !wizardHiddenUntilNextStep.value
})

function dismissWizard() {
    wizardClosed.value = true
    api.post(route('reporter.wizardDismissed')).catch(() => {})
}

function wizardNext() {
    if (wizardStep.value < REPORTER_WIZARD_STEPS.length - 1) {
        wizardStep.value += 1
    } else {
        wizardHiddenUntilNextStep.value = true
    }
}

function wizardBack() {
    if (wizardStep.value > 0) {
        wizardStep.value -= 1
    } else {
        dismissWizard()
    }
}

const handleFileClick = () => {
    fileInput.value?.click()
}

const handleFileChange = (e) => {
    const file = e.target.files?.[0]
    if (file) return
}

const step1Error = ref('')

function handleNext() {
    if (step.value === 1) {
        step1Error.value = ''
        if (!problemTypeId.value) {
            step1Error.value = 'Pasirinkite problemos rūšį.'
            return
        }
        const natures = natureOptionsForSelectedType.value
        if (natures.length > 0 && !problemNatureId.value) {
            step1Error.value = 'Pasirinkite problemos pobūdį.'
            return
        }
        step.value = 2
        wizardStep.value = 0
        if (!props.hasAcceptedReportTerms) {
            reviewModalMode.value = 'terms-first'
            showReviewModal.value = true
        }
    }
}

function handleBackToStep1() {
    step.value = 1
}

const ADDRESS_REQUIRED_MSG = 'Pasirinkite problemos vietą žemėlapyje arba įveskite adresą.'

function getAddressError() {
    return (addressStep2.value ?? '').trim() ? null : ADDRESS_REQUIRED_MSG
}

function handlePerziureti() {
    step.value = 1
    wizardHiddenUntilNextStep.value = false
    submitError.value = ''
}

function handlePateikti() {
    submitError.value = ''
    const err = getAddressError()
    if (err) {
        submitError.value = err
        return
    }
    if (props.hasAcceptedReportTerms || termsAcceptedThisSession.value) {
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

function handleReviewConfirm() {
    if (isSubmitting.value) return
    const err = getAddressError()
    if (err) {
        submitError.value = err
        return
    }
    submitError.value = ''
    isSubmitting.value = true
    const payload = {
        problem_type_id: problemTypeId.value ? Number(problemTypeId.value) : null,
        problem_nature_id: problemNatureId.value ? Number(problemNatureId.value) : null,
        description: description.value,
        address: (addressStep2.value ?? '').trim(),
        lat: latStep2.value ?? null,
        lng: lngStep2.value ?? null,
    }
    api.post(route('reporter.report.store'), payload)
        .then((res) => {
            termsAcceptedThisSession.value = true
            showReviewModal.value = false
            createdProblemId.value = res.data?.problem_id ?? null
            showSuccessModal.value = true
        })
        .catch((err) => {
            const data = err.response?.data
            if (data?.errors) {
                const first = Object.values(data.errors).flat()
                submitError.value = first[0] || 'Nepavyko pateikti.'
            } else {
                submitError.value = data?.message || 'Nepavyko pateikti. Bandykite dar kartą.'
            }
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
            :show="showWizard"
            :step="wizardStep"
            :steps="REPORTER_WIZARD_STEPS"
            @close="dismissWizard"
            @next="wizardNext"
            @back="wizardBack"
        />
        <ReporterReviewModal
            :show="showReviewModal"
            :mode="reviewModalMode"
            @close="showReviewModal = false"
            @confirm="handleReviewConfirm"
            @continue="handleReviewContinue"
        />
        <ReporterSuccessModal
            :show="showSuccessModal"
            :problem-id="createdProblemId"
            @close="showSuccessModal = false"
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
                            <option value="">Pasirinkti rūšį</option>
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
                            <option value="">Pasirinkti pobūdį</option>
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
                        />
                    </div>

                    <div class="reporter-report-field">
                        <label class="reporter-report-label">Pridėkite nuotrauką</label>
                        <div
                            class="reporter-report-photo-wrap"
                            role="button"
                            tabindex="0"
                            @click="handleFileClick"
                            @keydown.enter="handleFileClick"
                        >
                            <div class="reporter-report-photo-placeholder" />
                            <span class="reporter-report-photo-text">Prisegti failą</span>
                        </div>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="reporter-report-file-input"
                            @change="handleFileChange"
                        />
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
                                @click="handlePateikti"
                            />
                            <AppButton
                                text="Peržiūrėti"
                                variant="outline-light"
                                as-button
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
    font-family: 'Open Sans', sans-serif;
}

.reporter-report-card {
    max-width: 560px;
    margin: 0 auto;
}

.reporter-report-card.reporter-report-card--step2 {
    max-width: 640px;
    background: #f5f5f5;
    border-radius: 8px;
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
    border-radius: 4px;
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
    border-radius: 5px;
    font-size: 16px;
    font-family: inherit;
    color: #333;
    background: #fff;
    box-sizing: border-box;
    resize: vertical;
}

.reporter-report-textarea:focus {
    outline: none;
    border-color: #1946a0;
}

.reporter-report-photo-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 140px;
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    cursor: pointer;
}

.reporter-report-photo-wrap:hover {
    border-color: #1946a0;
    background: #f9fafb;
}

.reporter-report-photo-placeholder {
    width: 100%;
    height: 100px;
    min-height: 100px;
    background: repeating-conic-gradient(#eee 0% 25%, #fff 0% 50%) 50% / 20px 20px;
    border-radius: 4px;
}

.reporter-report-photo-text {
    font-size: 16px;
    color: #666;
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
}
</style>
