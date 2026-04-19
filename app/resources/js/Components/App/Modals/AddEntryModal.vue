<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { ICON_PENCIL } from '@/constants/ui/icons'
import {
    REVIEW_PROGRESS_MODAL_STATUS_TAGS,
    REVIEW_ENTRY_VISIBILITY,
    reviewModalTagKeyForStatus,
} from '@/constants/domain/reviewProgressEntry'
import { useReviewProgressEntryForm } from '@/composables/useReviewProgressEntryForm'
import { useFileUploadQueue, defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { firstInertiaErrorForPrefix, humanizeTechnicalUploadError } from '@/utils/laravelApiErrors'
import {
    attachmentsToUploadBaseline,
    formatDetailedFileUploadHint,
} from '@/utils/fileUploadClient'

const props = defineProps({
    open: { type: Boolean, default: false },
    problemId: { type: Number, required: true },
    currentStatus: { type: String, default: '' },
    entry: {
        type: Object,
        default: null,
    },
    fileUpload: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['close'])

const todayDate = new Date().toLocaleDateString('lt-LT')
const selectedKey = ref(null)
const localStatusHint = ref('')

const modalTitle = computed(() => (props.entry?.id ? 'Koreguoti įrašą' : 'Būsenos keitimas'))

const headerDateDisplay = computed(() => {
    if (props.entry?.date) {
        return new Date(props.entry.date).toLocaleDateString('lt-LT')
    }
    return todayDate
})

const uploadRules = computed(() => ({
    ...defaultFileUploadSettings(),
    ...props.fileUpload,
}))

const fileUploadBaseline = computed(() =>
    attachmentsToUploadBaseline(props.entry?.attachments ?? []),
)

const {
    selectedFiles,
    fileHint,
    fileInputRef,
    handleFileChange,
    removeFile,
    clearQueue,
    triggerPicker,
    showFileSection,
} = useFileUploadQueue(uploadRules, { appendBaseline: fileUploadBaseline })

const filePreviewEntries = ref([])

function revokeFilePreviews() {
    filePreviewEntries.value.forEach((p) => {
        if (p?.url) URL.revokeObjectURL(p.url)
    })
    filePreviewEntries.value = []
}

function buildPreviewEntries(files) {
    return files.map((f) => {
        if (f.type?.startsWith('image/')) {
            return { url: URL.createObjectURL(f), kind: 'image' }
        }
        if (f.type?.startsWith('video/')) {
            return { url: URL.createObjectURL(f), kind: 'video' }
        }
        return { url: '', kind: 'none' }
    })
}

watch(
    selectedFiles,
    (files) => {
        revokeFilePreviews()
        filePreviewEntries.value = buildPreviewEntries(files)
    },
    { deep: true },
)

onBeforeUnmount(() => {
    revokeFilePreviews()
})

const showRemoveQueuedConfirm = ref(false)
const pendingQueuedFileIndex = ref(null)

function openRemoveQueuedConfirm(idx) {
    pendingQueuedFileIndex.value = idx
    showRemoveQueuedConfirm.value = true
}

function closeRemoveQueuedConfirm() {
    showRemoveQueuedConfirm.value = false
    pendingQueuedFileIndex.value = null
}

function confirmRemoveQueued() {
    const idx = pendingQueuedFileIndex.value
    if (idx == null) return
    removeFile(idx)
    closeRemoveQueuedConfirm()
}

const { form, submit } = useReviewProgressEntryForm(
    () => props.problemId,
    {
        onSuccess: () => emit('close'),
        getFiles: () => selectedFiles.value,
        getEntryId: () => props.entry?.id ?? null,
    },
)

const pendingSubmitVisibility = ref(null)

watch(() => form.processing, (busy) => {
    if (!busy) pendingSubmitVisibility.value = null
})

const selectedStatusValue = computed(() => {
    const tag = REVIEW_PROGRESS_MODAL_STATUS_TAGS.find((t) => t.key === selectedKey.value)
    return tag?.value ?? ''
})

function selectStatus(tag) {
    selectedKey.value = selectedKey.value === tag.key ? null : tag.key
    localStatusHint.value = ''
}

function runSubmit(visibility) {
    if (form.processing) return
    if (!selectedStatusValue.value) {
        localStatusHint.value = 'Pasirinkite būseną.'
        return
    }
    localStatusHint.value = ''
    form.status = selectedStatusValue.value
    pendingSubmitVisibility.value = visibility
    submit(visibility)
}

watch(
    () => [props.open, props.entry],
    ([isOpen, entry]) => {
        if (!isOpen) {
            selectedKey.value = null
            localStatusHint.value = ''
            closeRemoveQueuedConfirm()
            return
        }
        localStatusHint.value = ''
        form.clearErrors()
        clearQueue()
        if (entry?.id) {
            form.comment = entry.comment ?? ''
            form.visibility =
                entry.visibility === REVIEW_ENTRY_VISIBILITY.PUBLIC
                    ? REVIEW_ENTRY_VISIBILITY.PUBLIC
                    : REVIEW_ENTRY_VISIBILITY.INTERNAL
            selectedKey.value = reviewModalTagKeyForStatus(entry.status)
        } else {
            selectedKey.value = reviewModalTagKeyForStatus(props.currentStatus)
            form.comment = ''
            form.visibility = REVIEW_ENTRY_VISIBILITY.INTERNAL
        }
    },
)

const uploadHintText = computed(() =>
    formatDetailedFileUploadHint(uploadRules.value, {
        includeExistingInTotal: !!props.entry?.id,
    }),
)

const filesErrorText = computed(() => firstInertiaErrorForPrefix(form.errors, 'files'))
const sanitizedFilesErrorText = computed(() => humanizeTechnicalUploadError(filesErrorText.value))

const queuedAttachmentCount = computed(() => selectedFiles.value.length)
</script>

<template>
    <div class="add-entry-modal-root">
        <BaseModal
            :open="open"
            title=""
            width="560px"
            footer-layout="column"
            @close="$emit('close')"
        >
            <template #header>
                <span class="add-entry-modal-header-label">Data</span>
                <span class="add-entry-modal-header-date">{{ headerDateDisplay }}</span>
                <div class="add-entry-modal-header-spacer"></div>
            </template>

            <div class="add-entry-modal">
                <h2 class="add-entry-modal-title">{{ modalTitle }}</h2>

                <div class="add-entry-modal-section add-entry-status-section">
                    <div class="add-entry-status-row">
                        <button
                            v-for="tag in REVIEW_PROGRESS_MODAL_STATUS_TAGS"
                            :key="tag.key"
                            type="button"
                            class="add-entry-status-tag"
                            :class="[tag.borderClass, { 'add-entry-status-tag--selected': selectedKey === tag.key }]"
                            @click="selectStatus(tag)"
                        >
                            {{ tag.label }}
                        </button>
                    </div>
                    <p v-if="localStatusHint || form.errors.status" class="add-entry-field-error">
                        {{ localStatusHint || form.errors.status }}
                    </p>
                </div>

                <div class="add-entry-modal-section">
                    <label class="add-entry-modal-label">Komentaras</label>
                    <div class="add-entry-comment-wrap">
                        <textarea
                            v-model="form.comment"
                            class="add-entry-textarea"
                            rows="4"
                            placeholder="Įrašykite komentarą"
                        />
                        <img :src="ICON_PENCIL" alt="" class="add-entry-comment-icon" />
                    </div>
                    <p v-if="form.errors.comment" class="add-entry-field-error">{{ form.errors.comment }}</p>
                </div>

                <p v-if="filesErrorText" class="add-entry-field-error add-entry-files-error-after-comment">
                    {{ sanitizedFilesErrorText }}
                </p>

                <div v-if="showFileSection" class="add-entry-modal-section add-entry-attachments-section">
                    <label class="add-entry-modal-label">Priedai</label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="add-entry-file-input-hidden"
                        multiple
                        :accept="uploadRules.accept_attr"
                        @change="handleFileChange"
                    >
                    <div
                        v-if="selectedFiles.length"
                        class="add-entry-attachments-grid"
                        :class="{ 'add-entry-attachments-grid--scroll': queuedAttachmentCount > 3 }"
                    >
                        <div
                            v-for="(f, idx) in selectedFiles"
                            :key="idx + f.name"
                            class="add-entry-att-tile"
                        >
                            <div class="add-entry-att-thumb-wrap">
                                <img
                                    v-if="filePreviewEntries[idx]?.kind === 'image'"
                                    :src="filePreviewEntries[idx].url"
                                    alt=""
                                    class="add-entry-att-thumb"
                                />
                                <video
                                    v-else-if="filePreviewEntries[idx]?.kind === 'video'"
                                    :src="filePreviewEntries[idx].url"
                                    class="add-entry-att-thumb add-entry-att-thumb--video"
                                    muted
                                    playsinline
                                />
                                <div
                                    v-else
                                    class="add-entry-att-thumb add-entry-att-thumb--fallback"
                                    aria-hidden="true"
                                >
                                    <span class="add-entry-att-fallback-text">{{ f.name }}</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="add-entry-att-remove-corner"
                                :disabled="form.processing"
                                aria-label="Pašalinti"
                                @click.stop="openRemoveQueuedConfirm(idx)"
                            >
                                Šalinti
                            </button>
                        </div>
                    </div>
                    <div class="add-entry-photo-area" @click="triggerPicker">
                        <span class="add-entry-photo-text">Prisegti failą</span>
                    </div>
                    <p class="add-entry-file-hint">{{ uploadHintText }}</p>
                    <p v-if="fileHint" class="add-entry-field-error">{{ fileHint }}</p>
                </div>
                <p v-if="form.errors.visibility" class="add-entry-field-error">{{ form.errors.visibility }}</p>
            </div>

            <template #footer>
                <AppButton
                    text="Siųsti (vidinis komentaras)"
                    variant="primary"
                    as-button
                    class="add-entry-footer-btn"
                    :loading="form.processing && pendingSubmitVisibility === REVIEW_ENTRY_VISIBILITY.INTERNAL"
                    :disabled="form.processing"
                    @click="runSubmit(REVIEW_ENTRY_VISIBILITY.INTERNAL)"
                />
                <AppButton
                    text="Paskelbti viešai"
                    variant="secondary"
                    as-button
                    class="add-entry-footer-btn"
                    :loading="form.processing && pendingSubmitVisibility === REVIEW_ENTRY_VISIBILITY.PUBLIC"
                    :disabled="form.processing"
                    @click="runSubmit(REVIEW_ENTRY_VISIBILITY.PUBLIC)"
                />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showRemoveQueuedConfirm"
            message="Ar tikrai pašalinti šį priedą?"
            confirm-text="Pašalinti"
            cancel-text="Atšaukti"
            footer-layout="column"
            confirm-variant="danger"
            width="400px"
            :z-index="5000"
            @close="closeRemoveQueuedConfirm"
            @confirm="confirmRemoveQueued"
        />
    </div>
</template>

<style scoped>
.add-entry-modal-header-label {
    font-size: 14px;
    color: #666;
}

.add-entry-modal-header-date {
    font-size: 14px;
    color: #333;
    margin-left: 8px;
}

.add-entry-modal-header-spacer {
    flex: 1;
}

.add-entry-modal {
    padding: 0 4px;
}

.add-entry-modal-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px;
}

.add-entry-modal-section {
    margin-bottom: 20px;
}

.add-entry-modal-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}

.add-entry-status-section .add-entry-modal-label {
    display: none;
}

.add-entry-status-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.add-entry-status-tag {
    padding: 10px 16px;
    background: #fff;
    border: 2px solid;
    border-radius: 999px;
    font-size: 14px;
    cursor: pointer;
    font-family: inherit;
    transition: background-color 0.2s, color 0.2s;
    width: 100%;
    min-width: 0;
    text-align: center;
}

.add-entry-status-tag--selected {
    color: #fff;
}

.add-entry-status--orange {
    border-color: #e67e22;
}
.add-entry-status-tag--selected.add-entry-status--orange {
    background: #e67e22;
}

.add-entry-status--yellow {
    border-color: #f1c40f;
}
.add-entry-status-tag--selected.add-entry-status--yellow {
    background: #f1c40f;
    color: #1e1c1c;
}

.add-entry-status--blue {
    border-color: var(--color-primary, #1946a0);
}
.add-entry-status-tag--selected.add-entry-status--blue {
    background: var(--color-primary, #1946a0);
    color: var(--color-white, #fff);
}

.add-entry-status--green {
    border-color: #27ae60;
}
.add-entry-status-tag--selected.add-entry-status--green {
    background: #27ae60;
    color: #fff;
}

.add-entry-status--gray {
    border-color: #666666;
}
.add-entry-status-tag--selected.add-entry-status--gray {
    background: #666666;
    color: #fff;
}

.add-entry-comment-wrap {
    position: relative;
}
.add-entry-textarea {
    width: 100%;
    padding: 12px 40px 12px 12px;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    resize: none;
    box-sizing: border-box;
}
.add-entry-comment-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    pointer-events: none;
    object-fit: contain;
}

.add-entry-file-input-hidden {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
}

.add-entry-attachments-section {
    margin-bottom: 14px;
}

.add-entry-attachments-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 8px;
    align-items: flex-start;
    min-width: 0;
}

.add-entry-attachments-grid--scroll {
    max-height: 88px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 2px;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
}

.add-entry-attachments-grid--scroll::-webkit-scrollbar {
    width: 8px;
}

.add-entry-attachments-grid--scroll::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.22);
    border-radius: 4px;
}

.add-entry-att-tile {
    position: relative;
    flex: none;
    width: 72px;
    line-height: 0;
}

.add-entry-att-thumb-wrap {
    width: 72px;
    height: 72px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--color-border, #e0e0e0);
    background: #f5f5f5;
    box-sizing: border-box;
}

.add-entry-att-thumb {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.add-entry-att-thumb--video {
    background: #111;
    object-fit: cover;
}

.add-entry-att-thumb--fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    box-sizing: border-box;
}

.add-entry-att-fallback-text {
    font-size: 8px;
    line-height: 1.15;
    color: #555;
    text-align: center;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.add-entry-att-remove-corner {
    position: absolute;
    top: 3px;
    right: 3px;
    z-index: 2;
    padding: 2px 5px;
    font-size: 9px;
    font-weight: 600;
    font-family: inherit;
    color: #fff;
    background: rgba(180, 40, 40, 0.92);
    border: none;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
    line-height: 1.2;
}

.add-entry-att-remove-corner:hover:not(:disabled) {
    background: rgba(200, 50, 50, 1);
}

.add-entry-att-remove-corner:disabled {
    opacity: 0.65;
    cursor: wait;
}

.add-entry-photo-area {
    min-height: 48px;
    padding: 8px 12px;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 8px;
    background: #f9f9f9;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-sizing: border-box;
}

.add-entry-photo-area:hover {
    border-color: var(--color-primary, #1946a0);
}

.add-entry-photo-text {
    font-size: 13px;
    color: #666;
    text-align: center;
}
.add-entry-file-hint {
    margin: 8px 0 0;
    font-size: 11px;
    line-height: 1.35;
    color: #777;
}

.add-entry-field-error {
    margin: 8px 0 0;
    font-size: 13px;
    color: #c0392b;
}

.add-entry-files-error-after-comment {
    margin-top: 4px;
    margin-bottom: 4px;
}

.add-entry-modal-root :deep(.modal-footer) {
    align-items: center;
    gap: 12px;
}

.add-entry-footer-btn {
    width: var(--button-width, 290px) !important;
    max-width: 100%;
    height: var(--button-height, 45px) !important;
    min-height: var(--button-height, 45px) !important;
    border-radius: var(--button-border-radius, 15px);
}
</style>
