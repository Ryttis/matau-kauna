<script setup>
import { ref, watch, computed, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import { useFileUploadQueue, defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { useReporterApi } from '@/composables/useReporterApi'
import { messageFromAxiosError } from '@/utils/laravelApiErrors'
import {
    attachmentsToUploadBaseline,
    fileUploadPickerLabel,
    formatDetailedFileUploadHint,
} from '@/utils/fileUploadClient'

const props = defineProps({
    show: Boolean,
    problem: Object,
    categories: Array,
    fileUpload: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['close'])

const api = useReporterApi()

const existingAttachments = ref([])

const uploadRules = computed(() => ({
    ...defaultFileUploadSettings(),
    ...props.fileUpload,
}))

const appendBaseline = computed(() => attachmentsToUploadBaseline(existingAttachments.value))

const {
    selectedFiles,
    fileHint,
    fileInputRef,
    handleFileChange,
    removeFile,
    clearQueue,
    triggerPicker,
    showFileSection,
} = useFileUploadQueue(uploadRules, { appendBaseline })

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

const form = ref({
    internal_number: '',
    category: '',
    address: '',
    description: '',
})

const isSubmitting = ref(false)

const showDeleteExistingConfirm = ref(false)
const pendingExistingAttachment = ref(null)
const deleteExistingLoading = ref(false)

function openDeleteExistingConfirm(a) {
    pendingExistingAttachment.value = a
    showDeleteExistingConfirm.value = true
}

function closeDeleteExistingConfirm() {
    if (deleteExistingLoading.value) return
    showDeleteExistingConfirm.value = false
    pendingExistingAttachment.value = null
}

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

function confirmDeleteExisting() {
    const a = pendingExistingAttachment.value
    if (!a || !props.problem?.id) return
    deleteExistingLoading.value = true
    router.delete(
        route('problems.attachments.destroy', {
            problem: props.problem.id,
            attachment: a.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                existingAttachments.value = existingAttachments.value.filter((x) => x.id !== a.id)
                router.reload({ only: ['problem'], preserveScroll: true })
            },
            onFinish: () => {
                deleteExistingLoading.value = false
                closeDeleteExistingConfirm()
            },
        },
    )
}

watch(
    () => props.show,
    (newVal) => {
        if (newVal && props.problem) {
            form.value = {
                internal_number: props.problem.internal_number || '',
                category: props.problem.category || '',
                address: props.problem.address || '',
                description: props.problem.description || '',
            }
            existingAttachments.value = [...(props.problem.attachments ?? [])]
            revokeFilePreviews()
            clearQueue()
            return
        }
        if (!newVal) {
            closeRemoveQueuedConfirm()
            if (!deleteExistingLoading.value) {
                closeDeleteExistingConfirm()
            }
        }
    },
)

const handleClose = () => emit('close')

const fileFieldLabel = computed(() => fileUploadPickerLabel(uploadRules.value))

const uploadHintText = computed(() =>
    formatDetailedFileUploadHint(uploadRules.value, { includeExistingInTotal: true }),
)

const attachmentTileCount = computed(
    () => existingAttachments.value.length + selectedFiles.value.length,
)

const handleSave = async () => {
    if (isSubmitting.value) return
    isSubmitting.value = true
    fileHint.value = ''

    try {
        if (selectedFiles.value.length) {
            await api.postProblemAttachments(props.problem.id, selectedFiles.value)
            clearQueue()
        }
    } catch (e) {
        isSubmitting.value = false
        fileHint.value = messageFromAxiosError(e, 'Įkelti nepavyko.')
        return
    }

    router.put(route('problems.update', props.problem.id), form.value, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => {
            isSubmitting.value = false
        },
    })
}
</script>

<template>
    <BaseModal
        :show="show"
        width="680px"
        max-width="96vw"
        max-height="90vh"
        footer-layout="column"
        @close="handleClose"
    >
        <div class="edit-modal-body">
            <div class="edit-modal-header-field">
                <label class="edit-modal-nr-label" for="edit-modal-internal-number">Problemos numeris</label>
                <input
                    id="edit-modal-internal-number"
                    v-model="form.internal_number"
                    type="text"
                    class="edit-modal-nr-input"
                    :placeholder="problem?.internal_number || 'Nr'"
                />
            </div>

            <div class="edit-form-field">
                <AppSelect
                    v-model="form.category"
                    label="Kategorija"
                    variant="white"
                    :rounded="true"
                    placeholder="Pasirinkite kategoriją"
                >
                    <option
                        v-for="category in (categories || [])"
                        :key="category"
                        :value="category"
                    >
                        {{ category }}
                    </option>
                </AppSelect>
            </div>

            <div class="edit-form-field">
                <AppInput
                    v-model="form.address"
                    label="Adresas"
                    type="text"
                    :show-icon="true"
                />
            </div>

            <div class="edit-form-field">
                <AppInput
                    v-model="form.description"
                    label="Aprašymas"
                    :as-textarea="true"
                    :rows="3"
                    :show-icon="true"
                />
            </div>

            <div v-if="showFileSection" class="edit-form-field edit-field-photo">
                    <label class="edit-field-label">{{ fileFieldLabel }}</label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="edit-file-input-hidden"
                        multiple
                        :accept="uploadRules.accept_attr"
                        @change="handleFileChange"
                    >
                    <div
                        v-if="existingAttachments.length || selectedFiles.length"
                        class="edit-attachments-grid"
                        :class="{ 'edit-attachments-grid--scroll': attachmentTileCount > 3 }"
                    >
                        <div
                            v-for="a in existingAttachments"
                            :key="'ex-' + a.id"
                            class="edit-att-tile"
                        >
                            <div class="edit-att-thumb-wrap">
                                <img
                                    v-if="a.kind === 'photo'"
                                    :src="a.url"
                                    alt=""
                                    class="edit-att-thumb"
                                />
                                <video
                                    v-else
                                    :src="a.url"
                                    class="edit-att-thumb edit-att-thumb--video"
                                    muted
                                    playsinline
                                />
                            </div>
                            <button
                                type="button"
                                class="edit-att-remove-corner"
                                :disabled="deleteExistingLoading || isSubmitting"
                                aria-label="Pašalinti priedą"
                                @click="openDeleteExistingConfirm(a)"
                            >
                                Šalinti
                            </button>
                        </div>
                        <div
                            v-for="(f, idx) in selectedFiles"
                            :key="'new-' + idx + f.name"
                            class="edit-att-tile"
                        >
                            <div class="edit-att-thumb-wrap">
                                <img
                                    v-if="filePreviewEntries[idx]?.kind === 'image'"
                                    :src="filePreviewEntries[idx].url"
                                    alt=""
                                    class="edit-att-thumb"
                                />
                                <video
                                    v-else-if="filePreviewEntries[idx]?.kind === 'video'"
                                    :src="filePreviewEntries[idx].url"
                                    class="edit-att-thumb edit-att-thumb--video"
                                    muted
                                    playsinline
                                />
                                <div
                                    v-else
                                    class="edit-att-thumb edit-att-thumb--fallback"
                                    aria-hidden="true"
                                >
                                    <span class="edit-att-fallback-text">{{ f.name }}</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="edit-att-remove-corner"
                                :disabled="isSubmitting"
                                aria-label="Pašalinti"
                                @click="openRemoveQueuedConfirm(idx)"
                            >
                                Šalinti
                            </button>
                        </div>
                    </div>
                    <div class="edit-file-placeholder" @click="triggerPicker">
                        <span class="edit-file-text">Prisegti failą</span>
                    </div>
                    <p class="edit-file-hint">{{ uploadHintText }}</p>
                    <p v-if="fileHint" class="edit-file-error">{{ fileHint }}</p>
            </div>
        </div>

        <template #footer>
            <div class="edit-save-wrap">
                <AppButton
                    text="Saugoti"
                    variant="primary"
                    as-button
                    class="edit-save-btn"
                    :loading="isSubmitting"
                    @click="handleSave"
                />
            </div>
        </template>
    </BaseModal>

    <ConfirmModal
        :open="showDeleteExistingConfirm"
        message="Ar tikrai pašalinti šį priedą?"
        confirm-text="Pašalinti"
        cancel-text="Atšaukti"
        footer-layout="column"
        confirm-variant="danger"
        width="400px"
        :z-index="5000"
        :loading="deleteExistingLoading"
        @close="closeDeleteExistingConfirm"
        @confirm="confirmDeleteExisting"
    />

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
</template>

<style scoped>
.edit-modal-header-field {
    display: flex;
    flex-direction: column;
    gap: var(--edit-modal-label-gap, 6px);
    min-width: 0;
    width: 100%;
}

.edit-modal-nr-label {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #000;
    font-family: 'Open Sans', sans-serif;
}

.edit-modal-nr-input {
    width: 100%;
    height: var(--control-height);
    padding: 0 16px;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: var(--control-radius);
    font-size: 14px;
    font-family: 'Open Sans', sans-serif;
    color: #333;
    background: #fff;
    box-sizing: border-box;
}

.edit-modal-nr-input::placeholder {
    color: #999;
}

.edit-modal-nr-input:focus {
    outline: none;
    border-color: var(--color-primary, #1946a0);
}

.edit-modal-body {
    display: flex;
    flex-direction: column;
    gap: var(--edit-modal-section-gap, 16px);
}

:deep(.modal-body) {
    padding: 8px 16px 16px !important;
}

:deep(.modal-header) {
    position: absolute !important;
    top: 8px !important;
    right: 8px !important;
    left: auto !important;
    z-index: 2 !important;
    display: block !important;
    padding: 0 !important;
    min-height: 0 !important;
}

:deep(.modal-header .x-btn) {
    margin: 0 !important;
}

:deep(.modal-header .modal-title-empty) {
    display: none;
}

.edit-form-field {
    display: flex;
    flex-direction: column;
    gap: var(--edit-modal-label-gap, 6px);
    min-width: 0;
}

.edit-field-label {
    font-size: 14px;
    font-weight: 600;
    color: #000;
    font-family: 'Open Sans', sans-serif;
}

.edit-modal-body :deep(.app-input-label) {
    color: #000;
}

.edit-field-photo {
    width: 100%;
}

.edit-field-photo .edit-file-placeholder {
    width: 100%;
    min-height: 80px;
}

.edit-file-input-hidden {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
}

.edit-file-placeholder {
    width: 100%;
    min-height: 80px;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: var(--control-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #f9f9f9;
    box-sizing: border-box;
}

.edit-file-placeholder:hover {
    border-color: var(--color-primary, #1946a0);
}

.edit-file-text {
    font-size: 13px;
    color: #666;
    font-family: 'Open Sans', sans-serif;
}

.edit-file-hint {
    margin: 8px 0 0;
    font-size: 11px;
    line-height: 1.35;
    color: #777;
    font-family: 'Open Sans', sans-serif;
}

.edit-file-error {
    margin: 8px 0 0;
    font-size: 12px;
    color: #c0392b;
    font-family: 'Open Sans', sans-serif;
}

.edit-attachments-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 var(--edit-modal-label-gap, 6px);
    align-items: flex-start;
    min-width: 0;
    width: 100%;
}

.edit-attachments-grid--scroll {
    max-height: 88px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 2px;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
}

.edit-attachments-grid--scroll::-webkit-scrollbar {
    width: 8px;
}

.edit-attachments-grid--scroll::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.22);
    border-radius: 4px;
}

.edit-att-tile {
    position: relative;
    flex: none;
    width: 88px;
    line-height: 0;
}

.edit-att-thumb-wrap {
    width: 88px;
    height: 88px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--color-border, #e0e0e0);
    background: #f5f5f5;
    box-sizing: border-box;
}

.edit-att-thumb {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.edit-att-thumb--video {
    background: #111;
    object-fit: cover;
}

.edit-att-thumb--fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    box-sizing: border-box;
}

.edit-att-fallback-text {
    font-size: 9px;
    line-height: 1.2;
    color: #555;
    text-align: center;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.edit-att-remove-corner {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 2;
    padding: 3px 6px;
    font-size: 10px;
    font-weight: 600;
    font-family: 'Open Sans', sans-serif;
    color: #fff;
    background: rgba(180, 40, 40, 0.92);
    border: none;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
    line-height: 1.2;
}

.edit-att-remove-corner:hover:not(:disabled) {
    background: rgba(200, 50, 50, 1);
}

.edit-att-remove-corner:disabled {
    opacity: 0.65;
    cursor: wait;
}

.edit-save-wrap {
    display: flex;
    justify-content: flex-end;
    width: 100%;
}

.edit-save-wrap :deep(.edit-save-btn.app-button--fixed) {
    width: var(--button-width);
    max-width: 100%;
    height: var(--button-height);
    min-height: var(--button-height);
    box-sizing: border-box;
    border-radius: var(--button-border-radius);
    padding: 9px 24px;
    font-size: var(--button-font-size, 16px);
    font-weight: var(--button-font-weight, 400);
    line-height: 150%;
    letter-spacing: -0.011em;
}

:deep(.modal) {
    position: relative;
    border-radius: 5px;
}
</style>
