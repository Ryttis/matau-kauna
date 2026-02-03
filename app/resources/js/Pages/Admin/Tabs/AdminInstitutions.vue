<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import { INSTITUTION_TYPES } from '@/constants/institutionTypes.js'

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const institutionsPage = ref(null)

const isCreateOpen = ref(false)
const isEditOpen = ref(false)

const createForm = ref({
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
    is_email_only: false
})

const editForm = ref({
    id: null,
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
    is_email_only: false
})

const loadInstitutions = async () => {
    const { data } = await axios.get('/admin/institutions')
    institutionsPage.value = data
}

const openCreate = () => {
    createForm.value = {
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
        is_email_only: false
    }
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
        integration_email: inst.integration_email ?? '',
        integration_dvs: inst.integration_dvs ?? '',
        integration_gis: inst.integration_gis ?? '',
        is_active: inst.is_active ?? true,
        is_email_only: inst.is_email_only ?? false
    }
    isEditOpen.value = true
}

const createInstitution = async () => {
    errorMessage.value = ''
    saving.value = true
    try {
        await axios.post('/admin/institutions', {
            ...createForm.value,
            is_email_only: createForm.value.is_email_only ?? false
        })
        isCreateOpen.value = false
        await loadInstitutions()
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
            integration_email: editForm.value.integration_email || null,
            integration_dvs: editForm.value.integration_dvs || null,
            integration_gis: editForm.value.integration_gis || null,
            is_active: editForm.value.is_active,
            is_email_only: editForm.value.is_email_only ?? false
        })
        isEditOpen.value = false
        await loadInstitutions()
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
    <div>
        <div class="header-row">
            <h3 class="title">Įstaigos</h3>
            <button type="button" class="primary-btn small" @click="openCreate">
                + Kurti įstaigą
            </button>
        </div>

        <div v-if="errorMessage" class="error">
            {{ errorMessage }}
        </div>

        <div v-if="loading">Kraunama...</div>

        <table v-else-if="institutionsPage?.data?.length" class="table">
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
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra įstaigų. Sukurkite pirmąją.</div>

        <!-- Create -->
        <BaseModal :open="isCreateOpen" title="Kurti įstaigą" width="640px" @close="isCreateOpen = false">
            <div class="form-grid">
                <label class="field">
                    <span>Pavadinimas *</span>
                    <input class="input" v-model="createForm.name" />
                </label>
                <label class="field">
                    <span>Kodas</span>
                    <input class="input" v-model="createForm.code" />
                </label>
                <label class="field">
                    <span>Tipas</span>
                    <select class="input" v-model="createForm.type">
                        <option v-for="opt in INSTITUTION_TYPES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </label>
                <label class="field full">
                    <span>Kontaktiniai duomenys</span>
                    <input class="input" v-model="createForm.contact_phone" placeholder="Tel." />
                    <input class="input" style="margin-top:6px" v-model="createForm.contact_email" placeholder="El. paštas" />
                    <input class="input" style="margin-top:6px" v-model="createForm.contact_address" placeholder="Adresas" />
                </label>
                <label class="field full">
                    <span>Integracijos nustatymai (el. paštas / DVS / GIS)</span>
                    <input class="input" v-model="createForm.integration_email" placeholder="El. paštas" />
                    <input class="input" style="margin-top:6px" v-model="createForm.integration_dvs" placeholder="DVS" />
                    <input class="input" style="margin-top:6px" v-model="createForm.integration_gis" placeholder="GIS" />
                </label>
                <label class="field checkbox">
                    <input type="checkbox" v-model="createForm.is_active" />
                    <span>Aktyvi įstaiga</span>
                </label>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="createForm.is_email_only" />
                    <span>Įstaiga be naudotojų sistemoje (tik el. paštu)</span>
                </label>
            </div>
            <template #footer>
                <button type="button" class="secondary-btn" @click="isCreateOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving || !createForm.name" @click="createInstitution">
                    {{ saving ? 'Kuriama...' : 'Sukurti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Edit -->
        <BaseModal :open="isEditOpen" title="Redaguoti įstaigą" width="640px" @close="isEditOpen = false">
            <div class="form-grid">
                <label class="field">
                    <span>Pavadinimas *</span>
                    <input class="input" v-model="editForm.name" />
                </label>
                <label class="field">
                    <span>Kodas</span>
                    <input class="input" v-model="editForm.code" />
                </label>
                <label class="field">
                    <span>Tipas</span>
                    <select class="input" v-model="editForm.type">
                        <option v-for="opt in INSTITUTION_TYPES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </label>
                <label class="field full">
                    <span>Kontaktiniai duomenys</span>
                    <input class="input" v-model="editForm.contact_phone" placeholder="Tel." />
                    <input class="input" style="margin-top:6px" v-model="editForm.contact_email" placeholder="El. paštas" />
                    <input class="input" style="margin-top:6px" v-model="editForm.contact_address" placeholder="Adresas" />
                </label>
                <label class="field full">
                    <span>Integracijos nustatymai (el. paštas / DVS / GIS)</span>
                    <input class="input" v-model="editForm.integration_email" placeholder="El. paštas" />
                    <input class="input" style="margin-top:6px" v-model="editForm.integration_dvs" placeholder="DVS" />
                    <input class="input" style="margin-top:6px" v-model="editForm.integration_gis" placeholder="GIS" />
                </label>
                <label class="field checkbox">
                    <input type="checkbox" v-model="editForm.is_active" />
                    <span>Aktyvi įstaiga</span>
                </label>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="editForm.is_email_only" />
                    <span>Įstaiga be naudotojų sistemoje (tik el. paštu)</span>
                </label>
            </div>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isEditOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving || !editForm.name" @click="updateInstitution">
                    {{ saving ? 'Saugoma...' : 'Išsaugoti' }}
                </button>
            </template>
        </BaseModal>
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

.table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #eef0f3;
    font-size: 13px;
    vertical-align: top;
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
    width: 120px;
}

.link-btn {
    border: none;
    background: transparent;
    color: #1946a0;
    cursor: pointer;
    padding: 0;
    font-size: 13px;
}

.link-btn:hover {
    text-decoration: underline;
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
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
}

.field.checkbox {
    flex-direction: row;
    align-items: center;
}

.field span {
    font-size: 13px;
    font-weight: 500;
}

.input {
    padding: 8px 10px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}

.input:focus {
    outline: none;
    border-color: #1946a0;
}

textarea.input {
    resize: vertical;
    min-height: 60px;
}

.primary-btn,
.secondary-btn {
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
}

.primary-btn {
    background: #1946a0;
    color: #fff;
    border: none;
}

.primary-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.primary-btn.small {
    padding: 8px 14px;
    font-size: 13px;
}

.secondary-btn {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
}
</style>
