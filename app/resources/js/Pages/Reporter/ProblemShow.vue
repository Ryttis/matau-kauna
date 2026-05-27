<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { formatDateLt } from '@/utils/dateFormat'
import AppInput from '@/Components/App/AppInput.vue'
import { ICON_LEFT_ARROW } from '@/constants/ui/icons'
import ProblemDetailBlocks from '@/Components/Problems/ProblemDetailBlocks.vue'
import ProblemAttachmentsPanel from '@/Components/Problems/ProblemAttachmentsPanel.vue'
import ProblemMergeLinks from '@/Components/Problems/ProblemMergeLinks.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { useFileUploadQueue, defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { useReporterApi } from '@/composables/useReporterApi'
import { messageFromAxiosError, humanizeTechnicalUploadError } from '@/utils/laravelApiErrors'
import {
    attachmentsToUploadBaseline,
    formatShortFileUploadLimitsLine,
} from '@/utils/fileUploadClient'
import { getReporterStatusKey, getReporterStatusLabel } from '@/constants/domain/problemStatus'

const props = defineProps({
    problem: Object,
    mergeTarget: { type: Object, default: null },
    mergedChildren: { type: Array, default: () => [] },
    mergedSiblings: { type: Array, default: () => [] },
    reviewProgressProblemId: { type: Number, default: null },
    backFrom: { type: String, default: '' },
    backOnlyMine: { type: Boolean, default: false },
    fileUpload: {
        type: Object,
        default: () => ({}),
    },
    canUploadAttachments: { type: Boolean, default: false },
    canWatch: { type: Boolean, default: false },
    isWatched: { type: Boolean, default: false },
})

const isProblemWatched = ref(!!props.isWatched)
const watchToggleBusy = ref(false)

async function toggleProblemWatch() {
    if (watchToggleBusy.value || !props.canWatch || !props.problem?.id) return
    watchToggleBusy.value = true
    try {
        const { data } = await axios.post(route('problems.watch.toggle', props.problem.id))
        isProblemWatched.value = !!data.watched
    } finally {
        watchToggleBusy.value = false
    }
}

const formattedDate = computed(() => formatDateLt(props.problem?.created_at))

const problemIdForReview = computed(() =>
    props.reviewProgressProblemId ?? props.problem?.id
)

const backUrl = computed(() => {
    if (props.backFrom === 'sarasas') return route('reporter.sarasas')
    const base = route('dashboard')
    return props.backOnlyMine ? `${base}?only_mine=1` : base
})

function goToReviewProgress() {
    const id = problemIdForReview.value
    if (id) router.visit(route('problems.reviewProgress', id))
}

const api = useReporterApi()

const uploadRules = computed(() => ({
    ...defaultFileUploadSettings(),
    ...props.fileUpload,
}))

const appendBaseline = computed(() =>
    attachmentsToUploadBaseline(props.problem?.attachments ?? []),
)

const {
    selectedFiles,
    fileHint,
    fileInputRef,
    handleFileChange,
    clearQueue,
    triggerPicker,
    showFileSection,
} = useFileUploadQueue(uploadRules, { appendBaseline })

const isUploading = ref(false)

const uploadLimitsHint = computed(() =>
    formatShortFileUploadLimitsLine(uploadRules.value),
)

const reporterShowsAttachmentsColumn = computed(
    () =>
        showFileSection.value ||
        (props.problem?.attachments?.length ?? 0) > 0,
)

async function onAttachInputChange(e) {
    if (!props.canUploadAttachments || isUploading.value) {
        return
    }
    handleFileChange(e)
    if (!selectedFiles.value.length) return

    isUploading.value = true
    fileHint.value = ''

    try {
        await api.postProblemAttachments(props.problem.id, selectedFiles.value)
        clearQueue()
        router.reload({ preserveScroll: true })
    } catch (err) {
        fileHint.value = humanizeTechnicalUploadError(
            messageFromAxiosError(err, 'Įkelti nepavyko.'),
        )
    } finally {
        isUploading.value = false
    }
}
</script>

<template>
    <Head :title="`Problemos peržiūra – ${problem?.internal_number}`" />
    <AuthenticatedLayout>
        <div class="reporter-problem-page">
            <div class="reporter-problem-detail">
                <div class="reporter-problem-header">
                    <Link :href="backUrl" class="reporter-problem-back" aria-label="Atgal">
                        <img :src="ICON_LEFT_ARROW" alt="" class="reporter-problem-back-icon" />
                    </Link>
                    <div class="header-info">
                        <span class="header-label">Nr</span>
                        <span class="header-value">{{ problem?.internal_number }}</span>
                    </div>
                    <div class="header-spacer" />
                    <div class="header-info">
                        <span class="header-label">Data</span>
                        <span class="header-value">{{ formattedDate }}</span>
                    </div>
                </div>

                <div v-if="problem?.status" class="reporter-problem-status-section">
                    <StatusBadge
                        :status="problem.status"
                        :display-status="problem.reporter_status || getReporterStatusKey(problem.status)"
                        :label="problem.reporter_status_label || getReporterStatusLabel(problem.status)"
                    />
                    <button
                        v-if="canWatch"
                        type="button"
                        class="reporter-problem-watch-label"
                        :class="{ 'reporter-problem-watch-label--on': isProblemWatched }"
                        :disabled="watchToggleBusy"
                        @click="toggleProblemWatch"
                    >
                        {{ isProblemWatched ? 'Stebima' : 'Stebėti' }}
                    </button>
                </div>

                <ProblemMergeLinks
                    :merge-target="mergeTarget"
                    :merged-siblings="mergedSiblings"
                    :merged-children="mergedChildren"
                    link-route="reports.show"
                />

                <div class="reporter-problem-content">
                    <div class="reporter-problem-fields">
                        <div class="reporter-problem-field-row">
                            <AppInput
                                :model-value="problem?.category"
                                label="Kategorija"
                                type="text"
                                readonly
                            />
                            <AppInput
                                :model-value="problem?.nature ?? '—'"
                                label="Pobūdis"
                                type="text"
                                readonly
                            />
                        </div>
                        <AppInput
                            :model-value="problem?.address"
                            label="Adresas"
                            type="text"
                            readonly
                        />
                    </div>
                    <div class="reporter-problem-desc-media-row">
                        <div class="reporter-desc-media-col">
                            <div class="reporter-desc-media-label">Aprašymas</div>
                            <div class="reporter-desc-media-box">
                                <ProblemDetailBlocks
                                    hide-label
                                    :description="problem?.description ?? ''"
                                />
                            </div>
                        </div>
                        <div
                            v-if="reporterShowsAttachmentsColumn"
                            class="reporter-desc-media-col reporter-priedai-col"
                        >
                            <div class="reporter-desc-media-label">Priedai</div>
                            <p
                                v-if="showFileSection && canUploadAttachments && uploadLimitsHint"
                                class="reporter-upload-limits-hint"
                            >
                                {{ uploadLimitsHint }}
                            </p>
                            <div class="reporter-desc-media-box">
                                <ProblemAttachmentsPanel
                                    compact
                                    fill-compact-surface
                                    hide-heading
                                    direct-lightbox
                                    :attachments="problem.attachments ?? []"
                                    :problem-id="problem.id"
                                    :can-delete="canUploadAttachments"
                                >
                                    <template
                                        v-if="canUploadAttachments && showFileSection"
                                        #add
                                    >
                                        <button
                                            type="button"
                                            class="reporter-add-cell"
                                            :disabled="isUploading"
                                            :aria-label="isUploading ? 'Įkeliama' : 'Pridėti failą'"
                                            @click="triggerPicker"
                                        >
                                            {{ isUploading ? '…' : '+' }}
                                        </button>
                                    </template>
                                    <template
                                        v-if="
                                            canUploadAttachments &&
                                                showFileSection &&
                                                !(problem.attachments?.length)
                                        "
                                        #empty
                                    >
                                        <button
                                            type="button"
                                            class="reporter-add-cell reporter-add-cell--fill"
                                            :disabled="isUploading"
                                            :aria-label="isUploading ? 'Įkeliama' : 'Pridėti failą'"
                                            @click="triggerPicker"
                                        >
                                            {{ isUploading ? '…' : '+' }}
                                        </button>
                                    </template>
                                </ProblemAttachmentsPanel>
                                <input
                                    v-if="canUploadAttachments && showFileSection"
                                    ref="fileInputRef"
                                    type="file"
                                    class="reporter-problem-file-input-hidden"
                                    multiple
                                    :accept="uploadRules.accept_attr"
                                    :disabled="isUploading"
                                    @change="onAttachInputChange"
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="fileHint"
                        class="reporter-upload-error-below-desc"
                        role="alert"
                    >
                        {{ fileHint }}
                    </p>
                </div>

                <div class="reporter-problem-actions">
                    <AppButton
                        text="Pranešimo eiga"
                        variant="primary"
                        as-button
                        @click="goToReviewProgress"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.reporter-problem-page {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.reporter-problem-detail {
    width: 100%;
}

.reporter-problem-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--color-border);
    width: 100%;
    box-sizing: border-box;
}

.reporter-problem-back {
    display: inline-flex;
    align-items: center;
    color: var(--color-primary);
    text-decoration: none;
}

.reporter-problem-back:hover {
    opacity: 0.8;
}

.reporter-problem-back-icon {
    display: block;
    width: 16px;
    height: 16px;
    object-fit: contain;
}

.header-spacer {
    flex: 1;
}

.header-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.header-label {
    font-size: 14px;
    color: var(--color-text-muted);
}

.header-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text);
}

.reporter-problem-status-section {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.reporter-problem-watch-label {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--button-height, 45px);
    padding: 12px 24px;
    border-radius: 15px;
    font-size: 16px;
    font-weight: 600;
    line-height: 1.25;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    border: 1px solid #cfd8dc;
    background: #eceff1;
    color: #455a64;
    cursor: pointer;
    transition: opacity 0.15s ease, background 0.15s ease;
}

.reporter-problem-watch-label--on {
    border-color: #81c784;
    background: #e8f5e9;
    color: #1b5e20;
}

.reporter-problem-watch-label:hover:not(:disabled) {
    opacity: 0.85;
}

.reporter-problem-watch-label:disabled {
    opacity: 0.5;
    cursor: wait;
}

.reporter-problem-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.reporter-problem-fields {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 600px;
}

.reporter-problem-fields :deep(.app-input-group) {
    margin-bottom: 0;
}

.reporter-problem-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 520px) {
    .reporter-problem-field-row {
        grid-template-columns: 1fr;
    }
}

.reporter-problem-desc-media-row {
    --reporter-desc-block-min-h: 220px;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 24px;
    align-items: stretch;
}

.reporter-desc-media-col {
    flex: 1 1 calc(50% - 12px);
    min-width: min(100%, 280px);
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.reporter-desc-media-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    line-height: 1.25;
    min-height: 1.25em;
    flex-shrink: 0;
}

.reporter-desc-media-box {
    position: relative;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: var(--reporter-desc-block-min-h, 220px);
    min-width: 0;
}

.reporter-desc-media-box :deep(.attachments-panel--compact) {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.reporter-priedai-col {
    position: relative;
}

.reporter-problem-file-input-hidden {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
}

.reporter-add-cell {
    width: 96px;
    height: 96px;
    padding: 0;
    box-sizing: border-box;
    border: 1px dashed #c5c5c5;
    border-radius: 6px;
    background: #fff;
    color: #666;
    font-size: 32px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
}

.reporter-add-cell--fill {
    flex: 1 1 auto;
    width: 100%;
    align-self: stretch;
    min-height: 0;
    height: 100%;
    font-size: 42px;
}

.reporter-add-cell:hover:not(:disabled) {
    border-color: var(--color-primary, #1946A0);
    color: var(--color-primary, #1946A0);
    background: #f5f7fc;
}

.reporter-add-cell:disabled {
    cursor: wait;
    opacity: 0.65;
}

.reporter-upload-limits-hint {
    margin: 0 0 10px;
    font-size: 13px;
    line-height: 1.45;
    color: #777;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.reporter-upload-error-below-desc {
    margin: 12px 0 0;
    font-size: 14px;
    line-height: 1.45;
    color: #c0392b;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    width: 100%;
    max-width: 100%;
}

@media (max-width: 768px) {
    .reporter-desc-media-col {
        flex: 1 1 100%;
    }
}

.reporter-problem-actions {
    margin-top: 32px;
    display: flex;
    justify-content: flex-start;
}
</style>
