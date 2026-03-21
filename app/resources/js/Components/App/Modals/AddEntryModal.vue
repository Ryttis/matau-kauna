<script setup>
import { ref, computed, watch } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { ICON_PENCIL } from '@/constants/icons'
import {
    REVIEW_PROGRESS_MODAL_STATUS_TAGS,
    REVIEW_ENTRY_VISIBILITY,
    reviewModalTagKeyForStatus,
} from '@/constants/reviewProgressEntry'
import { useReviewProgressEntryForm } from '@/composables/useReviewProgressEntryForm'
import { useFileUploadQueue, defaultFileUploadSettings } from '@/composables/useFileUploadQueue'
import { firstInertiaErrorForPrefix } from '@/utils/laravelApiErrors'
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

const { form, submit } = useReviewProgressEntryForm(
    () => props.problemId,
    {
        onSuccess: () => emit('close'),
        getFiles: () => selectedFiles.value,
        getEntryId: () => props.entry?.id ?? null,
    },
)

const selectedStatusValue = computed(() => {
    const tag = REVIEW_PROGRESS_MODAL_STATUS_TAGS.find((t) => t.key === selectedKey.value)
    return tag?.value ?? ''
})

function selectStatus(tag) {
    selectedKey.value = selectedKey.value === tag.key ? null : tag.key
    localStatusHint.value = ''
}

function runSubmit(visibility) {
    if (!selectedStatusValue.value) {
        localStatusHint.value = 'Pasirinkite būseną.'
        return
    }
    localStatusHint.value = ''
    form.status = selectedStatusValue.value
    submit(visibility)
}

watch(
    () => [props.open, props.entry],
    ([isOpen, entry]) => {
        if (!isOpen) {
            selectedKey.value = null
            localStatusHint.value = ''
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

                <div v-if="showFileSection" class="add-entry-modal-section">
                    <label class="add-entry-modal-label">Priedai</label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="add-entry-file-input-hidden"
                        multiple
                        :accept="uploadRules.accept_attr"
                        @change="handleFileChange"
                    >
                    <div class="add-entry-photo-area" @click="triggerPicker">
                        <span class="add-entry-photo-text">Pridėti nuotrauką arba vaizdo įrašą</span>
                    </div>
                    <p class="add-entry-file-hint">{{ uploadHintText }}</p>
                    <p v-if="fileHint || filesErrorText" class="add-entry-field-error">
                        {{ fileHint || filesErrorText }}
                    </p>
                    <ul v-if="selectedFiles.length" class="add-entry-file-list">
                        <li v-for="(f, idx) in selectedFiles" :key="idx + f.name" class="add-entry-file-item">
                            <span class="add-entry-file-name">{{ f.name }}</span>
                            <button type="button" class="add-entry-file-remove" @click.stop="removeFile(idx)">
                                Pašalinti
                            </button>
                        </li>
                    </ul>
                </div>
                <p v-if="form.errors.visibility" class="add-entry-field-error">{{ form.errors.visibility }}</p>
            </div>

            <template #footer>
                <AppButton
                    text="Siųsti (vidinis komentaras)"
                    variant="primary"
                    as-button
                    class="add-entry-footer-btn"
                    :disabled="form.processing"
                    @click="runSubmit(REVIEW_ENTRY_VISIBILITY.INTERNAL)"
                />
                <AppButton
                    text="Paskelbti viešai"
                    variant="secondary"
                    as-button
                    class="add-entry-footer-btn"
                    :disabled="form.processing"
                    @click="runSubmit(REVIEW_ENTRY_VISIBILITY.PUBLIC)"
                />
            </template>
        </BaseModal>
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

.add-entry-photo-area {
    min-height: 120px;
    background: repeating-conic-gradient(#e0e0e0 0% 25%, #f5f5f5 0% 50%) 50% / 16px 16px;
    border: 1px dashed #ccc;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.add-entry-photo-text {
    font-size: 14px;
    color: #666;
    background: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-align: center;
    max-width: 90%;
}
.add-entry-file-hint {
    margin: 8px 0 0;
    font-size: 11px;
    line-height: 1.35;
    color: #777;
}

.add-entry-file-list {
    margin: 10px 0 0;
    padding-left: 18px;
    font-size: 12px;
    color: #333;
}

.add-entry-file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.add-entry-file-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.add-entry-file-remove {
    flex: none;
    border: none;
    background: none;
    color: var(--color-primary, #1946a0);
    cursor: pointer;
    font-size: 12px;
    text-decoration: underline;
    padding: 0;
}

.add-entry-field-error {
    margin: 8px 0 0;
    font-size: 13px;
    color: #c0392b;
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
