<script setup>
import { ref, watch, nextTick, computed } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import { formatDateTimeLt } from '@/utils/dateFormat'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import ModalFooterActions from '@/Components/App/ModalFooterActions.vue'
import { adminApi } from '@/constants/api/adminApi'

const props = defineProps({
    type: { type: String, required: true },
    label: { type: String, required: true }
})

const versions = ref([])
const loading = ref(true)
const saving = ref(false)
const editingId = ref(null)
const form = ref({ title: '', body: '', items: [] })
const showEditorModal = ref(false)
const editingIsPublished = ref(false)
const bodyTextareaRef = ref(null)
const publishModalVersion = ref(null)
const versionToDelete = ref(null)
const versionToUnpublish = ref(null)
const requireReconsent = ref(false)
const successMessage = ref('')
const showSuccessModal = ref(false)
const errorMessage = ref('')

const isTerms = () => props.type === 'terms'
const isFaq = computed(() => props.type === 'faq')

const editorModalTitle = computed(() => {
    if (!editingId.value) {
        return `Nauja versija - ${props.label}`
    }
    const v = versions.value.find((x) => x.id === editingId.value)
    return v ? `Redaguoti versiją ${v.version}` : 'Redaguoti versiją'
})

function loadVersions() {
    const requestType = props.type
    loading.value = true
    errorMessage.value = ''
    axios.get(adminApi.contentVersions, { params: { type: requestType } })
        .then((res) => {
            if (props.type !== requestType) return
            versions.value = res.data.versions
        })
        .catch(() => {
            if (props.type !== requestType) return
            errorMessage.value = 'Nepavyko gauti versijų'
        })
        .finally(() => {
            if (props.type !== requestType) return
            loading.value = false
        })
}

watch(
    () => props.type,
    () => {
        closeEditorModal()
        publishModalVersion.value = null
        versionToDelete.value = null
        versionToUnpublish.value = null
        loadVersions()
    },
    { immediate: true },
)

function closeEditorModal() {
    showEditorModal.value = false
    editingId.value = null
    editingIsPublished.value = false
    form.value = { title: '', body: '', items: [] }
}

function openCreateModal() {
    editingId.value = null
    editingIsPublished.value = false
    form.value = {
        title: '',
        body: '',
        items: isFaq.value ? [{ question: '', answer: '' }] : [],
    }
    showEditorModal.value = true
}

function openEditModal(v) {
    editingId.value = v.id
    editingIsPublished.value = !!v.is_published
    form.value = {
        title: v.title ?? '',
        body: v.body ?? '',
        items: Array.isArray(v.items) ? v.items.map(it => ({ question: it.question ?? '', answer: it.answer ?? '' })) : [],
    }
    showEditorModal.value = true
}

function addFaqItem() {
    form.value.items.push({ question: '', answer: '' })
}

function removeFaqItem(index) {
    form.value.items.splice(index, 1)
}

function moveFaqItem(index, delta) {
    const target = index + delta
    if (target < 0 || target >= form.value.items.length) return
    const arr = form.value.items
    const tmp = arr[index]
    arr[index] = arr[target]
    arr[target] = tmp
}

function insertTag(open, close = open) {
    const ta = bodyTextareaRef.value
    if (!ta) return
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const text = form.value.body
    const selected = text.slice(start, end)
    const before = text.slice(0, start)
    const after = text.slice(end)
    form.value.body = before + open + selected + close + after
    nextTick(() => {
        ta.focus()
        ta.setSelectionRange(start + open.length, start + open.length + selected.length)
    })
}

function insertList() {
    const ta = bodyTextareaRef.value
    if (!ta) return
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const text = form.value.body
    const selected = text.slice(start, end)
    const before = text.slice(0, start)
    const after = text.slice(end)
    const lines = selected.split('\n').map(l => l.trim()).filter(Boolean)
    const items = lines.length ? lines : ['']
    const insert = '<ul>\n' + items.map(l => `  <li>${l}</li>`).join('\n') + '\n</ul>'
    form.value.body = before + insert + after
    nextTick(() => {
        ta.focus()
        ta.setSelectionRange(before.length + insert.length, before.length + insert.length)
    })
}

function insertParagraph() {
    const ta = bodyTextareaRef.value
    if (!ta) return
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const text = form.value.body
    const selected = text.slice(start, end)
    const before = text.slice(0, start)
    const after = text.slice(end)
    const inner = selected || 'Įrašykite tekstą'
    const insert = `<p>${inner}</p>`
    form.value.body = before + insert + after
    nextTick(() => {
        ta.focus()
        const innerStart = before.length + '<p>'.length
        ta.setSelectionRange(innerStart, innerStart + inner.length)
    })
}

function insertLink() {
    const ta = bodyTextareaRef.value
    if (!ta) return
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const text = form.value.body
    const selected = text.slice(start, end)
    const label = selected.length ? selected : 'nuoroda'
    const insert = `<a href="">${label}</a>`
    const before = text.slice(0, start)
    const after = text.slice(end)
    form.value.body = before + insert + after
    nextTick(() => {
        ta.focus()
        const hrefStart = before.length + '<a href="'.length
        ta.setSelectionRange(hrefStart, hrefStart)
    })
}

function save() {
    saving.value = true
    successMessage.value = ''
    errorMessage.value = ''
    const isEdit = !!editingId.value
    const wasPublishedEdit = isEdit && editingIsPublished.value
    const id = editingId.value
    const payload = {
        title: form.value.title,
        body: form.value.body,
    }
    if (isFaq.value) {
        // Drop empty rows before sending so admins don't end up with blank Q&A on the public page.
        payload.items = form.value.items
            .map(it => ({ question: (it.question ?? '').trim(), answer: (it.answer ?? '').trim() }))
            .filter(it => it.question !== '' || it.answer !== '')
    }
    const promise = isEdit
        ? axios.put(adminApi.contentVersion(id), payload)
        : axios.post(adminApi.contentVersions, { type: props.type, ...payload })
    promise
        .then(() => {
            if (!isEdit) {
                successMessage.value = 'Nauja versija sukurta sėkmingai.'
            } else if (wasPublishedEdit) {
                successMessage.value = 'Paskelbtas turinys atnaujintas.'
            } else {
                successMessage.value = 'Versija atnaujinta sėkmingai.'
            }
            showSuccessModal.value = true
            loadVersions()
            closeEditorModal()
        })
        .catch(err => { errorMessage.value = err.response?.data?.message ?? 'Klaida išsaugant' })
        .finally(() => { saving.value = false })
}

function openPublishModal(v) {
    if (v.is_published) return
    publishModalVersion.value = v
    requireReconsent.value = false
}

function openDeleteModal(v) {
    if (v.is_published) return
    versionToDelete.value = v
}

function closeDeleteModal() {
    versionToDelete.value = null
}

function openUnpublishModal(v) {
    if (!v.is_published) return
    versionToUnpublish.value = v
}

function closeUnpublishModal() {
    versionToUnpublish.value = null
}

function doUnpublish() {
    const v = versionToUnpublish.value
    if (!v) return
    saving.value = true
    errorMessage.value = ''
    axios.post(adminApi.contentVersionUnpublish(v.id))
        .then(() => {
            closeUnpublishModal()
            successMessage.value = 'Skelbimas atšauktas. Versija dabar kaip juodraštis.'
            showSuccessModal.value = true
            loadVersions()
        })
        .catch(err => { errorMessage.value = err.response?.data?.message ?? 'Nepavyko atšaukti.' })
        .finally(() => { saving.value = false })
}

function doDeleteVersion() {
    const v = versionToDelete.value
    if (!v) return
    saving.value = true
    errorMessage.value = ''
    axios.delete(adminApi.contentVersion(v.id))
        .then(() => {
            if (editingId.value === v.id) closeEditorModal()
            closeDeleteModal()
            successMessage.value = 'Versija pašalinta sėkmingai.'
            showSuccessModal.value = true
            loadVersions()
        })
        .catch(err => { errorMessage.value = err.response?.data?.message ?? 'Nepavyko pašalinti.' })
        .finally(() => { saving.value = false })
}

function closePublishModal() {
    publishModalVersion.value = null
}

function confirmPublish() {
    if (!publishModalVersion.value) return
    const id = publishModalVersion.value.id
    saving.value = true
    errorMessage.value = ''
    axios.post(adminApi.contentVersionPublish(id), { require_reconsent: requireReconsent.value })
        .then(() => {
            successMessage.value = requireReconsent.value
                ? 'Versija paskelbta sėkmingai. Pareiškėjams bus reikalaujama dar kartą sutikti su taisyklėmis.'
                : 'Versija paskelbta sėkmingai.'
            showSuccessModal.value = true
            loadVersions()
            closePublishModal()
        })
        .catch(err => { errorMessage.value = err.response?.data?.message ?? 'Klaida skelbiant' })
        .finally(() => { saving.value = false })
}

function formatDate(s) {
    return formatDateTimeLt(s, '–')
}
</script>

<template>
    <div class="content-editor-tab">
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />
        <ConfirmModal
            :open="!!versionToDelete"
            message="Ar tikrai norite pašalinti šią versiją?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            confirm-variant="danger"
            @close="closeDeleteModal"
            @confirm="doDeleteVersion"
        />
        <ConfirmModal
            :open="!!versionToUnpublish"
            message="Atšaukti skelbimą? Versija liks, bet nebus rodoma svečių puslapiuose. Vėliau galėsite ją redaguoti arba vėl paskelbti."
            confirm-text="Atšaukti skelbimą"
            cancel-text="Ne"
            :loading="saving"
            confirm-variant="primary"
            @close="closeUnpublishModal"
            @confirm="doUnpublish"
        />
        <p v-if="errorMessage" class="message message-error">{{ errorMessage }}</p>

        <div v-if="loading" class="loading">Kraunama…</div>
        <template v-else>
            <div class="editor-start-row">
                <AppButton
                    text="Nauja versija"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="openCreateModal"
                />
            </div>

            <table v-if="versions.length" class="versions-table">
                <thead>
                    <tr>
                        <th>Versija</th>
                        <th>Pavadinimas</th>
                        <th>Būsena</th>
                        <th>Paskelbta</th>
                        <th>Kūrėjas</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="v in versions" :key="v.id">
                        <td>{{ v.version }}</td>
                        <td>{{ v.title || '–' }}</td>
                        <td>
                            <span :class="v.is_published ? 'badge badge-published' : 'badge badge-draft'">
                                {{ v.is_published ? 'Paskelbta' : 'Juodraštis' }}
                            </span>
                        </td>
                        <td>{{ formatDate(v.published_at) }}</td>
                        <td>{{ v.creator_name || '–' }}</td>
                        <td>
                            <template v-if="v.is_published">
                                <button type="button" class="btn-link" @click="openEditModal(v)">Redaguoti</button>
                                <span class="sep">|</span>
                                <button type="button" class="btn-link" @click="openUnpublishModal(v)">Atšaukti skelbimą</button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn-link" @click="openEditModal(v)">Redaguoti</button>
                                <span class="sep">|</span>
                                <button type="button" class="btn-link" @click="openPublishModal(v)">Paskelbti</button>
                                <span class="sep">|</span>
                                <button type="button" class="btn-link btn-link-danger" @click="openDeleteModal(v)">Trinti</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="empty">Nėra versijų. Sukurkite pirmą versiją mygtuku aukščiau.</p>
        </template>

        <BaseModal
            class="content-editor-modal"
            :open="showEditorModal"
            :title="editorModalTitle"
            width="min(100%, 720px)"
            :maxWidth="'720px'"
            @close="closeEditorModal"
        >
            <div class="editor-modal-body">
                <AppInput
                    v-model="form.title"
                    type="text"
                    label="Pavadinimas"
                    placeholder="Neprivaloma"
                />
                <template v-if="isFaq">
                    <div class="field">
                        <span class="field-label">Dažniausiai užduodami klausimai</span>
                        <p class="field-help">Kiekvienas įrašas mobiliojoje programėlėje rodomas kaip atskira sulankstoma eilutė.</p>
                        <div class="faq-items">
                            <div v-for="(item, index) in form.items" :key="index" class="faq-item">
                                <div class="faq-item-header">
                                    <span class="faq-item-number">#{{ index + 1 }}</span>
                                    <div class="faq-item-actions">
                                        <button type="button" class="faq-icon-btn" :disabled="index === 0" @click="moveFaqItem(index, -1)" title="Pakelti">↑</button>
                                        <button type="button" class="faq-icon-btn" :disabled="index === form.items.length - 1" @click="moveFaqItem(index, 1)" title="Nuleisti">↓</button>
                                        <button type="button" class="faq-icon-btn faq-icon-btn-danger" @click="removeFaqItem(index)" title="Pašalinti">×</button>
                                    </div>
                                </div>
                                <input
                                    v-model="item.question"
                                    type="text"
                                    class="faq-question-input"
                                    placeholder="Klausimas"
                                />
                                <textarea
                                    v-model="item.answer"
                                    class="faq-answer-textarea"
                                    rows="3"
                                    placeholder="Atsakymas"
                                />
                            </div>
                        </div>
                        <button type="button" class="toolbar-btn faq-add-btn" @click="addFaqItem">+ Pridėti klausimą</button>
                    </div>
                </template>
                <div v-else class="field field--body">
                    <span class="field-label">Turinys (HTML)</span>
                    <div class="toolbar">
                        <button type="button" class="toolbar-btn" @click="insertTag('<b>', '</b>')">B</button>
                        <button type="button" class="toolbar-btn" @click="insertTag('<i>', '</i>')">I</button>
                        <button type="button" class="toolbar-btn" @click="insertParagraph()">Pastraipa</button>
                        <button type="button" class="toolbar-btn" @click="insertLink()">Nuoroda</button>
                        <button type="button" class="toolbar-btn" @click="insertList()">Sąrašas</button>
                    </div>
                    <textarea
                        ref="bodyTextareaRef"
                        v-model="form.body"
                        class="body-textarea"
                        rows="8"
                        placeholder="Įrašykite turinį…"
                    />
                </div>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    :primary-text="editingId ? 'Išsaugoti' : 'Sukurti versiją'"
                    :primary-disabled="saving"
                    @cancel="closeEditorModal"
                    @primary="save"
                />
            </template>
        </BaseModal>

        <BaseModal
            class="publish-modal"
            :open="!!publishModalVersion"
            :title="publishModalVersion ? `Paskelbti versiją ${publishModalVersion.version}?` : ''"
            width="520px"
            @close="closePublishModal"
        >
            <label v-if="isTerms()" class="checkbox-row">
                <AppCheckbox v-model="requireReconsent" />
                <span>Reikalauti pareiškėjų supažindinimo (sekančio prisijungimo metu jie turės išreikšti sutikimą su naujomis taisyklėmis)</span>
            </label>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Paskelbti"
                    :primary-disabled="saving"
                    @cancel="closePublishModal"
                    @primary="confirmPublish"
                />
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.content-editor-tab {
    padding: 0;
}
.message {
    margin: 0 0 12px 0;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 14px;
}
.message-error { background: #f8d7da; color: #721c24; }
.editor-start-row {
    margin: 0 0 16px 0;
}
.loading, .empty { color: #666; margin: 0 0 16px 0; font-size: 14px; }
.versions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-bottom: 12px;
}
.versions-table th,
.versions-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
    box-sizing: border-box;
}
.versions-table th {
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    color: var(--table-header-text);
    background: var(--table-header-bg);
}
.badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 500; }
.badge-published { background: #e8f5e9; color: #2e7d32; }
.badge-draft { background: #fff3cd; color: #856404; }
.btn-link {
    background: none;
    border: none;
    color: #1946a0;
    cursor: pointer;
    font-size: 14px;
    padding: 0;
    text-decoration: none;
}
.btn-link:hover {
    text-decoration: none;
    opacity: 0.85;
}
.btn-link-danger { color: #b91c1c; }
.btn-link-danger:hover {
    color: #991b1b;
    text-decoration: none;
    opacity: 0.9;
}
.sep { color: #9ca3af; margin: 0 4px; }
.editor-modal-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}
.field--body { margin: 0; }
.field-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 4px;
    color: #374151;
}
.toolbar { margin-bottom: 4px; }
.toolbar-btn {
    margin-right: 8px;
    padding: 4px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
}
.toolbar-btn:hover { background: #f3f4f6; }
.body-textarea {
    width: 100%;
    min-height: 140px;
    max-height: min(45vh, 360px);
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.4;
    font-family: inherit;
    box-sizing: border-box;
    resize: vertical;
}
.field-help {
    margin: 0 0 8px 0;
    color: #6b7280;
    font-size: 12px;
}
.faq-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 8px;
}
.faq-item {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px;
    background: #fafafa;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.faq-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.faq-item-number {
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
}
.faq-item-actions {
    display: flex;
    gap: 4px;
}
.faq-icon-btn {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    width: 24px;
    height: 24px;
    font-size: 14px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}
.faq-icon-btn:hover { background: #f3f4f6; }
.faq-icon-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.faq-icon-btn-danger { color: #b91c1c; }
.faq-icon-btn-danger:hover { background: #fee2e2; }
.faq-question-input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
    font-weight: 500;
}
.faq-answer-textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    line-height: 1.4;
    font-family: inherit;
    box-sizing: border-box;
    resize: vertical;
    min-height: 70px;
    max-height: 200px;
}
.faq-add-btn {
    margin-right: 0;
    width: fit-content;
}

.publish-modal .checkbox-row { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #333; }
.publish-modal .checkbox-row span { flex: 1; }
.publish-modal :deep(.modal-body) { padding-top: 8px; padding-bottom: 16px; }
.publish-modal :deep(.modal-footer) { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 20px; }
</style>
<style>
/* BaseModal + Teleport: scoped neveikia .modal (dedama į body) */
.modal.content-editor-modal {
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: min(88vh, 88dvh);
}
.modal.content-editor-modal .modal-header {
    padding: 20px 18px 12px 18px;
    flex-shrink: 0;
    align-items: flex-start;
    gap: 10px;
    min-height: 0;
    box-sizing: border-box;
}
.modal.content-editor-modal .x-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    flex-shrink: 0;
    margin-top: 0;
}
.modal.content-editor-modal .modal-title {
    flex: 1;
    min-width: 0;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 700;
    padding: 0;
    margin: 0;
    color: var(--color-text, #1a1a1a);
}
.modal.content-editor-modal .modal-body {
    padding: 12px 18px 16px !important;
    min-height: 0;
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;
    box-sizing: border-box;
}
.modal.content-editor-modal .modal-footer {
    flex-shrink: 0;
    padding: 12px 18px 16px;
    box-sizing: border-box;
}
.modal.content-editor-modal .app-input-group {
    width: 100%;
    max-width: 100%;
    gap: 4px;
}
.modal.content-editor-modal .app-input-label {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
}
</style>
