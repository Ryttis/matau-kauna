<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import ModalFooterActions from '@/Components/App/ModalFooterActions.vue'

const loading = ref(false)
const saving = ref(false)
const types = ref([])
const errorMessage = ref('')

const showAddTypeModal = ref(false)
const showEditTypeModal = ref(false)
const addTypeForm = ref({ name: '' })
const editTypeForm = ref({ id: null, name: '', is_active: true })

const showNaturesModal = ref(false)
const naturesModalTitle = ref('')
const selectedTypeForNatures = ref({ id: null, name: '' })
const naturesList = ref([])
const naturesLoading = ref(false)
const showAddNatureForm = ref(false)
const addNatureForm = ref({ name: '', is_active: true })
const showEditNatureModal = ref(false)
const editNatureForm = ref({ id: null, name: '', is_active: true })

const showDeleteConfirmModal = ref(false)
const typeToDelete = ref(null)
const showSuccessModal = ref(false)
const successMessage = ref('')

const loadClassifiers = async () => {
    loading.value = true
    try {
        const { data } = await axios.get('/admin/classifiers')
        types.value = data.types ?? []
    } finally {
        loading.value = false
    }
}

onMounted(loadClassifiers)

function openAddType() {
    addTypeForm.value = { name: '' }
    errorMessage.value = ''
    showAddTypeModal.value = true
}

function openEditType(type) {
    editTypeForm.value = {
        id: type.id,
        name: type.name,
        is_active: type.is_active,
    }
    errorMessage.value = ''
    showEditTypeModal.value = true
}

async function submitAddType() {
    const name = (addTypeForm.value.name || '').trim()
    if (!name) {
        errorMessage.value = 'Įveskite pavadinimą.'
        return
    }
    saving.value = true
    errorMessage.value = ''
    try {
        await axios.post('/admin/classifiers/values', {
            name,
        })
        showAddTypeModal.value = false
        await loadClassifiers()
        successMessage.value = 'Rūšis sėkmingai pridėta.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pridėti.'
    } finally {
        saving.value = false
    }
}

async function submitEditType() {
    const name = (editTypeForm.value.name || '').trim()
    if (!name) {
        errorMessage.value = 'Įveskite pavadinimą.'
        return
    }
    saving.value = true
    errorMessage.value = ''
    try {
        await axios.put(`/admin/classifiers/values/${editTypeForm.value.id}`, {
            name,
            is_active: editTypeForm.value.is_active,
        })
        showEditTypeModal.value = false
        await loadClassifiers()
        successMessage.value = 'Rūšis atnaujinta.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko atnaujinti.'
    } finally {
        saving.value = false
    }
}

async function toggleTypeActive(type) {
    try {
        await axios.put(`/admin/classifiers/values/${type.id}`, {
            is_active: !type.is_active,
        })
        await loadClassifiers()
        successMessage.value = 'Rūšies būsena pakeista.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pakeisti.'
    }
}

function confirmDeleteType(t) {
    typeToDelete.value = t
    showDeleteConfirmModal.value = true
}

function closeDeleteConfirmModal() {
    showDeleteConfirmModal.value = false
    typeToDelete.value = null
}

async function deleteType() {
    if (!typeToDelete.value) return
    const t = typeToDelete.value
    errorMessage.value = ''
    successMessage.value = ''
    saving.value = true
    try {
        await axios.delete(`/admin/classifiers/values/${t.id}`)
        closeDeleteConfirmModal()
        await loadClassifiers()
        successMessage.value = 'Rūšis sėkmingai pašalinta.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pašalinti.'
    } finally {
        saving.value = false
    }
}

async function openNatures(type) {
    selectedTypeForNatures.value = { id: type.id, name: type.name }
    naturesModalTitle.value = `Pobūdžiai: ${type.name}`
    naturesList.value = []
    showNaturesModal.value = true
    showAddNatureForm.value = false
    addNatureForm.value = { name: '', is_active: true }
    await loadNaturesForType(type.id)
}

async function loadNaturesForType(typeId) {
    naturesLoading.value = true
    try {
        const { data } = await axios.get(`/admin/classifiers/types/${typeId}/natures`)
        naturesList.value = data.natures ?? []
    } catch {
        naturesList.value = []
    } finally {
        naturesLoading.value = false
    }
}

function openAddNature() {
    addNatureForm.value = { name: '', is_active: true }
    showAddNatureForm.value = true
}

async function submitAddNature() {
    const name = (addNatureForm.value.name || '').trim()
    if (!name) return
    saving.value = true
    try {
        await axios.post('/admin/classifiers/values', {
            parent_id: selectedTypeForNatures.value.id,
            name,
            is_active: addNatureForm.value.is_active,
        })
        showAddNatureForm.value = false
        await loadNaturesForType(selectedTypeForNatures.value.id)
        successMessage.value = 'Pobūdis pridėtas.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pridėti pobūdžio.'
    } finally {
        saving.value = false
    }
}

function openEditNature(nature) {
    editNatureForm.value = {
        id: nature.id,
        name: nature.name,
        is_active: nature.is_active,
    }
    showEditNatureModal.value = true
}

async function submitEditNature() {
    const name = (editNatureForm.value.name || '').trim()
    if (!name) return
    saving.value = true
    try {
        await axios.put(`/admin/classifiers/values/${editNatureForm.value.id}`, {
            name,
            is_active: editNatureForm.value.is_active,
        })
        showEditNatureModal.value = false
        await loadNaturesForType(selectedTypeForNatures.value.id)
        successMessage.value = 'Pobūdis atnaujintas.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko atnaujinti.'
    } finally {
        saving.value = false
    }
}

async function toggleNatureActive(nature) {
    try {
        await axios.put(`/admin/classifiers/values/${nature.id}`, {
            is_active: !nature.is_active,
        })
        await loadNaturesForType(selectedTypeForNatures.value.id)
        successMessage.value = 'Pobūdžio būsena pakeista.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pakeisti.'
    }
}

async function removeNature(nature) {
    try {
        await axios.put(`/admin/classifiers/values/${nature.id}`, {
            is_active: false,
        })
        await loadNaturesForType(selectedTypeForNatures.value.id)
        successMessage.value = 'Pobūdis pašalintas.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pašalinti.'
    }
}
</script>

<template>
    <div class="admin-classifiers">
        <h2 class="title">Klasifikatoriai ir terminai</h2>
        <p class="desc">
            Valdykite problemų rūšis. Prie kiekvienos rūšies galite pridėti pobūdžius – jie bus rodomi pareiškėjui registruojant problemą pasirinkus atitinkamą rūšį.
        </p>
        <p v-if="errorMessage" class="error-msg">{{ errorMessage }}</p>

        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />

        <div v-if="loading" class="loading">Kraunama…</div>
        <template v-else>
            <div class="classifier-block">
                <h3 class="category-title">Problemos rūšis</h3>
                <div class="toolbar">
                    <AppButton
                        text="Pridėti rūšį"
                        variant="primary"
                        as-button
                        @click="openAddType"
                    />
                </div>
                <div class="table-wrap">
                    <table class="values-table">
                        <thead>
                            <tr>
                                <th>Pavadinimas</th>
                                <th>Aktyvi</th>
                                <th>Veiksmai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in types"
                                :key="t.id"
                                :class="{ 'row-inactive': !t.is_active }"
                            >
                                <td>{{ t.name }}</td>
                                <td>
                                    <label class="toggle-label">
                                        <input
                                            type="checkbox"
                                            :checked="t.is_active"
                                            @change="toggleTypeActive(t)"
                                        />
                                        <span class="toggle-text">{{ t.is_active ? 'Taip' : 'Ne' }}</span>
                                    </label>
                                </td>
                                <td class="actions-cell">
                                    <button
                                        type="button"
                                        class="link-btn"
                                        @click="openNatures(t)"
                                    >
                                        Pobūdžiai
                                    </button>
                                    <span class="link-sep">|</span>
                                    <button
                                        type="button"
                                        class="link-btn"
                                        @click="openEditType(t)"
                                    >
                                        Redaguoti
                                    </button>
                                    <span class="link-sep">|</span>
                                    <button
                                        type="button"
                                        class="link-btn link-btn--danger"
                                        @click="confirmDeleteType(t)"
                                    >
                                        Trinti
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!types.length" class="empty">Nėra rūšių. Pridėkite pirmąją rūšį.</p>
                </div>
            </div>
        </template>

        <BaseModal
            :show="showAddTypeModal"
            title="Pridėti rūšį"
            max-width="400px"
            @close="showAddTypeModal = false"
        >
            <div class="form">
                <AppInput
                    v-model="addTypeForm.name"
                    label="Pavadinimas"
                    placeholder="pvz. Eismas"
                />
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Pridėti"
                    :primary-disabled="saving"
                    @cancel="showAddTypeModal = false"
                    @primary="submitAddType"
                />
            </template>
        </BaseModal>

        <BaseModal
            :show="showEditTypeModal"
            title="Redaguoti rūšį"
            max-width="400px"
            @close="showEditTypeModal = false"
        >
            <div class="form">
                <AppInput
                    v-model="editTypeForm.name"
                    label="Pavadinimas"
                />
                <label class="checkbox-label">
                    <AppCheckbox v-model="editTypeForm.is_active" />
                    <span>Aktyvi (rodoma pareiškėjams)</span>
                </label>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Išsaugoti"
                    :primary-disabled="saving"
                    @cancel="showEditTypeModal = false"
                    @primary="submitEditType"
                />
            </template>
        </BaseModal>

        <BaseModal
            :show="showNaturesModal"
            :title="naturesModalTitle"
            max-width="520px"
            @close="showNaturesModal = false"
        >
            <div class="natures-modal-body">
                <div class="natures-toolbar">
                    <AppButton
                        v-if="!showAddNatureForm"
                        text="Pridėti pobūdį"
                        variant="primary"
                        as-button
                        @click="openAddNature"
                    />
                    <div v-else class="add-nature-form">
                        <AppInput
                            v-model="addNatureForm.name"
                            label="Pavadinimas"
                            placeholder="pvz. Defektas"
                        />
                        <label class="checkbox-label">
                            <AppCheckbox v-model="addNatureForm.is_active" />
                            <span>Aktyvi</span>
                        </label>
                        <div class="add-nature-btns">
                            <AppButton text="Atšaukti" variant="secondary" as-button @click="showAddNatureForm = false" />
                            <AppButton text="Pridėti" variant="primary" as-button :disabled="saving" @click="submitAddNature" />
                        </div>
                    </div>
                </div>
                <div v-if="naturesLoading" class="loading">Kraunama…</div>
                <div v-else class="table-wrap">
                    <table class="values-table values-table--narrow">
                        <thead>
                            <tr>
                                <th>Pavadinimas</th>
                                <th>Aktyvi</th>
                                <th>Veiksmai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="n in naturesList"
                                :key="n.id"
                                :class="{ 'row-inactive': !n.is_active }"
                            >
                                <td>{{ n.name }}</td>
                                <td>
                                    <label class="toggle-label">
                                        <input
                                            type="checkbox"
                                            :checked="n.is_active"
                                            @change="toggleNatureActive(n)"
                                        />
                                        <span class="toggle-text">{{ n.is_active ? 'Taip' : 'Ne' }}</span>
                                    </label>
                                </td>
                                <td class="actions-cell">
                                    <button type="button" class="link-btn" @click="openEditNature(n)">Redaguoti</button>
                                    <span class="link-sep">|</span>
                                    <button
                                        type="button"
                                        class="link-btn link-btn--danger"
                                        @click="removeNature(n)"
                                    >
                                        Pašalinti
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!naturesList.length" class="empty">Nėra pobūdžių. Pridėkite pobūdį.</p>
                </div>
            </div>
        </BaseModal>

        <BaseModal
            :show="showEditNatureModal"
            title="Redaguoti pobūdį"
            max-width="400px"
            @close="showEditNatureModal = false"
        >
            <div class="form">
                <AppInput
                    v-model="editNatureForm.name"
                    label="Pavadinimas"
                />
                <label class="checkbox-label">
                    <AppCheckbox v-model="editNatureForm.is_active" />
                    <span>Aktyvi (rodoma pareiškėjams)</span>
                </label>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Išsaugoti"
                    :primary-disabled="saving"
                    @cancel="showEditNatureModal = false"
                    @primary="submitEditNature"
                />
            </template>
        </BaseModal>

        <BaseModal
            :show="showDeleteConfirmModal"
            title="Pašalinti rūšį?"
            max-width="400px"
            @close="closeDeleteConfirmModal"
        >
            <p v-if="typeToDelete" class="delete-confirm-text">
                Ar tikrai pašalinti rūšį „{{ typeToDelete.name }}"? Bus pašalinti ir visi jos pobūdžiai.
            </p>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Trinti"
                    :primary-disabled="saving"
                    @cancel="closeDeleteConfirmModal"
                    @primary="deleteType"
                />
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.admin-classifiers {
    padding: 24px 32px;
    max-width: 900px;
}
.title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 8px 0;
}
.desc {
    font-size: 14px;
    color: #555;
    margin: 0 0 20px 0;
    line-height: 1.5;
}
.error-msg {
    color: #c00;
    font-size: 14px;
    margin-bottom: 12px;
}
.delete-confirm-text {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
}
.loading {
    padding: 24px 0;
    color: #666;
}
.classifier-block {
    margin-bottom: 32px;
}
.category-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 12px 0;
}
.toolbar {
    margin-bottom: 12px;
}
.table-wrap {
    overflow-x: auto;
}
.values-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.values-table th,
.values-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e8e8e8;
}
.values-table th {
    font-weight: 600;
    background: #f5f5f5;
}
.values-table--narrow th,
.values-table--narrow td {
    padding: 8px 10px;
}
.row-inactive td {
    color: #888;
}
.actions-cell {
    white-space: nowrap;
}
.toggle-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.toggle-label input {
    cursor: pointer;
}
.link-btn {
    background: none;
    border: none;
    color: #1946A0;
    cursor: pointer;
    text-decoration: underline;
    font-size: 14px;
    padding: 0;
}
.link-btn:hover {
    color: #0d3580;
}
.link-btn--danger {
    color: #a00;
}
.link-btn--danger:hover {
    color: #800;
}
.link-sep {
    margin: 0 6px;
    color: #999;
}
.empty {
    color: #666;
    font-size: 14px;
    padding: 16px 0;
    margin: 0;
}
.form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
}
.natures-modal-body {
    min-height: 200px;
}
.natures-toolbar {
    margin-bottom: 16px;
}
.add-nature-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px 0;
}
.add-nature-btns {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.add-nature-btns :deep(.app-button) {
    width: auto !important;
    min-width: 80px;
}
</style>
