<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import { adminApi } from '@/constants/api/adminApi'
import { EMAIL_NOTIFICATION_PLACEHOLDER_DOCS_BY_KEY } from '@/constants/content/emailNotificationPlaceholderDocs'

const templates = ref([])
const selectedTemplateId = ref(null)
const loading = ref(true)
const savingId = ref(null)
const errorMessage = ref('')
const showSuccessModal = ref(false)
const previewOpen = ref(false)
const previewHtml = ref('')
const previewLoading = ref(false)

const selectedTemplate = computed(() => {
    if (selectedTemplateId.value === '' || selectedTemplateId.value === null || selectedTemplateId.value === undefined) return null
    const id = Number(selectedTemplateId.value)
    return templates.value.find((template) => template.id === id) ?? null
})

const placeholderDocs = computed(() => {
    const key = selectedTemplate.value?.key
    if (!key) {
        return []
    }
    return EMAIL_NOTIFICATION_PLACEHOLDER_DOCS_BY_KEY[key] ?? []
})

const selectOptions = computed(() => {
    return templates.value.map((template) => ({
        value: String(template.id),
        label: template.name,
    }))
})

function load() {
    loading.value = true
    errorMessage.value = ''
    axios.get(adminApi.emailNotifications)
        .then((response) => {
            templates.value = response.data.templates ?? []
            if (templates.value.length && !selectedTemplateId.value) {
                selectedTemplateId.value = String(templates.value[0].id)
            }
        })
        .catch(() => { errorMessage.value = 'Nepavyko gauti šablonų' })
        .finally(() => { loading.value = false })
}

const BODY_TEXTAREA_ID = 'email-notification-body-editor'

function getBodyTextarea() {
    return typeof document !== 'undefined' ? document.getElementById(BODY_TEXTAREA_ID) : null
}

function insertAroundSelection(open, close) {
    const ta = getBodyTextarea()
    const template = selectedTemplate.value
    if (!ta || !template) return
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const text = template.body
    const selected = text.slice(start, end)
    const before = text.slice(0, start)
    const after = text.slice(end)
    template.body = before + open + selected + close + after
    nextTick(() => {
        ta.focus()
        const innerStart = start + open.length
        ta.setSelectionRange(innerStart, innerStart + selected.length)
    })
}

function insertMarkdownLink() {
    const ta = getBodyTextarea()
    const template = selectedTemplate.value
    if (!ta || !template) return
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const text = template.body
    const selected = text.slice(start, end)
    const label = selected.length ? selected : 'Problema'
    const insert = `[${label}]({url})`
    const before = text.slice(0, start)
    const after = text.slice(end)
    template.body = before + insert + after
    nextTick(() => {
        ta.focus()
        const urlTokenStart = before.length + insert.indexOf('{url}')
        ta.setSelectionRange(urlTokenStart, urlTokenStart + 5)
    })
}

async function openPreview() {
    const template = selectedTemplate.value
    if (!template) return
    previewOpen.value = true
    previewLoading.value = true
    previewHtml.value = ''
    try {
        const { data } = await axios.post(adminApi.emailNotificationPreviewBody, { body: template.body })
        previewHtml.value = data.html ?? ''
    } catch {
        previewHtml.value = '<p>Nepavyko sugeneruoti peržiūros.</p>'
    } finally {
        previewLoading.value = false
    }
}

function insertMarkdownListLine() {
    const ta = getBodyTextarea()
    const template = selectedTemplate.value
    if (!ta || !template) return
    const start = ta.selectionStart
    const text = template.body
    const insert = (start > 0 && text[start - 1] !== '\n' ? '\n' : '') + '- '
    template.body = text.slice(0, start) + insert + text.slice(start)
    nextTick(() => {
        ta.focus()
        const pos = start + insert.length
        ta.setSelectionRange(pos, pos)
    })
}

function saveTemplate(template) {
    savingId.value = template.id
    errorMessage.value = ''
    axios.put(adminApi.emailNotification(template.id), {
        subject: template.subject,
        body: template.body,
        is_enabled: template.is_enabled,
    })
        .then(() => {
            showSuccessModal.value = true
        })
        .catch(() => { errorMessage.value = 'Nepavyko išsaugoti' })
        .finally(() => { savingId.value = null })
}

onMounted(() => load())
</script>

<template>
    <div class="email-notification-editor">
        <p v-if="errorMessage" class="email-notification-editor-error">{{ errorMessage }}</p>
        <div v-if="loading" class="email-notification-editor-loading">Kraunama…</div>
        <template v-else>
            <div class="email-notification-editor-select-wrap">
                <label class="email-notification-editor-label">El. laiškas</label>
                <AppSelect
                    v-model="selectedTemplateId"
                    class="email-notification-editor-select"
                    variant="white"
                    :rounded="true"
                    placeholder=""
                >
                    <option
                        v-for="option in selectOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </AppSelect>
            </div>
            <div v-if="selectedTemplate" class="email-notification-editor-card">
                <div v-if="placeholderDocs.length" class="email-notification-editor-placeholders">
                    <p class="email-notification-editor-placeholders-title">Kintamieji šablone</p>
                    <ul class="email-notification-editor-placeholder-list">
                        <li
                            v-for="row in placeholderDocs"
                            :key="row.token"
                            class="email-notification-editor-placeholder-item"
                        >
                            <code class="email-notification-editor-code">{{ row.token }}</code>
                            <span class="email-notification-editor-placeholder-text">{{ row.text }}</span>
                        </li>
                    </ul>
                </div>
                <div class="email-notification-editor-switch-row">
                    <span class="email-notification-editor-switch-label">Siųsti šį laišką</span>
                    <AppSwitch
                        :model-value="selectedTemplate.is_enabled"
                        @update:model-value="selectedTemplate.is_enabled = $event"
                    />
                </div>
                <div class="email-notification-editor-field">
                    <label class="email-notification-editor-label">Tema</label>
                    <input
                        v-model="selectedTemplate.subject"
                        type="text"
                        class="email-notification-editor-input"
                        maxlength="500"
                    />
                </div>
                <div class="email-notification-editor-field">
                    <label class="email-notification-editor-label">Turinys</label>
                    <div class="email-notification-editor-toolbar-row">
                        <div class="email-notification-editor-toolbar">
                            <button type="button" class="email-notification-editor-toolbar-btn" @click="insertAroundSelection('**', '**')">B</button>
                            <button type="button" class="email-notification-editor-toolbar-btn" @click="insertAroundSelection('_', '_')">I</button>
                            <button type="button" class="email-notification-editor-toolbar-btn" @click="insertMarkdownLink">Nuoroda</button>
                            <button type="button" class="email-notification-editor-toolbar-btn" @click="insertMarkdownListLine">Sąrašas</button>
                        </div>
                        <button
                            type="button"
                            class="email-notification-editor-toolbar-btn email-notification-editor-toolbar-btn--primary email-notification-editor-toolbar-preview"
                            @click="openPreview"
                        >
                            Peržiūrėti
                        </button>
                    </div>
                    <textarea
                        :id="BODY_TEXTAREA_ID"
                        v-model="selectedTemplate.body"
                        class="email-notification-editor-textarea"
                        rows="8"
                        placeholder="Įrašykite turinį…"
                    />
                </div>
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="savingId === selectedTemplate.id"
                    @click="saveTemplate(selectedTemplate)"
                />
            </div>
        </template>
        <SuccessModal
            :show="showSuccessModal"
            message="Išsaugota."
            @close="showSuccessModal = false"
        />
        <BaseModal
            :open="previewOpen"
            title="Turinio peržiūra"
            width="640px"
            max-height="min(85vh, 720px)"
            @close="previewOpen = false"
        >
            <div v-if="previewLoading" class="email-notification-editor-preview-loading">Kraunama…</div>
            <div
                v-else
                class="email-notification-editor-preview-html transactional-md"
                v-html="previewHtml"
            />
            <template #footer>
                <div class="email-notification-editor-preview-footer">
                    <AppButton text="Uždaryti" variant="secondary" as-button @click="previewOpen = false" />
                </div>
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.email-notification-editor-error {
    color: #c00;
    margin: 0 0 12px 0;
    font-size: 14px;
}
.email-notification-editor-loading {
    color: #666;
    padding: 24px 0;
}
.email-notification-editor-select-wrap {
    margin-bottom: 20px;
    width: 100%;
    max-width: 480px;
}
.email-notification-editor-select-wrap .email-notification-editor-label {
    margin-bottom: 6px;
}
.email-notification-editor-select :deep(.app-select-group) {
    margin-bottom: 0;
}
.email-notification-editor-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}
.email-notification-editor-placeholders {
    margin: 0 0 16px 0;
    padding: 12px 14px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.email-notification-editor-placeholders-title {
    margin: 0 0 10px 0;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}
.email-notification-editor-placeholder-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
}
.email-notification-editor-placeholder-item {
    display: grid;
    grid-template-columns: minmax(0, auto) 1fr;
    gap: 10px 14px;
    align-items: start;
    margin-bottom: 10px;
    font-size: 13px;
    line-height: 1.45;
    color: #4b5563;
}
.email-notification-editor-placeholder-item:last-child {
    margin-bottom: 0;
}
.email-notification-editor-code {
    font-family: ui-monospace, monospace;
    font-size: 12px;
    color: #1e40af;
    background: #eff6ff;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
}
.email-notification-editor-placeholder-text {
    min-width: 0;
}
@media (max-width: 520px) {
    .email-notification-editor-placeholder-item {
        grid-template-columns: 1fr;
    }
}
.email-notification-editor-row {
    margin-bottom: 12px;
}
.email-notification-editor-switch-row {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    max-width: 100%;
}
.email-notification-editor-switch-label {
    font-size: 14px;
    color: #333;
    font-weight: 500;
}
.email-notification-editor-field {
    margin-bottom: 12px;
}
.email-notification-editor-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 6px;
    width: 100%;
}
.email-notification-editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.email-notification-editor-toolbar-preview {
    margin-left: auto;
    flex-shrink: 0;
}
.email-notification-editor-preview-loading {
    color: #666;
    font-size: 14px;
    padding: 8px 0;
}
.email-notification-editor-preview-html {
    font-size: 16px;
    line-height: 1.5;
    overflow-y: auto;
    max-height: min(55vh, 480px);
    padding-right: 4px;
}
.email-notification-editor-preview-html :deep(p) {
    margin: 0 0 12px;
    color: #718096;
}
.email-notification-editor-preview-html :deep(p:last-child) {
    margin-bottom: 0;
}
.email-notification-editor-preview-html :deep(a) {
    color: #1946a0;
    font-weight: 600;
    text-decoration: none;
}
.email-notification-editor-preview-html :deep(strong) {
    color: #3d4852;
}
.email-notification-editor-preview-html :deep(ul),
.email-notification-editor-preview-html :deep(ol) {
    margin: 0 0 12px;
    padding-left: 1.25rem;
    color: #718096;
}
.email-notification-editor-preview-footer {
    display: flex;
    justify-content: flex-end;
    width: 100%;
}
.email-notification-editor-toolbar-btn {
    padding: 4px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
}
.email-notification-editor-toolbar-btn:hover {
    background: #f3f4f6;
}
.email-notification-editor-toolbar-btn--primary {
    background-color: var(--color-primary, #1946a0);
    color: var(--color-white, #fff);
    border-color: var(--color-primary, #1946a0);
    font-weight: 600;
}
.email-notification-editor-toolbar-btn--primary:hover {
    background-color: var(--color-primary-hover, #163d8a);
    border-color: var(--color-primary-hover, #163d8a);
}
.email-notification-editor-toolbar-btn--primary:active {
    background-color: var(--color-primary-dark, #12306e);
    border-color: var(--color-primary-dark, #12306e);
}
.email-notification-editor-label {
    display: block;
    margin-bottom: 4px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}
.email-notification-editor-input {
    width: 100%;
    max-width: 500px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
}
.email-notification-editor-textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
    resize: vertical;
}
</style>
