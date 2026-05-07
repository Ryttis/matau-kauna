<script setup>
import { ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import ReporterWizardModal from '@/Components/Reporter/ReporterWizardModal.vue'
import ReporterLocationMap from '@/Components/Reporter/ReporterLocationMap.vue'
import ReporterReviewModal from '@/Components/Reporter/ReporterReviewModal.vue'
import ReporterCancelConfirmModal from '@/Components/Reporter/ReporterCancelConfirmModal.vue'
import AttachmentMediaLightbox from '@/Components/Problems/AttachmentMediaLightbox.vue'
import { defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { useReporterReportPage } from '@/composables/useReporterReportPage'
import '@/Components/Reporter/reporter-report-page.css'

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

const {
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
    hasAcceptedTerms,
    wizardClose,
    wizardNext,
    wizardBack,
    handleNext,
    handlePerziureti,
    handlePateikti,
    handleReviewContinue,
    handleReviewConfirm,
    handleReviewModalClose,
    handleCloseCancelModal,
    handleConfirmCancel,
    openCancelModal,
} = useReporterReportPage(props)

const reporterFilePreviewLightbox = ref(null)

function openReporterFilePreview(item) {
    if (!item?.previewUrl || item.kind === 'other') return
    reporterFilePreviewLightbox.value = {
        kind: item.kind === 'photo' ? 'photo' : 'video',
        url: item.previewUrl,
    }
}

function closeReporterFilePreview() {
    reporterFilePreviewLightbox.value = null
}

function removeReporterFile(idx) {
    closeReporterFilePreview()
    removeFile(idx)
}

watch(step, (s) => {
    if (s !== 1) {
        closeReporterFilePreview()
    }
})
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
            :submitting="isSubmitting || termsAcceptBusy"
            :save-error="termsSaveError"
            :dismissible="reviewModalMode !== 'terms-first' || hasAcceptedTerms"
            @close="handleReviewModalClose"
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
                    <p class="reporter-report-desc">
                        Užpildykite informaciją apie problemą ir pateikite naują pranešimą.
                    </p>
                    <template v-if="step === 1">
                        <div class="reporter-report-field">
                            <AppSelect
                                v-model="problemTypeId"
                                label="Problemos kategorija"
                                placeholder="Pasirinkti kategoriją"
                                variant="white"
                                required
                            >
                                <option
                                    v-for="v in typeValues"
                                    :key="v.id"
                                    :value="v.id"
                                >
                                    {{ v.name }}
                                </option>
                            </AppSelect>
                            <p v-if="step1Errors.problemTypeId" class="reporter-report-error">
                                {{ step1Errors.problemTypeId }}
                            </p>
                        </div>
                        <div class="reporter-report-field">
                            <AppSelect
                                v-model="problemNatureId"
                                label="Problemos pobūdis"
                                placeholder="Pasirinkti pobūdį"
                                variant="white"
                                :disabled="!problemTypeId || !natureOptionsForSelectedType.length"
                                required
                            >
                                <option
                                    v-for="v in natureOptionsForSelectedType"
                                    :key="v.id"
                                    :value="v.id"
                                >
                                    {{ v.name }}
                                </option>
                            </AppSelect>
                            <p v-if="step1Errors.problemNatureId" class="reporter-report-error">
                                {{ step1Errors.problemNatureId }}
                            </p>
                        </div>

                        <div class="reporter-report-field">
                            <label class="reporter-report-label">
                                Aprašykite problemą<span class="reporter-report-required"> *</span>
                            </label>
                            <textarea
                                v-model="description"
                                class="reporter-report-textarea"
                                placeholder=""
                                rows="6"
                                :maxlength="descriptionMaxChars"
                            />
                            <p v-if="step1Errors.description" class="reporter-report-error">
                                {{ step1Errors.description }}
                            </p>
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
                                    :key="item.key"
                                    class="reporter-report-photo-item"
                                >
                                    <div v-if="item.previewUrl" class="reporter-report-photo-preview">
                                        <div
                                            class="reporter-report-thumb-hit"
                                            role="button"
                                            tabindex="0"
                                            :aria-label="`Peržiūrėti ${item.file.name}`"
                                            @click="openReporterFilePreview(item)"
                                            @keydown.enter.prevent="openReporterFilePreview(item)"
                                            @keydown.space.prevent="openReporterFilePreview(item)"
                                        >
                                            <img
                                                v-if="item.kind === 'photo'"
                                                :src="item.previewUrl"
                                                :alt="item.file.name"
                                                class="reporter-report-photo-img"
                                            />
                                            <video
                                                v-else
                                                class="reporter-report-video-thumb"
                                                muted
                                                playsinline
                                                preload="metadata"
                                                :src="item.previewUrl"
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            class="reporter-report-photo-remove"
                                            :aria-label="`Pašalinti ${item.file.name}`"
                                            @click.stop="removeReporterFile(idx)"
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <div v-else class="reporter-report-photo-preview reporter-report-photo-preview--fallback">
                                        <span class="reporter-report-fallback-name" :title="item.file.name">{{
                                            item.file.name
                                        }}</span>
                                        <button
                                            type="button"
                                            class="reporter-report-photo-remove"
                                            :aria-label="`Pašalinti ${item.file.name}`"
                                            @click.stop="removeReporterFile(idx)"
                                        >
                                            ×
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
                        <h2 class="reporter-report-step2-title">
                            Pasirinkite problemos vietą<span class="reporter-report-required"> *</span>
                        </h2>
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

        <AttachmentMediaLightbox
            :attachment="reporterFilePreviewLightbox"
            :z-index="5600"
            @close="closeReporterFilePreview"
        />
    </AuthenticatedLayout>
</template>
