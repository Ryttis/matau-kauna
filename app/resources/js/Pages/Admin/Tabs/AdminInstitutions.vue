<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import AppInput from '@/Components/App/AppInput.vue'
import ModalFooterActions from '@/Components/App/ModalFooterActions.vue'
import { INSTITUTION_TYPES } from '@/constants/institutionTypes'

function emptyInstitutionForm() {
    return {
        name: '',
        code: '',
        type: '',
        contact_phone: '',
        contact_email: '',
        contact_address: '',
        integration_email: '',
        integration_dvs: '',
        integration_gis: '',
        is_active: true,
        is_email_only: false,
    }
}

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const institutionsPage = ref(null)
const isCreateOpen = ref(false)
const isEditOpen = ref(false)
const createForm = ref(emptyInstitutionForm())
const editForm = ref(emptyInstitutionForm())
const showDeleteModal = ref(false)
const institutionToDelete = ref(null)
const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const deleteConfirmMessage = computed(() => {
    const inst = institutionToDelete.value
    if (!inst) return ''
    return 'Ar tikrai pašalinti įstaigą „' + inst.name + '"? Naudotojų įstaiga bus atimta, susijusios problemos liks be įstaigos.'
})

const loadInstitutions = async () => {
    const { data } = await axios.get('/admin/institutions')
    institutionsPage.value = data
}

const openCreate = () => {
    createForm.value = emptyInstitutionForm()
    isCreateOpen.value = true
}

const openEdit = (inst) => {
    editForm.value = {
        id: inst.id,
        name: inst.name ?? '',
        code: inst.code ?? '',
        type: inst.type ?? '',
        contact_phone: inst.contact_phone ?? '',
        contact_email: inst.contact_email ?? '',
        contact_address: inst.contact_address ?? '',
        integration_email: inst.contact_email ?? inst.integration_email ?? '',
        integration_dvs: inst.integration_dvs ?? '',
        integration_gis: inst.integration_gis ?? '',
        is_active: inst.is_active ?? true,
        is_email_only: inst.is_email_only ?? false,
    }
    isEditOpen.value = true
}

const confirmDelete = (inst) => {
    institutionToDelete.value = inst
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    showDeleteModal.value = false
    institutionToDelete.value = null
}

const deleteInstitution = async () => {
    if (!institutionToDelete.value) return
    errorMessage.value = ''
    saving.value = true
    try {
        await axios.delete(`/admin/institutions/${institutionToDelete.value.id}`)
        closeDeleteModal()
        await loadInstitutions()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pašalinti įstaigos.'
    } finally {
        saving.value = false
    }
}

const createInstitution = async () => {
    errorMessage.value = ''
    saving.value = true
    try {
        await axios.post('/admin/institutions', {
            ...createForm.value,
            integration_email: createForm.value.contact_email || null,
            is_email_only: createForm.value.is_email_only ?? false,
        })
        isCreateOpen.value = false
        await loadInstitutions()
        showWithMessage('Įstaiga sukurta.')
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko sukurti įstaigos.'
    } finally {
        saving.value = false
    }
}

const updateInstitution = async () => {
    errorMessage.value = ''
    saving.value = true
    try {
        await axios.put(`/admin/institutions/${editForm.value.id}`, {
            name: editForm.value.name,
            code: editForm.value.code || null,
            type: editForm.value.type || null,
            contact_phone: editForm.value.contact_phone || null,
            contact_email: editForm.value.contact_email || null,
            contact_address: editForm.value.contact_address || null,
            integration_email: editForm.value.contact_email || null,
            integration_dvs: editForm.value.integration_dvs || null,
            integration_gis: editForm.value.integration_gis || null,
            is_active: editForm.value.is_active,
            is_email_only: editForm.value.is_email_only ?? false,
        })
        isEditOpen.value = false
        await loadInstitutions()
        showWithMessage('Įstaigos duomenys atnaujinti.')
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko išsaugoti įstaigos.'
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    loading.value = true
    try {
        await loadInstitutions()
    } catch (e) {
        errorMessage.value = 'Nepavyko užkrauti įstaigų.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="admin-tab">
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="header-row">
            <h3 class="title">Įstaigos</h3>
            <AppButton text="+ Kurti įstaigą" variant="primary" as-button @click="openCreate" />
        </div>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else-if="institutionsPage?.data?.length">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pavadinimas</th>
                            <th>Kodas</th>
                            <th>Tipas</th>
                            <th>Kontaktai</th>
                            <th>Naudotojai</th>
                            <th>Būsena</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inst in institutionsPage.data" :key="inst.id">
                            <td>{{ inst.name }}</td>
                            <td>{{ inst.code ?? '—' }}</td>
                            <td>{{ inst.type ?? '—' }}</td>
                            <td>{{ inst.contact_email ?? inst.contact_phone ?? '—' }}</td>
                            <td>{{ inst.users_count ?? 0 }}</td>
                            <td>
                                <span :class="['pill', inst.is_active ? 'active' : 'inactive']">
                                    {{ inst.is_active ? 'Aktyvi' : 'Neaktyvi' }}
                                </span>
                                <span v-if="inst.is_email_only" class="pill email-only">Tik el. paštas</span>
                            </td>
                            <td class="actions">
                                <button type="button" class="link-btn" @click="openEdit(inst)">Redaguoti</button>
                                <span class="link-sep">|</span>
                                <button type="button" class="link-btn link-btn--danger" @click="confirmDelete(inst)">
                                    Trinti
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <p v-else class="empty">Nėra įstaigų. Sukurkite pirmąją.</p>

        <BaseModal
            :show="isCreateOpen"
            title="Kurti įstaigą"
            max-width="640px"
            @close="isCreateOpen = false"
        >
            <div class="form-grid">
                <AppInput v-model="createForm.name" label="Pavadinimas *" />
                <AppInput v-model="createForm.code" label="Kodas" />
                <label class="field">
                    <span>Tipas</span>
                    <select class="input" v-model="createForm.type">
                        <option v-for="opt in INSTITUTION_TYPES" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </label>
                <div class="field full">
                    <span class="field-label">Kontaktiniai duomenys</span>
                    <AppInput v-model="createForm.contact_phone" placeholder="Tel." />
                    <AppInput v-model="createForm.contact_email" placeholder="El. paštas" type="email" />
                    <AppInput v-model="createForm.contact_address" placeholder="Adresas" />
                </div>
                <div class="field full">
                    <span class="field-label">Integracijos (DVS / GIS)</span>
                    <AppInput v-model="createForm.integration_dvs" placeholder="DVS" />
                    <AppInput v-model="createForm.integration_gis" placeholder="GIS" />
                </div>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="createForm.is_active" />
                    <span>Aktyvi įstaiga</span>
                </label>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="createForm.is_email_only" />
                    <span>Įstaiga be naudotojų (tik el. paštas)</span>
                </label>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Sukurti"
                    :primary-disabled="saving || !createForm.name"
                    @cancel="isCreateOpen = false"
                    @primary="createInstitution"
                />
            </template>
        </BaseModal>

        <BaseModal
            :show="isEditOpen"
            title="Redaguoti įstaigą"
            max-width="640px"
            @close="isEditOpen = false"
        >
            <div class="form-grid">
                <AppInput v-model="editForm.name" label="Pavadinimas *" />
                <AppInput v-model="editForm.code" label="Kodas" />
                <label class="field">
                    <span>Tipas</span>
                    <select class="input" v-model="editForm.type">
                        <option v-for="opt in INSTITUTION_TYPES" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </label>
                <div class="field full">
                    <span class="field-label">Kontaktiniai duomenys</span>
                    <AppInput v-model="editForm.contact_phone" placeholder="Tel." />
                    <AppInput v-model="editForm.contact_email" placeholder="El. paštas" type="email" />
                    <AppInput v-model="editForm.contact_address" placeholder="Adresas" />
                </div>
                <div class="field full">
                    <span class="field-label">Integracijos (DVS / GIS)</span>
                    <AppInput v-model="editForm.integration_dvs" placeholder="DVS" />
                    <AppInput v-model="editForm.integration_gis" placeholder="GIS" />
                </div>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="editForm.is_active" />
                    <span>Aktyvi įstaiga</span>
                </label>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="editForm.is_email_only" />
                    <span>Įstaiga be naudotojų (tik el. paštas)</span>
                </label>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Išsaugoti"
                    :primary-disabled="saving || !editForm.name"
                    @cancel="isEditOpen = false"
                    @primary="updateInstitution"
                />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showDeleteModal"
            title="Pašalinti įstaigą?"
            :message="deleteConfirmMessage"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            confirm-variant="danger"
            @close="closeDeleteModal"
            @confirm="deleteInstitution"
        />
    </div>
</template>

<style scoped>
.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.title {
    font-size: 18px;
    font-weight: 700;
}

.table-wrap {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
}

th,
td {
    text-align: left;
    padding: 12px 10px;
    border-bottom: 1px solid var(--color-border-light);
    font-size: 13px;
    vertical-align: middle;
    box-sizing: border-box;
}

.pill {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 12px;
}

.pill.active {
    background: #e8f5e9;
    color: #2e7d32;
}

.pill.inactive {
    background: #ffebee;
    color: #c62828;
}

.pill.email-only {
    background: #e3f2fd;
    color: #1565c0;
    margin-left: 6px;
}

.actions {
    white-space: nowrap;
}

.link-btn {
    border: none;
    background: transparent;
    color: var(--color-primary);
    cursor: pointer;
    padding: 0;
    font-size: 13px;
}

.link-btn:hover {
    text-decoration: underline;
}

.link-sep {
    margin: 0 6px;
    color: var(--color-gray-400);
}

.link-btn--danger {
    color: #c62828;
}

.link-btn--danger:hover {
    color: var(--color-primary-hover);
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: var(--color-error);
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.loading,
.empty {
    color: var(--color-text-muted);
    margin: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.field.full {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field.checkbox {
    flex-direction: row;
    align-items: center;
}

.field span,
.field-label {
    font-size: 13px;
    font-weight: 500;
}

.input {
    padding: 8px 10px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 14px;
}

.input:focus {
    outline: none;
    border-color: var(--color-primary);
}
</style>
