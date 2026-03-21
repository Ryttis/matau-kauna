<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
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
    removeFile,
    clearQueue,
    triggerPicker,
    showFileSection,
} = useFileUploadQueue(uploadRules, { appendBaseline })

const form = ref({
    internal_number: '',
    category: '',
    address: '',
    description: '',
})

const isSubmitting = ref(false)

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
            clearQueue()
        }
    }
)

const handleClose = () => emit('close')

const fileFieldLabel = computed(() => fileUploadPickerLabel(uploadRules.value))

const uploadHintText = computed(() =>
    formatDetailedFileUploadHint(uploadRules.value, { includeExistingInTotal: true }),
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
        width="520px"
        max-width="520px"
        max-height="90vh"
        @close="handleClose"
    >
        <template #header>
            <div class="edit-modal-header-content">
                <input
                    v-model="form.internal_number"
                    type="text"
                    class="edit-modal-nr-input"
                    :placeholder="problem?.internal_number || 'Nr'"
                />
            </div>
        </template>

        <div class="edit-modal-body">
            <p class="edit-date-line">Data: {{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</p>

            <div class="edit-form-field edit-form-field-status">
                <StatusBadge :status="problem?.status" />
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

            <div
                class="edit-form-row"
                :class="showFileSection ? 'edit-form-row-two' : 'edit-form-row-one'"
            >
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
                    <div class="edit-file-placeholder" @click="triggerPicker">
                        <span class="edit-file-text">Prisegti failą</span>
                    </div>
                    <p class="edit-file-hint">{{ uploadHintText }}</p>
                    <p v-if="fileHint" class="edit-file-error">{{ fileHint }}</p>
                    <ul v-if="selectedFiles.length" class="edit-file-list">
                        <li v-for="(f, idx) in selectedFiles" :key="idx + f.name" class="edit-file-item">
                            <span class="edit-file-name">{{ f.name }}</span>
                            <button type="button" class="edit-file-remove" @click="removeFile(idx)">
                                Pašalinti
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="edit-save-wrap">
                <AppButton
                    :text="isSubmitting ? 'Saugoma...' : 'Saugoti'"
                    variant="primary"
                    :disabled="isSubmitting"
                    as-button
                    class="edit-save-btn"
                    @click="handleSave"
                />
            </div>
        </template>
    </BaseModal>
</template>

<style scoped>
.edit-modal-header-content {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
}

.edit-modal-nr-input {
    width: 100%;
    max-width: 200px;
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
    gap: 12px;
}

:deep(.modal-body) {
    padding: 16px;
}

:deep(.modal-header) {
    padding: 12px 16px;
}

.edit-date-line {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #000;
    font-family: 'Open Sans', sans-serif;
}

.edit-form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.edit-form-field-status {
    align-items: flex-start;
}

.edit-form-field-status :deep(.status-badge) {
    flex: none;
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

.edit-form-row-one {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.edit-form-row-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    align-items: stretch;
}

.edit-form-row-two .edit-form-field {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.edit-form-row-two .edit-field-photo .edit-file-placeholder {
    flex: 0 0 auto;
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
    margin: 6px 0 0;
    font-size: 11px;
    line-height: 1.35;
    color: #777;
    font-family: 'Open Sans', sans-serif;
}

.edit-file-error {
    margin: 6px 0 0;
    font-size: 12px;
    color: #c0392b;
    font-family: 'Open Sans', sans-serif;
}

.edit-file-list {
    margin: 8px 0 0;
    padding-left: 18px;
    font-size: 12px;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

.edit-file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.edit-file-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.edit-file-remove {
    flex: none;
    border: none;
    background: none;
    color: var(--color-primary, #1946a0);
    cursor: pointer;
    font-size: 12px;
    text-decoration: underline;
    padding: 0;
}

.edit-save-wrap {
    display: flex;
    justify-content: center;
    width: 100%;
}

.edit-save-btn {
    width: var(--button-width);
}

:deep(.edit-save-btn) {
    height: var(--control-height);
    border-radius: var(--control-radius);
}

:deep(.modal) {
    border-radius: 5px;
}
</style>
