<script setup>
import { ref, watch, nextTick } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
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
const form = ref({ title: '', body: '' })
const publishModalVersion = ref(null)
const versionToDelete = ref(null)
const versionToUnpublish = ref(null)
const requireReconsent = ref(false)
const successMessage = ref('')
const showSuccessModal = ref(false)
const errorMessage = ref('')

const isTerms = () => props.type === 'terms'

function loadVersions() {
    loading.value = true
    errorMessage.value = ''
    axios.get(adminApi.contentVersions, { params: { type: props.type } })
        .then(res => { versions.value = res.data.versions })
        .catch(() => { errorMessage.value = 'Nepavyko gauti versijų' })
        .finally(() => { loading.value = false })
}

watch(() => props.type, () => {
    loadVersions()
    editingId.value = null
    publishModalVersion.value = null
}, { immediate: true })

function startNew() {
    editingId.value = null
    form.value = { title: '', body: '' }
}

function startEdit(v) {
    editingId.value = v.id
    form.value = { title: v.title ?? '', body: v.body ?? '' }
}

function insertTag(open, close = open) {
    const ta = document.getElementById('content-body-editor')
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
    const ta = document.getElementById('content-body-editor')
    if (!ta) return
    const start = ta.selectionStart
    const text = form.value.body
    const insert = '\n<ul>\n<li></li>\n</ul>\n'
    form.value.body = text.slice(0, start) + insert + text.slice(start)
    nextTick(() => ta.focus())
}

function insertLink() {
    const ta = document.getElementById('content-body-editor')
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
    const promise = editingId.value
        ? axios.put(adminApi.contentVersion(editingId.value), { title: form.value.title, body: form.value.body })
        : axios.post(adminApi.contentVersions, { type: props.type, title: form.value.title, body: form.value.body })
    promise
        .then(() => {
            successMessage.value = editingId.value ? 'Versija atnaujinta sėkmingai.' : 'Nauja versija sukurta sėkmingai.'
            showSuccessModal.value = true
            loadVersions()
            startNew()
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
            if (editingId.value === v.id) startNew()
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
    if (!s) return '–'
    try {
        const d = new Date(s)
        return d.toLocaleString('lt-LT')
    } catch {
        return s
    }
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
                                <button type="button" class="btn-link" @click="openUnpublishModal(v)">Atšaukti skelbimą</button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn-link" @click="startEdit(v)">Redaguoti</button>
                                <span class="sep">|</span>
                                <button type="button" class="btn-link" @click="openPublishModal(v)">Paskelbti</button>
                                <span class="sep">|</span>
                                <button type="button" class="btn-link btn-link-danger" @click="openDeleteModal(v)">Trinti</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="empty">Nėra versijų. Redaguokite formoje žemiau ir išsaugokite.</p>

            <div v-if="form !== null" class="editor-form">
                <h3 class="editor-form-title">{{ editingId ? 'Redaguoti versiją' : 'Kurti versiją' }}</h3>
                <label class="field">
                    <span>Pavadinimas</span>
                    <input v-model="form.title" type="text" class="input" placeholder="Neprivaloma" />
                </label>
                <label class="field">
                    <span>Turinys</span>
                    <div class="toolbar">
                        <button type="button" class="toolbar-btn" @click="insertTag('<b>', '</b>')">B</button>
                        <button type="button" class="toolbar-btn" @click="insertTag('<i>', '</i>')">I</button>
                        <button type="button" class="toolbar-btn" @click="insertLink()">Nuoroda</button>
                        <button type="button" class="toolbar-btn" @click="insertList()">Sąrašas</button>
                    </div>
                    <textarea
                        id="content-body-editor"
                        v-model="form.body"
                        class="body-textarea"
                        rows="14"
                        placeholder="Įrašykite turinį…"
                    />
                </label>
                <div class="form-actions">
                    <AppButton text="Atšaukti" variant="secondary" as-button @click="startNew" />
                    <AppButton :text="editingId ? 'Išsaugoti' : 'Sukurti versiją'" variant="primary" as-button :disabled="saving" @click="save" />
                </div>
            </div>
        </template>

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
.loading, .empty { color: #666; margin: 0 0 16px 0; font-size: 14px; }
.versions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-bottom: 24px;
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
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.badge-published { background: #d4edda; color: #155724; }
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
.editor-form {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    background: #fafafa;
}
.editor-form-title { margin: 0 0 16px 0; font-size: 16px; }
.field { display: block; margin-bottom: 12px; }
.field > span { display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px; color: #374151; }
.input { width: 100%; max-width: 400px; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
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
    min-height: 200px;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
}
.form-actions { display: flex; gap: 12px; margin-top: 16px; }

.publish-modal .checkbox-row { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #333; }
.publish-modal .checkbox-row span { flex: 1; }
.publish-modal :deep(.modal-body) { padding-top: 8px; padding-bottom: 16px; }
.publish-modal :deep(.modal-footer) { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 20px; }
</style>
